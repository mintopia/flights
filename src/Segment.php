<?php

namespace Mintopia\Flights;

use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Mintopia\Flights\Protobuf\Airport;
use Mintopia\Flights\Protobuf\FlightData;

class Segment
{
    /**
     * @var array <int, string>
     */
    public array $from = [];

    /**
     * @var array <int, string>
     */
    public array $to = [];

    /**
     * @var array <int, string>
     */
    protected array $airlines = [];

    public DateTimeInterface $date;

    /**
     * @param array<int, string>|string $from
     * @param array<int, string>|string $to
     * @param DateTimeInterface|string|null $date
     * @param int $maxStops
     * @param array<string> $airlines
     * @throws DateMalformedStringException
     */
    public function __construct(array|string $from, array|string $to, DateTimeInterface|string|null $date = null, public int $maxStops = 0, null|string|array $airlines = [])
    {
        if ($airlines === null) {
            $this->airlines = [];
        } elseif (!is_array($airlines)) {
            $this->airlines = [$airlines];
        } else {
            $this->airlines = $airlines;
        }
        if ($date === null) {
            $this->date = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        } elseif (is_string($date)) {
            $this->date =  new DateTimeImmutable($date, new DateTimeZone('UTC'));
        } elseif ($date instanceof DateTimeInterface) {
            $this->date = $date;
        }

        if (is_array($from)) {
            $this->from = $from;
        } else {
            $this->from[] = $from;
        }
        if (is_array($to)) {
            $this->to = $to;
        } else {
            $this->to[] = $to;
        }
    }

    public function encode(): FlightData
    {
        $to = array_map(function ($airportCode) {
            return new Airport()->setAirport($airportCode)->setFlag(-1);
        }, $this->to);
        $from = array_map(function ($airportCode) {
            return new Airport()->setAirport($airportCode)->setFlag(-1);
        }, $this->from);
        $flightData = new FlightData()
            ->setToFlight($to)
            ->setFromFlight($from)
            ->setDate($this->date->format('Y-m-d'))
            ->setMaxStops($this->maxStops);
        if (!empty($this->airlines)) {
            $flightData->setAirlines($this->airlines);
        }
        return $flightData;
    }
}
