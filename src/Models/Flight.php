<?php
declare(strict_types=1);
namespace Mintopia\Flights\Models;

use DateInterval;
use DateTimeInterface;
use Mintopia\Flights\Interfaces\AirlineInterface;
use Mintopia\Flights\Interfaces\AirportInterface;
use Mintopia\Flights\Interfaces\FlightInterface;
use Mintopia\Flights\Protobuf\FlightSummary\Itinerary\Sector\Flight as ProtoFlight;

class Flight extends AbstractModel implements FlightInterface
{
    public AirportInterface $from;
    public AirportInterface $to;

    public string $operator;
    public AirlineInterface $airline;
    public string $code;
    public string $number;

    public DateTimeInterface $departure;
    public DateTimeInterface $arrival;
    public DateInterval $duration;

    /**
     * @param array<int, mixed> $data
     * @param ProtoFlight $flight
     * @return $this
     */
    public function parse(array $data, ProtoFlight $flight): self
    {
        $this->from = $this->container->get(AirportInterface::class, $data[3], $data[4]);
        $this->to = $this->container->get(AirportInterface::class, $data[6], $data[5]);
        $this->airline = $this->container->get(AirlineInterface::class, $data[22][0], $data[22][3]);
        $this->number = $data[22][1];
        $this->code = $data[22][0] . $data[22][1];

        $this->departure = $this->container->get(DateTimeInterface::class, $flight->getDeparture());
        $this->arrival = $this->container->get(DateTimeInterface::class, $flight->getArrival());

        $diff = $this->arrival->diff($this->departure);
        $this->duration = $this->container->get(DateInterval::class, $diff);

        $this->operator = $this->airline->name;
        if ($data[2]) {
            $this->operator = $data[2];
        }
        return $this;
    }

    protected function getModelId(): string
    {
        return $this->code;
    }
}
