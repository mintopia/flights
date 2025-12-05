<?php

namespace Mintopia\Flights;

use Mintopia\Flights\Protobuf\ItineraryData;

class Itinerary
{
    public ?string $note = null;
    public int $price = 0;
    public ?string $currency = null;

    /**
     * @var array<int, Journey>
     */
    public array $journeys = [];

    // phpcs:disable
    public Journey $outbound {
        get {
            return $this->journeys[0];
        }
    }

    /**
     * @var Journey|null
     */
    public ?Journey $return {
        get {
            $journey = end($this->journeys);
            if ($journey instanceof Journey) {
                return $journey;
            }
            return null;
        }
    }

    /**
     * @var array<int, Flight>
     */
    public array $flights {
        get {
            return array_reduce($this->journeys, function (array $carry, Journey $journey) {
                return array_merge($carry, $journey->flights);
            }, []);
        }
    }
    // phpcs:enable

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
        $this->price = 0;
        $this->currency = null;
        foreach ($this->journeys as $journey) {
            if ($this->price >= $journey->price) {
                continue;
            }
            $this->price = $journey->price;
            $this->currency = $journey->currency;
        }
    }

    /**
     * @return array<int, ItineraryData>
     */
    public function getItineraryData(): array
    {
        $itinData = [];
        foreach ($this->journeys as $journey) {
            $itinData = array_merge($itinData, $journey->getItineraryData());
        }
        return $itinData;
    }

    public function __clone()
    {
        $this->journeys = array_map(function (Journey $journey) {
            return clone $journey;
        }, $this->journeys);
    }
}
