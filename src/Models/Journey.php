<?php

declare(strict_types=1);

namespace Mintopia\Flights\Models;

use DateInterval;
use DateTimeInterface;
use Mintopia\Flights\Exceptions\DecoderException;
use Mintopia\Flights\Exceptions\FlightException;
use Mintopia\Flights\Protobuf\FlightSummary;
use Mintopia\Flights\Protobuf\ItineraryData;
use Mintopia\Flights\Protobuf\ItinerarySummary;

class Journey extends AbstractModel
{
    /**
     * @var Flight[]
     */
    public array $flights = [];

    // phpcs:disable
    public Airport $from {
        get {
            if (empty($this->flights)) {
                throw new FlightException('No flights specified for journey');
            }
            return $this->flights[0]->to;
        }
    }
    public Airport $to {
        get {
            if (empty($this->flights)) {
                throw new FlightException('No flights specified for journey');
            }
            return end($this->flights)->from;
        }
    }
    // phpcs:enable
    public int $stops = 0;
    public DateInterval $duration;
    public DateTimeInterface $departure;
    public DateTimeInterface $arrival;

    public int $price = 0;
    public string $currency = '';

    public function __clone(): void
    {
        $this->cloneArrays([
            'flights',
        ]);
        $this->departure = clone $this->departure;
        $this->arrival = clone $this->arrival;
    }

    /**
     * @param mixed[] $data
     * @return $this
     * @throws DecoderException
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
            $flight = new Flight($this->flightService);
            $flight->parse($flightData, $flightSummary[$i]);
            $this->flights[] = $flight;
        }
        if (empty($this->flights)) {
            throw new DecoderException('Unable to parse flights');
        }

        $this->stops = count($this->flights) - 1;
        $this->departure = $this->flights[0]->departure;
        $this->arrival = $this->flights[$this->stops]->arrival;
        $this->duration = $this->arrival->diff($this->departure);

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
     * @return ItineraryData[]
     */
    public function getItineraryData(): array
    {
        $flights = [];
        foreach ($this->flights as $flight) {
            $itineraryDate = new ItineraryData();
            $itineraryDate->setFlightNumber($flight->number);
            $itineraryDate->setFlightCode($flight->airline->code ?? '');
            $itineraryDate->setDepartureAirport($flight->from->code ?? '');
            $itineraryDate->setArrivalAirport($flight->to->code ?? '');
            $itineraryDate->setDepartureDate($flight->departure->format('Y-m-d'));
            $flights[] = $itineraryDate;
        }
        return $flights;
    }
}
