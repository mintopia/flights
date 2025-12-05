<?php

namespace Mintopia\Flights;

use DateMalformedStringException;
use DateTimeInterface;
use Mintopia\Flights\Enums\BookingClass;
use Mintopia\Flights\Enums\PassengerType;
use Mintopia\Flights\Enums\SortOrder;
use Mintopia\Flights\Exceptions\DecoderException;
use Mintopia\Flights\Exceptions\FlightException;
use Mintopia\Flights\Exceptions\GoogleException;
use Mintopia\Flights\Exceptions\SearchException;
use Mintopia\Flights\Protobuf\Info;
use Mintopia\Flights\Protobuf\ItineraryData;
use Mintopia\Flights\Protobuf\Seat;
use Mintopia\Flights\Protobuf\Trip;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Search implements LoggerAwareInterface
{
    public string $userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:145.0) Gecko/20100101 Firefox/145.0';
    /**
     * @var array<int, PassengerType>
     */
    protected array $passengers = [];
    public string $currency = 'GBP';
    public string $language = 'en-GB';
    public SortOrder $sortOrder = SortOrder::Price;
    public BookingClass $bookingClass = BookingClass::Economy;

    /**
     * @var array <int, Segment>
     */
    public array $segments = [];

    protected LoggerInterface $log;

    public function __construct(protected ClientInterface $httpClient, protected RequestFactoryInterface $requestFactory, ?LoggerInterface $logger = null)
    {
        if ($logger === null) {
            $logger = new NullLogger();
        }
        $this->log = $logger;
        if (!class_exists('Google\Protobuf\Internal\Message')) {
            $this->log->error('Unable to find Google\Protobuf\Internal\Message class');
            throw new FlightException('Please install the ext-protobuf extension or google/protobuf library');
        }
    }

    public function addPassenger(PassengerType $passenger): self
    {
        $this->passengers[] = $passenger;
        return $this;
    }

    /**
     * @param array<PassengerType> $passengers
     * @return $this
     */
    public function setPassengers(array $passengers): self
    {
        $this->passengers = $passengers;
        return $this;
    }

    public function clearPassengers(): self
    {
        $this->passengers = [];
        return $this;
    }

    /**
     * @param string|array<int, string> $from
     * @param string|array<int, string> $to
     * @param string|DateTimeInterface|null $date
     * @param int $maxStops
     * @param array<int, string>|null $airlines
     * @return $this
     * @throws DateMalformedStringException
     */
    public function addSegment(string|array $from, string|array $to, string|DateTimeInterface|null $date = null, int $maxStops = 0, ?array $airlines = []): self
    {
        $journey = new Segment(...func_get_args());
        $this->segments[] = $journey;
        return $this;
    }

    public function clearSegments(): self
    {
        $this->segments = [];
        return $this;
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
     * @param array<int, ItineraryData>|null $itineraryData
     * @return string
     * @throws SearchException
     */
    protected function encode(?array $itineraryData = null): string
    {
        $segmentCount = count($this->segments);
        if ($segmentCount === 0) {
            throw new SearchException('No segments specified');
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
            $segmentCount === 1 => Trip::ONE_WAY,
            $segmentCount === 2 => Trip::ROUND_TRIP,
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
     * @return array<int, Itinerary>
     */
    public function getItineraries(): array
    {
        $itineraries = [];
        $this->log->debug("[Segment:1] Fetching journeys");

        // Get our first journeys
        $journeys = $this->getJourneys();
        $this->log->debug("[Segment:1] Found " . count($journeys) . " journeys");
        foreach ($journeys as $journey) {
            $itinerary = new Itinerary();
            $itinerary->addJourney($journey);
            $itineraries[] = $itinerary;
        }

        $segmentCount = count($this->segments);
        for ($i = 1; $i < count($this->segments); $i++) {
            $newItineraries = [];
            $prefix = '[Segment:' . $i + 1 . ']';
            $this->log->debug("{$prefix} Fetching journeys");
            foreach ($itineraries as $index => $itinerary) {
                $prefix = '[Segment:' . $i + 1 . '][Itinerary:' . $index . ']';
                $journeys = $this->getJourneys($itinerary->getItineraryData());
                if (count($journeys) === 0) {
                    $this->log->debug("{$prefix} No journeys found, removing this itinerary as it can't be completed");
                    continue;
                }
                $this->log->debug("{$prefix} Found " . count($journeys) . " journeys");
                foreach ($journeys as $journey) {
                    // We found a journey, clone our current trip, add it to our list for the next round.
                    $clone = clone $itinerary;
                    $clone->addJourney($journey);
                    $newItineraries[] = $clone;
                }
            }
            $itineraries = $newItineraries;

            $this->log->debug("{$prefix} " . count($itineraries) . " trips");
        }

        array_filter($itineraries, function (Itinerary $itinerary) use ($segmentCount) {
            return count($itinerary->journeys) === $segmentCount;
        });
        usort($itineraries, function (Itinerary $itinerary1, Itinerary $itinerary2) {
            switch ($this->sortOrder) {
                case SortOrder::DepartureTime:
                    return $itinerary1->outbound->departure <=> $itinerary2->outbound->departure;
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
     * @param array<int, mixed> $itineraryData
     * @return array<int, Journey>
     * @throws GoogleException
     * @throws SearchException
     */
    protected function getJourneys(array $itineraryData = []): array
    {
        $request = $this->requestFactory
            ->createRequest('GET', 'https://www.google.com/travel/flights');

        $uri = $request->getUri()->withQuery(http_build_query([
            'curr' => $this->currency,
            'hl' => $this->language,
            'tfu' => $this->getSortOrder(),
            'tfs' => $this->encode($itineraryData),
        ]));
        $this->log->debug("GET {$uri}");
        $request = $request->withUri($uri);
        $response = $this->makeRequest($request);

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
        $journey = new Journey();
        return $journey->parse($data);
    }

    /**
     * @param string $response
     * @return array<int, mixed>
     * @throws DecoderException
     * @throws GoogleException
     */
    protected function getDataFromResponse(string $response): array
    {
        $this->log->debug("Attempting to find script tag in Google response");
        $scriptStart = strpos($response, 'script class="ds:1"');
        if ($scriptStart === false) {
            throw new GoogleException("Unable to find script tag in Google response");
        }
        $start = strpos($response, 'data:[', $scriptStart);
        if ($start === false) {
            throw new GoogleException("Unable to find data start in Google response");
        }
        $start += 5;
        $this->log->debug("Found data start at {$start}");
        $end = strpos($response, '], sideChannel', $start);
        if ($end === false) {
            throw new GoogleException("Unable to find data end at Google response");
        }
        $end += 1;
        $this->log->debug("Found data end at {$end}");
        $body = substr($response, $start, $end - $start);
        $json = json_decode($body);
        if ($json === false) {
            throw new DecoderException("Failed to parse JSON response at {$start}: " . json_last_error_msg());
        }
        if (!is_array($json)) {
            $this->log->debug("JSON response did not decode to an array at {$start}: " . json_last_error_msg());
            throw new DecoderException("JSON response did not decode to an array at {$start}: " . json_last_error_msg());
        }
        $this->log->debug("Found JSON data at {$start}");
        return $json;
    }

    protected function makeRequest(RequestInterface $request): string
    {
        $request = $request
            ->withHeader('User-Agent', $this->userAgent)
            ->withHeader('Cookie', $this->getCookies());

        $response = $this->httpClient->sendRequest($request);
        if ($response->getStatusCode() !== 200) {
            $ex = new GoogleException($response->getReasonPhrase(), $response->getStatusCode());
            $ex->request = $request;
            $ex->response = $response;
            throw $ex;
        }
        return $response->getBody()->getContents();
    }

    protected function getCookies(): string
    {
        $cookies = [
            'SOCS' => 'CAISNQgjEitib3FfaWRlbnRpdHlmcm9udGVuZHVpc2VydmVyXzIwMjUwNDIzLjA0X3AwGgJ1ayACGgYIgP6lwAY',
            'OTZ' => '8053484_44_48_123900_44_436380',
            'NID' => '8053484_44_48_123900_44_436380',
        ];
        $output = '';
        foreach ($cookies as $name => $value) {
            $output .= "{$name}={$value}; ";
        }
        return trim($output);
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->log = $logger;
    }
}
