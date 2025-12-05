<?php

namespace Mintopia\Flights;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Mintopia\Flights\Exceptions\DecoderException;
use Mintopia\Flights\Protobuf\FlightSummary\Itinerary\Sector\Flight as ProtoFlight;

class Flight
{
    public Airport $from;
    public Airport $to;

    public string $operator;
    public Airline $airline;
    public string $code;
    public string $number;

    public DateTimeInterface $departure;
    public DateTimeInterface $arrival;

    public function __construct(public Journey $trip)
    {
    }

    /**
     * @param array<int, mixed> $data
     * @param ProtoFlight $flight
     * @return $this
     */
    public function parse(array $data, ProtoFlight $flight): self
    {
        $this->from = new Airport($data[3], $data[4]);
        $this->to = new Airport($data[6], $data[5]);
        $this->airline = new Airline($data[22][0], $data[22][3]);
        $this->number = $data[22][1];
        $this->code = $data[22][0] . $data[22][1];

        $departure = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $flight->getDeparture());
        if ($departure === false) {
            throw new DecoderException("Departure date {$flight->getDeparture()} could not be parsed");
        }
        $this->departure = $departure;

        $arrival = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $flight->getArrival());
        if ($arrival === false) {
            throw new DecoderException("Arrival date {$flight->getArrival()} could not be parsed");
        }
        $this->arrival = $arrival;

        $this->operator = $this->airline->name;
        if ($data[2]) {
            $this->operator = $data[2];
        }
        return $this;
    }
}
