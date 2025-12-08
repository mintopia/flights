<?php
declare(strict_types=1);
namespace Mintopia\Flights\Models;

use Mintopia\Flights\Container;
use Mintopia\Flights\FlightService;
use Mintopia\Flights\Interfaces\AirportInterface;
use Mintopia\Flights\Interfaces\FlightInterface;
use Mintopia\Flights\Interfaces\ItineraryInterface;
use Mintopia\Flights\Interfaces\JourneyInterface;
use Mintopia\Flights\Protobuf\ItineraryData;

class Itinerary extends AbstractModel implements ItineraryInterface
{
    public ?string $note = null;
    public int $price = 0;
    public ?string $currency = null;

    /**
     * @var iterable<int, Journey>
     */
    public iterable $journeys;

    // phpcs:disable
    public ?JourneyInterface $outbound {
        get {
            return $this->journeys[0] ?? null;
        }
    }

    /**
     * @var JourneyInterface|null
     */
    public ?JourneyInterface $return {
        get {
            $index = count($this->journeys) - 1;
            return $this->journeys[$index] ?? null;
        }
    }

    public ?AirportInterface $from {
        get {
            return $this->outbound?->from;
        }
    }

    public ?AirportInterface $to {
        get {
            if ($this->isReturn()) {
                return $this->outbound?->to;
            } else {
                return $this->return?->to;
            }
        }
    }


    /**
     * @var iterable<int, FlightInterface>
     */
    public iterable $flights {
        get {
            $flights = [];
            foreach ($this->journeys as $journey) {
                foreach ($journey->flights as $flight) {
                    $flights[] = $flight;
                }
            }
            return $this->container->get(
                'iterable',
                $flights
            );
        }
    }
    // phpcs:enable

    public function __construct(protected Container $container)
    {
        parent::__construct($container);
        $this->initialiseIterables([
            'journeys',
        ]);
    }

    public function isReturn(): bool
    {
        if (count($this->journeys) !== 2) {
            return false;
        }
        if ($this->journeys[0]->from !== $this->journeys[1]->to) {
            return false;
        }
        if ($this->journeys[1]->to != $this->journeys[0]->from) {
            return false;
        }
        return true;
    }

    public function addJourney(JourneyInterface $journey): self
    {
        $this->journeys[] = $journey;
        $journeys = iterator_to_array($this->journeys);
        usort($journeys, function (JourneyInterface $a, JourneyInterface $b) {
            return $a->departure <=> $b->departure;
        });
        $this->journeys = $this->container->get('iterable', $journeys);
        $this->updatePrice();
        return $this;
    }

    public function clearJourneys(): self
    {
        $this->journeys = $this->container->get('iterable', []);
        $this->updatePrice();
        return $this;
    }

    protected function updatePrice(): void
    {
        if (count($this->journeys) === 0) {
            $this->price = 0;
            $this->currency = $this->container->get(FlightService::class)->getCurrency();
            return;
        }
        $this->currency = $this->journeys[0]->currency ?? null;
        $this->price = 0;

        $prices = [];
        foreach ($this->journeys as $journey) {
            $prices[] = $journey->price;
        }

        if ($this->isReturn()) {
            $this->price = max($prices);
        } else {
            $this->price = array_sum($prices);
        }
    }

    /**
     * @return array<int, ItineraryData>
     */
    public function getItineraryData(): array
    {
        $itineraryData = [];
        foreach ($this->journeys as $journey) {
            $itineraryData = array_merge($itineraryData, $journey->getItineraryData());
        }
        return $itineraryData;
    }

    public function __clone(): void
    {
        parent::__clone();
        $this->cloneIterables([
            'journeys',
        ]);
    }
}
