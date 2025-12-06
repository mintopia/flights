<?php

namespace Mintopia\Flights;

use DateInterval;
use DateTimeInterface;
use Mintopia\Flights\Exceptions\DecoderException;
use Mintopia\Flights\Protobuf\FlightSummary;
use Mintopia\Flights\Protobuf\ItineraryData;

class Journey
{
    /**
     * @var array<int, Flight>
     */
    public array $flights = [];
    public int $stops = 0;
    public DateInterval $duration;
    public DateTimeInterface $departure;
    public DateTimeInterface $arrival;

    public int $price = 0;
    public string $currency = '';

    public function __construct()
    {
    }

    public function __clone()
    {
        $this->flights = array_map(function (Flight $flight) {
            return clone $flight;
        }, $this->flights);
    }

    /**
     * @param array<int, mixed> $data
     * @return $this
     * @throws Exceptions\DecoderException
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
            $flight = new Flight($this);
            $flight->parse($flightData, $flightSummary[$i]);
            $this->flights[] = $flight;
        }
        if (empty($this->flights)) {
            throw new DecoderException('Unable to parse flights');
        }

        $this->stops = count($this->flights) - 1;
        $this->departure = $this->flights[0]->departure;
        $this->arrival = end($this->flights)->arrival;
        $this->duration = $this->arrival->diff($this->departure);

        // Get price and currency
        $summary = new Protobuf\ItinerarySummary();
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
            $itin = new ItineraryData();
            $itin->setFlightNumber($flight->number);
            $itin->setFlightCode($flight->airline->code);
            $itin->setDepartureAirport($flight->from->code);
            $itin->setArrivalAirport($flight->to->code);
            $itin->setDepartureDate($flight->departure->format('Y-m-d'));
            $flights[] = $itin;
        }
        return $flights;
    }
}
