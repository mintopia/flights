<?php

declare(strict_types=1);

namespace Mintopia\Flights\Models;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Mintopia\Flights\Protobuf\FlightSummary\Itinerary\Sector\Flight as ProtoFlight;

class Flight extends AbstractModel
{
    public Airport $from;
    public Airport $to;

    public string $operator;
    public Airline $airline;
    public string $code;
    public string $number;

    public DateTimeInterface $departure;
    public DateTimeInterface $arrival;
    public DateInterval $duration;

    /**
     * @param mixed[] $data
     * @param ProtoFlight $flight
     * @return $this
     */
    public function parse(array $data, ProtoFlight $flight): self
    {
        $this->from = new Airport($this->flightService, $data[3], $data[4]);
        $this->to = new Airport($this->flightService, $data[6], $data[5]);
        $this->airline = new Airline($this->flightService, $data[22][0], $data[22][3]);
        $this->number = $data[22][1];
        $this->code = $data[22][0] . $data[22][1];

        $this->departure = new DateTimeImmutable($flight->getDeparture());
        $this->arrival = new DateTimeImmutable($flight->getArrival());

        $this->duration = $this->arrival->diff($this->departure);

        $this->operator = $this->airline->name ?? '';
        if ($data[2] !== null) {
            $this->operator = $data[2];
        }
        return $this;
    }

    protected function getModelId(): string
    {
        return $this->code;
    }
}
