<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace Tests\Unit\Models\Traits;

use Mintopia\Flights\Protobuf\FlightSummary;
use Mintopia\Flights\Protobuf\FlightSummary\Itinerary\Sector;
use Mintopia\Flights\Protobuf\FlightSummary\Itinerary\Sector\Flight;
use Mintopia\Flights\Protobuf\ItinerarySummary;
use Mintopia\Flights\Protobuf\Price;

trait FixturesData
{
    protected function getDirectJourneySummaryProtobuf(): FlightSummary
    {
        return new FlightSummary()
            ->setItinerary(new FlightSummary\Itinerary()
                ->setSector(new Sector()
                    ->setFlight([
                        new Flight()
                            ->setArrival('2025-12-14 18:15:00')
                            ->setDeparture('2025-12-14 15:20:00')
                    ]),));
    }

    protected function getGatwickJourneySummaryProtobuf(): FlightSummary
    {
        return new FlightSummary()
            ->setItinerary(new FlightSummary\Itinerary()
                ->setSector(new Sector()
                    ->setFlight([
                        new Flight()
                            ->setDeparture('2025-12-15 19:05:00')
                            ->setArrival('2025-12-15 21:50:00')
                    ]),));
    }


    protected function getPortoJourneySummaryProtobuf(): FlightSummary
    {
        return new FlightSummary()
            ->setItinerary(new FlightSummary\Itinerary()
                ->setSector(new Sector()
                    ->setFlight([
                        new Flight()
                            ->setDeparture('2025-12-14 21:45:00')
                            ->setArrival('2025-12-14 23:00:00'),
                    ]),));
    }

    protected function getIndirectJourneySummaryProtobuf(): FlightSummary
    {
        return new FlightSummary()
            ->setItinerary(new FlightSummary\Itinerary()
                ->setSector(new Sector()
                    ->setFlight([
                        new Flight()
                            ->setDeparture('2025-12-14 15:20:00')
                            ->setArrival('2025-12-14 18:15:00'),
                        new Flight()
                            ->setDeparture('2025-12-14 21:45:00')
                            ->setArrival('2025-12-14 23:00:00'),
                    ]),));
    }
    protected function getDirectJourneyPayload(): array
    {
        return [
            0 => [
                2 => [
                    $this->getFaroFlightPayload(),
                ],
            ],
            1 => [
                1 => base64_encode($this->getDirectFlightItinerarySummary()->serializeToString()),
            ],
            8 => '"' . base64_encode($this->getDirectJourneySummaryProtobuf()->serializeToString()) . '\u003d\u003d"',
        ];
    }


    protected function getGatwickJourneyPayload(): array
    {
        return [
            0 => [
                2 => [
                    $this->getGatwickFlightPayload(),
                ],
            ],
            1 => [
                1 => base64_encode($this->getGatwickFlightItinerarySummary()->serializeToString()),
            ],
            8 => '"' . base64_encode($this->getGatwickJourneySummaryProtobuf()->serializeToString()) . '\u003d\u003d"',
        ];
    }

    protected function getPortoJourneyPayload(): array
    {
        return [
            0 => [
                2 => [
                    $this->getPortoFlightPayload(),
                ],
            ],
            1 => [
                1 => base64_encode($this->getPortoFlightIntinerarySummary()->serializeToString()),
            ],
            8 => '"' . base64_encode($this->getPortoJourneySummaryProtobuf()->serializeToString()) . '\u003d\u003d"',
        ];
    }

    protected function getIndirectJourneyPayload(): array
    {
        return [
            0 => [
                2 => [
                    $this->getFaroFlightPayload(),
                    $this->getPortoFlightPayload(),
                ],
            ],
            1 => [
                1 => base64_encode($this->getIndirectFlightItinerarySummary()->serializeToString()),
            ],
            8 => '"' . base64_encode($this->getIndirectJourneySummaryProtobuf()->serializeToString()) . '\u003d\u003d"',
        ];
    }

    protected function getDirectFlightItinerarySummary(): ItinerarySummary
    {
        return new ItinerarySummary()
            ->setPrice(new Price()
                ->setPrice(8472)
                ->setCurrency('GBP'));
    }

    protected function getGatwickFlightItinerarySummary(): ItinerarySummary
    {
        return new ItinerarySummary()
            ->setPrice(new Price()
                ->setPrice(9832)
                ->setCurrency('GBP'));
    }

    protected function getIndirectFlightItinerarySummary(): ItinerarySummary
    {
        return new ItinerarySummary()
            ->setPrice(new Price()
                ->setPrice(21039)
                ->setCurrency('GBP'));
    }

    protected function getPortoFlightIntinerarySummary(): ItinerarySummary
    {
        return new ItinerarySummary()
            ->setPrice(new Price()
                ->setPrice(2499)
                ->setCurrency('GBP'));
    }

    protected function getFaroFlightPayload(): array
    {
        return [
            2 => 'BA Euroflyer',
            3 => 'London Gatwick',
            4 => 'LGW',
            5 => 'Faro',
            6 => 'FAO',
            22 => [
                0 => 'BA',
                1 => '2662',
                3 => 'British Airways',
            ],
        ];
    }


    protected function getGatwickFlightPayload(): array
    {
        return [
            2 => 'BA Euroflyer',
            3 => 'Faro',
            4 => 'FAO',
            5 => 'London Gatwick',
            6 => 'LGW',
            22 => [
                0 => 'BA',
                1 => '2663',
                3 => 'British Airways',
            ],
        ];
    }


    protected function getPortoFlightPayload(): array
    {
        return [
            2 => null,
            3 => 'Faro',
            4 => 'FAO',
            5 => 'Porto',
            6 => 'OPO',
            22 => [
                0 => 'FR',
                1 => '5451',
                3 => 'Ryanair',
            ],
        ];
    }

    protected function getFaroFlightProtobuf(): Flight
    {
        $protobuf = new Flight();
        $protobuf->setNumber(2662);
        $protobuf->setDeparture('2025-12-14T15:20:00+00:00');
        $protobuf->setArrival('2025-12-14T18:15:00+00:00');
        return $protobuf;
    }

    protected function getGatwickFlightProtobuf(): Flight
    {
        return new Flight()
            ->setNumber(2663)
            ->setDeparture('2025-12-15T19:05:00+00:00')
            ->setArrival('2025-12-15T21:50:00+00:00');
    }
}
