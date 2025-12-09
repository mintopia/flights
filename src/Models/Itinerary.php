<?php

declare(strict_types=1);

namespace Mintopia\Flights\Models;

use Mintopia\Flights\Exceptions\FlightException;
use Mintopia\Flights\Protobuf\ItineraryData;

class Itinerary extends AbstractModel
{
    public ?string $note = null;
    public int $price = 0;
    public ?string $currency = null;

    /**
     * @var Journey[]
     */
    public array $journeys = [];

    // phpcs:disable
    public Journey $outbound {
        get {
            if (empty($this->journeys)) {
                throw new FlightException('No journey found in iterinary');
            }
            return $this->journeys[0];
        }
    }

    public Journey $return {
        get {
            if (empty($this->journeys)) {
                throw new FlightException('No journey found in iterinary');
            }
            return end($this->journeys);
        }
    }

    public Airport $from {
        get {
            return $this->outbound->from;
        }
    }

    public Airport $to {
        get {
            if ($this->isReturn()) {
                return $this->outbound->to;
            }
            return $this->return->to;
        }
    }

    /**
     * @var Flight[]
     */
    public array $flights {
        get {
            return array_reduce($this->journeys, function (array $carry, Journey $journey) {
                return array_merge($carry, $journey->flights);
            }, []);
        }
    }
    // phpcs:enable

    public function isReturn(): bool
    {
        if (count($this->journeys) !== 2) {
            return false;
        }
        if ($this->journeys[0]->from !== $this->journeys[1]->to) {
            return false;
        }
        if ($this->journeys[1]->to != $$this->journeys[0]->from) {
            return false;
        }
        return true;
    }

    public function addJourney(Journey $journey): self
    {
        $this->journeys[] = $journey;
        usort($this->journeys, function (Journey $a, Journey $b) {
            return $a->departure <=> $b->departure;
        });
        $this->updatePrice();
        return $this;
    }

    public function clearJourneys(): self
    {
        $this->journeys = [];
        $this->updatePrice();
        return $this;
    }

    protected function updatePrice(): void
    {
        if (empty($this->journeys)) {
            $this->price = 0;
            $this->currency = $this->flightService->getCurrency();
            return;
        }
        $this->currency = $this->journeys[0]->currency;
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
     * @return ItineraryData[]
     */
    public function getItineraryData(): array
    {
        return array_reduce($this->journeys, function (array $carry, Journey $journey) {
            return array_merge($carry, $journey->getItineraryData());
        }, []);
    }

    public function __clone(): void
    {
        $this->cloneArrays([
            'journeys',
        ]);
    }
}
