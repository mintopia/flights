<?php
declare(strict_types=1);
namespace Mintopia\Flights\Models;

use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Mintopia\Flights\Container;
use Mintopia\Flights\Protobuf\Airport;
use Mintopia\Flights\Protobuf\FlightData;

class Segment
{
    public DateTimeInterface $date;

    /**
     * @param Container $objectFactory
     * @param array<int, string> $from
     * @param array<int, string> $to
     * @param string|DateTimeInterface|null $date
     * @param int $maxStops
     * @param null|array<string> $airlines
     * @throws DateMalformedStringException
     */
    public function __construct(protected Container $container, protected array $from, protected array $to, string|DateTimeInterface|null $date = null, public int $maxStops = 0, protected array $airlines = [])
    {
        if ($date === null) {
            $this->date = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        } elseif (is_string($date)) {
            $this->date =  new DateTimeImmutable($date, new DateTimeZone('UTC'));
        } elseif ($date instanceof DateTimeInterface) {
            $this->date = $date;
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
