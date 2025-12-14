<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace Tests\Unit\Models;

use Mintopia\Flights\Exceptions\FlightException;
use Mintopia\Flights\FlightService;
use Mintopia\Flights\Models\Journey;
use Mintopia\Flights\Protobuf\ItinerarySummary;
use Mintopia\Flights\Support\DateIntervalFormatter;
use Tests\Unit\AbstractTestCase;
use Tests\Unit\Models\Traits\FixturesData;

class JourneyTest extends AbstractTestCase
{
    use FixturesData;

    protected function makeDirectJourney(): Journey
    {
        $payload = $this->getDirectJourneyPayload();
        return new Journey(new FlightService())
            ->parse($payload);
    }

    protected function makeIndirectJourney(): Journey
    {
        $payload = $this->getIndirectJourneyPayload();
        return new Journey(new FlightService())
            ->parse($payload);
    }

    public function testToString(): void
    {
        $journey = $this->makeDirectJourney();
        $this->assertEquals('[Journey]', (string)$journey);
    }
    public function testSimpleParsing(): void
    {
        $journey = $this->makeDirectJourney();
        $this->assertCount(1, $journey->flights);
        $this->assertEquals('BA2662', $journey->flights[0]->code);
        $this->assertEquals(0, $journey->stops);
        $this->assertEquals(8472, $journey->price);
        $this->assertEquals('GBP', $journey->currency);
        $this->assertEquals('PT2H55M', DateIntervalFormatter::format($journey->duration));
        $this->assertEquals('2025-12-14T15:20:00+00:00', $journey->departure->format('c'));
        $this->assertEquals('2025-12-14T18:15:00+00:00', $journey->arrival->format('c'));
    }

    public function testIsMutable(): void
    {
        $journey = new Journey(new FlightService());
        $journey2 = $journey->parse($this->getDirectJourneyPayload());
        $this->assertSame($journey, $journey2);
    }

    public function testIndirectJourney(): void
    {
        $journey = $this->makeIndirectJourney();
        $this->assertCount(2, $journey->flights);
        $this->assertEquals('BA2662', $journey->flights[0]->code);
        $this->assertEquals('FR5451', $journey->flights[1]->code);
        $this->assertEquals(1, $journey->stops);
        $this->assertEquals(21039, $journey->price);
        $this->assertEquals('PT7H40M', DateIntervalFormatter::format($journey->duration));
        $this->assertEquals('2025-12-14T15:20:00+00:00', $journey->departure->format('c'));
        $this->assertEquals('2025-12-14T23:00:00+00:00', $journey->arrival->format('c'));
        $this->assertEquals('LGW', $journey->from->code);
        $this->assertEquals('OPO', $journey->to->code);
    }

    public function testThrowsIfNoFlightsAndUsingFrom(): void
    {
        $this->expectException(FlightException::class);
        $journey = new Journey(new FlightService());
        $from = $journey->from;
    }

    public function testThrowsIfNoFlightsAndUsingTo(): void
    {
        $this->expectException(FlightException::class);
        $journey = new Journey(new FlightService());
        $to = $journey->to;
    }

    public function testCloneWillCloneObjects(): void
    {
        $journey = $this->makeIndirectJourney();
        $clone = clone $journey;
        $this->assertNotSame($journey->flights[0], $clone->flights[0]);
        $this->assertNotSame($journey->flights[1], $clone->flights[1]);
        $this->assertNotSame($journey->departure, $clone->departure);
        $this->assertNotSame($journey->arrival, $clone->arrival);
        $this->assertCount(2, $clone->flights);
    }

    public function testItineraryData(): void
    {
        $journey = $this->makeIndirectJourney();
        $data = $journey->getItineraryData();
        $this->assertCount(2, $data);
        $this->assertEquals('BA', $data[0]->getFlightCode());
        $this->assertEquals('2662', $data[0]->getFlightNumber());
        $this->assertEquals('LGW', $data[0]->getDepartureAirport());
        $this->assertEquals('FAO', $data[0]->getArrivalAirport());

        $this->assertEquals('FR', $data[1]->getFlightCode());
        $this->assertEquals('5451', $data[1]->getFlightNumber());
        $this->assertEquals('FAO', $data[1]->getDepartureAirport());
        $this->assertEquals('OPO', $data[1]->getArrivalAirport());
    }

    public function testFlightSummaryHasWrongNumberOfFlights(): void
    {
        $payload = $this->getIndirectJourneyPayload();
        $payload[8] = '"' . base64_encode($this->getDirectJourneySummaryProtobuf()->serializeToString()) . '"';
        $journey = new Journey(new FlightService());
        $this->expectException(FlightException::class);
        $this->expectExceptionMessage('Unable to parse flight summary');
        $journey->parse($payload);
    }

    public function testFlightSummaryHasWrongEncoding(): void
    {
        $payload = $this->getIndirectJourneyPayload();
        $payload[8] = base64_encode($this->getDirectJourneySummaryProtobuf()->serializeToString());
        $journey = new Journey(new FlightService());

        $this->expectException(FlightException::class);
        $this->expectExceptionMessage('Unable to decode flight summary');
        $journey->parse($payload);
    }


    public function testFlightSummaryHasMoreWrongEncoding(): void
    {
        $payload = $this->getIndirectJourneyPayload();
        $payload[8] = '"' . base64_encode($this->getDirectJourneySummaryProtobuf()->serializeToString());
        $journey = new Journey(new FlightService());

        $this->expectException(FlightException::class);
        $this->expectExceptionMessage('Unable to decode flight summary');
        $journey->parse($payload);
    }


    public function testFlightSummaryHasNoFlights(): void
    {
        $payload = $this->getIndirectJourneyPayload();
        $summary = $this->getDirectJourneySummaryProtobuf();
        $summary->getItinerary()->getSector()->setFlight([]);
        $payload[8] = base64_encode($summary->serializeToString());
        $journey = new Journey(new FlightService());

        $this->expectException(FlightException::class);
        $this->expectExceptionMessage('Unable to decode flight summary');
        $journey->parse($payload);
    }

    public function testNoFlightsInPayload(): void
    {
        $payload = $this->getIndirectJourneyPayload();
        $journey = new Journey(new FlightService());
        $payload[0][2] = [];

        $this->expectException(FlightException::class);
        $this->expectExceptionMessage('Unable to parse flights');
        $journey->parse($payload);
    }

    public function testNoPrice(): void
    {
        $payload = $this->getIndirectJourneyPayload();
        $protobuf = new ItinerarySummary();

        $journey = new Journey(new FlightService());
        $payload[1][1] = base64_encode($protobuf->serializeToString());

        $this->expectException(FlightException::class);
        $this->expectExceptionMessage('Unable to parse flight price');
        $journey->parse($payload);
    }

    public function testToArray(): void
    {
        $journey = $this->makeIndirectJourney();
        $array = $journey->toArray();
        $this->assertEquals([
            'flights' => [
                $journey->flights[0]->toArray(),
                $journey->flights[1]->toArray(),
            ],
            'from' => $journey->from->toArray(),
            'to' => $journey->to->toArray(),
            'departure' => '2025-12-14T15:20:00+00:00',
            'arrival' => '2025-12-14T23:00:00+00:00',
            'duration' => 'PT7H40M',
            'price' => 21039,
            'currency' => 'GBP',
            'stops' => 1,
        ], $array);
    }
}
