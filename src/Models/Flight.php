<?php

declare(strict_types=1);

namespace Mintopia\Flights\Models;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Mintopia\Flights\Protobuf\FlightSummary\Itinerary\Sector\Flight as ProtoFlight;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class Flight extends AbstractModel
{
    public Airport $from;
    public Airport $to;

    public string $operator;
    public Airline $airline;
    public string $code;
    public string $number;

    // phpcs:disable
    public DateInterval $duration {
        get {
            return $this->departure->diff($this->arrival);
        }
    }
    // phpcs:enable

    public DateTimeInterface $departure;
    public DateTimeInterface $arrival;

    /**
     * @param array $data
     * @param ProtoFlight $flight
     * @return $this
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \DateMalformedStringException
     */
    public function parse(array $data, ProtoFlight $flight): self
    {
        $this->from = $this->flightService->container->get(Airport::class);
        $this->from->code = $data[4];
        $this->from->name = $data[3];

        $this->to = $this->flightService->container->get(Airport::class);
        $this->to->code = $data[6];
        $this->to->name = $data[5];

        $this->airline = $this->flightService->container->get(Airline::class);
        $this->airline->code = $data[22][0];
        $this->airline->name = $data[22][3];

        $this->number = $data[22][1];
        $this->code = $data[22][0] . $data[22][1];

        $this->departure = new DateTimeImmutable($flight->getDeparture());
        $this->arrival = new DateTimeImmutable($flight->getArrival());

        $this->operator = $this->airline->name;
        if ($data[2] !== null) {
            $this->operator = $data[2];
        }
        return $this;
    }

    protected function getModelId(): string
    {
        return $this->code ?? parent::getModelId();
    }

    protected function getModelDescription(): string
    {
        if (!isset($this->from->code) || !isset($this->to->code)) {
            return parent::getModelDescription();
        }
        return "{$this->from->code} to {$this->to->code}";
    }
}
