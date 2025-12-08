<?php
declare(strict_types=1);
namespace Mintopia\Flights\Models;

use DateInterval;
use DateTimeInterface;
use Mintopia\Flights\Exceptions\DecoderException;
use Mintopia\Flights\Interfaces\AirportInterface;
use Mintopia\Flights\Interfaces\FlightInterface;
use Mintopia\Flights\Interfaces\JourneyInterface;
use Mintopia\Flights\Protobuf\FlightSummary;
use Mintopia\Flights\Protobuf\ItineraryData;
use Mintopia\Flights\Protobuf\ItinerarySummary;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class Journey extends AbstractModel implements JourneyInterface
{
    /**
     * @var iterable<int, FlightInterface>
     */
    public iterable $flights = [];

    public ?AirportInterface $from = null;
    public ?AirportInterface $to = null;
    public int $stops = 0;
    public DateInterval $duration;
    public DateTimeInterface $departure;
    public DateTimeInterface $arrival;

    public int $price = 0;
    public string $currency = '';

    public function __clone(): void
    {
        parent::__clone();
        $this->cloneIterables([
            'flights',
        ]);
        $this->departure = clone $this->departure;
        $this->arrival = clone $this->arrival;
    }

    /**
     * @param array<int, mixed> $data
     * @return $this
     * @throws DecoderException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function parse(array $data): self
    {
        $summary = new FlightSummary();
        $summaryData = $this->getSummaryData($data[8] ?? '');
        $summary->mergeFromString(base64_decode($summaryData));
        $flightSummary = $summary->getItinerary()?->getSector()?->getFlight();
        if ($flightSummary === null || count($flightSummary) < count($data[0][2])) {
            throw new DecoderException('Unable to parse flight summary');
        }
        foreach ($data[0][2] as $i => $flightData) {
            $flight = $this->container->get(FlightInterface::class);
            $flight->parse($flightData, $flightSummary[$i]);
            $this->flights[] = $flight;
        }
        if (empty($this->flights)) {
            throw new DecoderException('Unable to parse flights');
        }

        $this->stops = count($this->flights) - 1;
        $this->departure = $this->flights[0]->departure;
        $this->arrival = $this->flights[$this->stops]->arrival;

        $diff = $this->arrival->diff($this->departure);
        $this->duration = $this->container->get(DateInterval::class, $diff);

        // Get price and currency
        $summary = new ItinerarySummary();
        $summary->mergeFromString(base64_decode($data[1][1]));
        $price = $summary->getPrice();
        if ($price === null) {
            throw new DecoderException('Unable to parse flight price');
        }
        $this->price = $price->getPrice();
        $this->currency = $price->getCurrency();

        return $this;
    }

    protected function getSummaryData(string $data): string
    {
        $start = strpos($data, '"');
        if ($start === false) {
            throw new DecoderException('Unable to decode flight summary');
        }
        $start++;
        $end = strpos($data, '"', $start);
        if ($end === false) {
            throw new DecoderException('Unable to decode flight summary');
        }
        return str_replace('\u003d', '=', substr($data, $start, $end - $start));
    }

    /**
     * @return array<int, ItineraryData>
     */
    public function getItineraryData(): array
    {
        $flights = [];
        foreach ($this->flights as $flight) {
            $itineraryDate = new ItineraryData();
            $itineraryDate->setFlightNumber($flight->number);
            $itineraryDate->setFlightCode($flight->airline->code);
            $itineraryDate->setDepartureAirport($flight->from->code);
            $itineraryDate->setArrivalAirport($flight->to->code);
            $itineraryDate->setDepartureDate($flight->departure->format('Y-m-d'));
            $flights[] = $itineraryDate;
        }
        return $flights;
    }
}
