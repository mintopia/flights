<?php

declare(strict_types=1);

namespace Mintopia\Flights\Models;

use DateTimeInterface;
use Mintopia\Flights\Protobuf\Airport;
use Mintopia\Flights\Protobuf\FlightData;

class Segment
{
    /**
     * @param string[] $from
     * @param string[] $to
     * @param DateTimeInterface $date
     * @param int $maxStops
     * @param string[]|null $airlines
     */
    public function __construct(protected array $from, protected array $to, protected DateTimeInterface $date, protected int $maxStops = 0, protected ?array $airlines = null)
    {
    }

    public function encode(): FlightData
    {
        $to = array_map(function (string $code) {
            return new Airport()->setAirport($code)->setFlag(-1);
        }, $this->to);

        $from = array_map(function (string $code) {
            return new Airport()->setAirport($code)->setFlag(-1);
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
