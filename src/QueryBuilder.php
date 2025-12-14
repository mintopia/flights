<?php

declare(strict_types=1);

namespace Mintopia\Flights;

use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Mintopia\Flights\Enums\BookingClass;
use Mintopia\Flights\Enums\PassengerType;
use Mintopia\Flights\Enums\SortOrder;
use Mintopia\Flights\Exceptions\DecoderException;
use Mintopia\Flights\Exceptions\DependencyException;
use Mintopia\Flights\Exceptions\GoogleException;
use Mintopia\Flights\Exceptions\SearchException;
use Mintopia\Flights\Models\Itinerary;
use Mintopia\Flights\Models\Journey;
use Mintopia\Flights\Models\Segment;
use Mintopia\Flights\Protobuf\Info;
use Mintopia\Flights\Protobuf\ItineraryData;
use Mintopia\Flights\Protobuf\Seat;
use Mintopia\Flights\Protobuf\Trip;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class QueryBuilder
{
    protected string $language;
    protected string $currency;

    /**
     * @var PassengerType[]
     */
    protected array $passengers = [];
    public SortOrder $sortOrder = SortOrder::Price;
    public BookingClass $bookingClass = BookingClass::Economy;

    /**
     * @var Segment[]
     */
    public array $segments;
    protected bool $cache = true;
    protected FlightService $flightService;

    public function __construct(?FlightService $flightService = null)
    {
        if ($flightService !== null) {
            $this->flightService = $flightService;
        }
    }

    public function setFlightService(FlightService $flightService): self
    {
        $clone = clone $this;
        $clone->flightService = $flightService;
        return $clone;
    }

    public function addPassenger(PassengerType $passenger): self
    {
        $clone = clone $this;
        $clone->passengers[] = $passenger;
        return $clone;
    }

    /**
     * @param PassengerType[] $passengers
     * @return self
     */
    public function setPassengers(array $passengers): self
    {
        $clone = clone $this;
        $clone->passengers = $passengers;
        return $clone;
    }

    public function clearPassengers(): self
    {
        $clone = clone $this;
        $clone->passengers = [];
        return $clone;
    }

    public function setBookingClass(BookingClass $bookingClass): self
    {
        $clone = clone $this;
        $clone->bookingClass = $bookingClass;
        return $clone;
    }

    public function setSortOrder(SortOrder $sortOrder): self
    {
        $clone = clone $this;
        $clone->sortOrder = $sortOrder;
        return $clone;
    }

    public function setLanguage(string $language): self
    {
        $clone = clone $this;
        $clone->language = $language;
        return $clone;
    }

    public function withoutCache(): self
    {
        $clone = clone $this;
        $clone->cache = false;
        return $clone;
    }

    public function setCurrency(string $currency): self
    {
        $clone = clone $this;
        $clone->currency = $currency;
        return $clone;
    }

    /**
     * @param string|string[] $from
     * @param string|string[] $to
     * @param string|DateTimeInterface|null $date
     * @param int $maxStops
     * @param string|string[] $airlines
     * @return self
     * @throws DateMalformedStringException
     */
    public function addSegment(string|array $from, string|array $to, string|DateTimeInterface|null $date = null, int $maxStops = 0, string|array $airlines = []): self
    {
        $from = $this->normaliseArray($from);
        $to = $this->normaliseArray($to);
        $airlines = $this->normaliseArray($airlines);

        if ($date === null) {
            $date = $this->flightService->clock->now()->setTimezone(new DateTimeZone('UTC'));
        } elseif (is_string($date)) {
            $date =  new DateTimeImmutable($date, new DateTimeZone('UTC'));
        }

        $segment = new Segment($from, $to, $date, $maxStops, $airlines);
        $clone = clone $this;
        $clone->segments[] = $segment;
        return $clone;
    }

    /**
     * @param mixed $array
     * @return string[]
     */
    protected function normaliseArray(mixed $array): array
    {
        if (is_string($array)) {
            $array = explode(',', $array);
        }
        return array_filter($array);
    }

    public function clearSegments(): self
    {
        $clone = clone $this;
        $clone->segments = [];
        return $clone;
    }

    protected function getSortOrder(): string
    {
        return match ($this->sortOrder) {
            SortOrder::Best => 'EgQIARABIgA',
            SortOrder::DepartureTime => 'EgQIAxABIgA',
            SortOrder::Price => 'EgQIAhABIgA',
            SortOrder::Duration => 'EgQIBRABIgA',
        };
    }

    /**
     * @param ItineraryData[]|null $itineraryData
     * @return string
     * @throws SearchException
     */
    protected function encode(?array $itineraryData = null): string
    {
        $segmentCount = count($this->segments);
        if ($segmentCount === 0) {
            throw new SearchException('No segments specified');
        }
        if ($segmentCount > 2) {
            throw new SearchException('Multi city searches are not supported');
        }
        $flightData = [];
        foreach ($this->segments as $i => $segment) {
            $segmentData = $segment->encode();
            if (isset($itineraryData[$i])) {
                $segmentData->setItinData(array_slice($itineraryData, 0, $i + 1));
            }
            $flightData[] = $segmentData;
        }

        $tripType = match (true) {
            $segmentCount == 1 => Trip::ONE_WAY,
            true => Trip::MULTI_CITY,
        };

        $seat = match ($this->bookingClass) {
            BookingClass::Unknown => Seat::UNKNOWN_SEAT,
            BookingClass::Economy => Seat::ECONOMY,
            BookingClass::PremiumEconomy => Seat::PREMIUM_ECONOMY,
            BookingClass::Business => Seat::BUSINESS,
            BookingClass::First => Seat::FIRST,
        };

        if (count($this->passengers) === 0) {
            $this->addPassenger(PassengerType::Adult);
        }

        $info = new Info()
            ->setData($flightData)
            ->setTrip($tripType)
            ->setPassengers(array_map(function (PassengerType $passengerType): int {
                return $passengerType->value;
            }, $this->passengers))
            ->setSeat($seat);
        return base64_encode($info->serializeToString());
    }

    /**
     * @return Itinerary[]
     * @throws ContainerExceptionInterface
     * @throws DecoderException
     * @throws DependencyException
     * @throws GoogleException
     * @throws NotFoundExceptionInterface
     * @throws SearchException
     */
    public function get(): array
    {
        if (!isset($this->flightService)) {
            throw new DependencyException('FlightService not provided for QueryBuilder');
        }
        /** @var Itinerary[] $itineraries */
        $itineraries = [];
        $this->flightService->log->debug("[Segment:1] Fetching journeys");

        // Get our first journeys
        $journeys = $this->getJourneys();
        $this->flightService->log->debug("[Segment:1] Found " . count($journeys) . " journeys");
        foreach ($journeys as $journey) {
            /**
             * @var Itinerary $itinerary
             */
            $itinerary = $this->flightService->container->get(Itinerary::class);
            $itinerary->addJourney($journey);
            $itineraries[] = $itinerary;
        }

        $segmentCount = count($this->segments);
        for ($i = 1; $i < count($this->segments); $i++) {
            $newItineraries = [];
            $prefix = '[Segment:' . $i + 1 . ']';
            $this->flightService->log->debug("{$prefix} Fetching journeys");
            foreach ($itineraries as $index => $itinerary) {
                $prefix = '[Segment:' . $i + 1 . '][Itinerary:' . $index . ']';
                $journeys = $this->getJourneys($itinerary->getItineraryData());
                if (count($journeys) === 0) {
                    $this->flightService->log->debug("{$prefix} No journeys found, removing this itinerary as it can't be completed");
                    continue;
                }
                $this->flightService->log->debug("{$prefix} Found " . count($journeys) . " journeys");
                foreach ($journeys as $journey) {
                    // We found a journey, clone our current trip, add it to our list for the next round.
                    $clone = clone $itinerary;
                    $clone->addJourney($journey);
                    $newItineraries[] = $clone;
                }
            }
            $itineraries = $newItineraries;

            $this->flightService->log->debug("{$prefix} " . count($itineraries) . " trips");
        }

        array_filter($itineraries, function (Itinerary $itinerary) use ($segmentCount) {
            return count($itinerary->journeys) === $segmentCount;
        });
        usort($itineraries, function (Itinerary $itinerary1, Itinerary $itinerary2) {
            switch ($this->sortOrder) {
                case SortOrder::DepartureTime:
                    return $itinerary1->outbound->departure->getTimestamp() <=> $itinerary2->outbound->departure->getTimestamp();
                case SortOrder::Duration:
                    return $itinerary1->outbound->duration <=> $itinerary2->outbound->duration;
                case SortOrder::Price:
                    return $itinerary1->price <=> $itinerary2->price;
                default:
                    if ($itinerary1->outbound->stops !== $itinerary2->outbound->stops) {
                        return $itinerary1->outbound->stops <=> $itinerary2->outbound->stops;
                    }
                    return $itinerary1->price <=> $itinerary2->price;
            }
        });
        return $itineraries;
    }

    /**
     * @param mixed[] $itineraryData
     * @return Journey[]
     * @throws DecoderException
     * @throws DependencyException
     * @throws GoogleException
     * @throws SearchException
     */
    protected function getJourneys(array $itineraryData = []): array
    {
        $request = $this->flightService->createRequest('GET', 'https://www.google.com/travel/flights');

        $uri = $request->getUri()->withQuery(http_build_query([
            'curr' => $this->currency,
            'hl' => $this->language,
            'tfu' => $this->getSortOrder(),
            'tfs' => $this->encode($itineraryData),
        ]));
        $this->flightService->log->debug("GET {$uri}");
        $request = $request->withUri($uri);
        $response = $this->flightService->makeRequest($request);

        return $this->parseResponse($response);
    }

    /**
     * @param string $response
     * @return array<int, mixed>
     * @throws DecoderException
     * @throws GoogleException
     */
    protected function parseResponse(string $response): array
    {
        $data = $this->getDataFromResponse($response);
        return array_map(function (array $data) {
            return $this->parseJourney($data);
        }, $data[3][0] ?? []);
    }

    /**
     * @param array<int, mixed> $data
     * @return Journey
     * @throws DecoderException
     */
    protected function parseJourney(array $data): Journey
    {
        return new Journey($this->flightService)->parse($data);
    }

    /**
     * @param string $response
     * @return array<int, mixed>
     * @throws DecoderException
     * @throws GoogleException
     */
    protected function getDataFromResponse(string $response): array
    {
        $this->flightService->log->debug("Attempting to find script tag in Google response");
        $scriptStart = strpos($response, 'script class="ds:1"');
        if ($scriptStart === false) {
            throw new GoogleException("Unable to find script tag in Google response");
        }
        $start = strpos($response, 'data:[', $scriptStart);
        if ($start === false) {
            throw new GoogleException("Unable to find data start in Google response");
        }
        $start += 5;
        $this->flightService->log->debug("Found data start at {$start}");
        $end = strpos($response, '], sideChannel', $start);
        if ($end === false) {
            throw new GoogleException("Unable to find data end at Google response");
        }
        $end += 1;
        $this->flightService->log->debug("Found data end at {$end}");
        $body = substr($response, $start, $end - $start);
        $json = json_decode($body);
        if ($json === false) {
            throw new DecoderException("Failed to parse JSON response at {$start}: " . json_last_error_msg());
        }
        if (!is_array($json)) {
            $this->flightService->log->debug("JSON response did not decode to an array at {$start}: " . json_last_error_msg());
            throw new DecoderException("JSON response did not decode to an array at {$start}: " . json_last_error_msg());
        }
        $this->flightService->log->debug("Found JSON data at {$start}");
        return $json;
    }
}
