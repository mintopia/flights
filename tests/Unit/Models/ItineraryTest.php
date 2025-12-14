<?php /** @noinspection ALL */

declare(strict_types=1);

namespace Tests\Unit\Models;

use Mintopia\Flights\Exceptions\FlightException;
use Mintopia\Flights\FlightService;
use Mintopia\Flights\Models\Itinerary;
use Mintopia\Flights\Models\Journey;
use Mintopia\Flights\Support\DateIntervalFormatter;
use Tests\Unit\AbstractTestCase;
use Tests\Unit\Models\Traits\FixturesData;

class ItineraryTest extends AbstractTestCase
{
    use FixturesData;

    protected FlightService $flightService;
    protected Journey $faroJourney;
    protected Journey $connectingJourney;
    protected Journey $gatwickJourney;
    protected Journey $portoJourney;

    public function setUp(): void
    {
        parent::setUp();
        $this->flightService = new FlightService();
        $this->faroJourney = new Journey($this->flightService)
            ->parse($this->getDirectJourneyPayload());
        $this->gatwickJourney = new Journey($this->flightService)
            ->parse($this->getGatwickJourneyPayload());
        $this->portoJourney = new Journey($this->flightService)
            ->parse($this->getPortoJourneyPayload());
        $this->connectingJourney = new Journey($this->flightService)
            ->parse($this->getIndirectJourneyPayload());
    }
    public function testToString(): void
    {
        $itinerary = new Itinerary($this->flightService);
        $this->assertEquals('[Itinerary]', (string) $itinerary);
    }

    public function testOutboundNoJourneys(): void
    {
        $this->expectException(FlightException::class);
        $this->expectExceptionMessage('No journey found in itinerary');

        $itinerary = new Itinerary($this->flightService);
        $this->assertCount(0, $itinerary->flights);
        $itinerary->outbound;
    }

    public function testReturnNoJourneys(): void
    {
        $this->expectException(FlightException::class);
        $this->expectExceptionMessage('No journey found in itinerary');

        $itinerary = new Itinerary($this->flightService);
        $this->assertCount(0, $itinerary->flights);
        $itinerary->return;
    }

    public function testIsReturnNoJourneys(): void
    {
        $itinerary = new Itinerary($this->flightService);
        $this->assertFalse($itinerary->isReturn());
    }

    public function testFromNoJourneys(): void
    {
        $this->expectException(FlightException::class);
        $this->expectExceptionMessage('No journey found in itinerary');

        $itinerary = new Itinerary($this->flightService);
        $this->assertCount(0, $itinerary->flights);
        $itinerary->from;
    }

    public function testToNoJourneys(): void
    {
        $this->expectException(FlightException::class);
        $this->expectExceptionMessage('No journey found in itinerary');

        $itinerary = new Itinerary($this->flightService);
        $this->assertCount(0, $itinerary->flights);
        $itinerary->to;
    }

    public function testAddingJourney(): void
    {
        $itinerary = new Itinerary($this->flightService);
        $this->assertCount(0, $itinerary->journeys);
        $this->assertCount(0, $itinerary->flights);
        $itinerary->addJourney($this->faroJourney);

        $this->assertCount(1, $itinerary->journeys);
        $this->assertCount(1, $itinerary->flights);

        $this->assertSame($this->faroJourney, $itinerary->journeys[0]);
        $this->assertSame($this->faroJourney->flights[0], $itinerary->flights[0]);

        $this->assertSame($this->faroJourney, $itinerary->outbound);
        $this->assertSame($this->faroJourney, $itinerary->return);

        $this->assertFalse($itinerary->isReturn());
        $this->assertSame($this->faroJourney->departure, $itinerary->departure);
        $this->assertSame($this->faroJourney->arrival, $itinerary->arrival);

        $this->assertSame($this->faroJourney->from, $itinerary->from);
        $this->assertSame($this->faroJourney->to, $itinerary->to);

        $this->assertEquals($this->faroJourney->price, $itinerary->price);
        $this->assertEquals($this->faroJourney->currency, $itinerary->currency);

        $this->assertEquals($this->faroJourney->duration, $itinerary->duration);
    }

    public function testReturnJourney(): void
    {
        $itinerary = new Itinerary($this->flightService);
        $this->assertCount(0, $itinerary->flights);
        $this->assertCount(0, $itinerary->journeys);

        $itinerary->addJourney($this->faroJourney);
        $this->assertCount(1, $itinerary->flights);
        $this->assertCount(1, $itinerary->journeys);

        $itinerary->addJourney($this->gatwickJourney);
        $this->assertCount(2, $itinerary->flights);
        $this->assertCount(2, $itinerary->journeys);

        $this->assertSame($this->faroJourney, $itinerary->journeys[0]);
        $this->assertSame($this->gatwickJourney, $itinerary->journeys[1]);

        $this->assertTrue($itinerary->isReturn());

        $this->assertSame($this->faroJourney, $itinerary->outbound);
        $this->assertSame($this->gatwickJourney, $itinerary->return);

        $this->assertSame($this->faroJourney->from, $itinerary->from);
        $this->assertSame($this->faroJourney->to, $itinerary->to);

        $this->assertSame($this->faroJourney->departure, $itinerary->departure);
        $this->assertSame($this->gatwickJourney->arrival, $itinerary->arrival);

        $this->assertEquals('P1DT6H30M', DateIntervalFormatter::format($itinerary->duration));

        $this->assertEquals(9832, $itinerary->price);
        $this->assertEquals('GBP', $itinerary->currency);
    }

    public function testClearingJourneys(): void
    {
        $itinerary = new Itinerary($this->flightService);
        $this->assertCount(0, $itinerary->journeys);

        $itinerary->addJourney($this->faroJourney);
        $this->assertCount(1, $itinerary->journeys);

        $itinerary->addJourney($this->gatwickJourney);
        $this->assertCount(2, $itinerary->flights);

        $this->assertEquals(9832, $itinerary->price);

        $itinerary->clearJourneys();
        $this->assertCount(0, $itinerary->journeys);
        $this->assertCount(0, $itinerary->flights);
        $this->assertEquals(0, $itinerary->price);
    }

    public function testClone(): void
    {
        $itinerary = new Itinerary($this->flightService);
        $itinerary->addJourney($this->faroJourney);
        $itinerary->addJourney($this->gatwickJourney);
        $this->assertCount(2, $itinerary->journeys);

        $clone = clone $itinerary;
        $this->assertNotSame($itinerary, $clone);
        $this->assertEquals($itinerary, $clone);

        $this->assertCount(2, $clone->journeys);
        $this->assertNotSame($itinerary->journeys[0], $clone->journeys[0]);
        $this->assertNotSame($itinerary->journeys[1], $clone->journeys[1]);
        $this->assertEquals($itinerary->journeys[0], $clone->journeys[0]);
        $this->assertEquals($itinerary->journeys[1], $clone->journeys[1]);

        $this->assertNotSame($this->faroJourney, $clone->journeys[0]);
        $this->assertNotSame($this->gatwickJourney, $clone->journeys[1]);
        $this->assertEquals($this->faroJourney, $clone->journeys[0]);
        $this->assertEquals($this->gatwickJourney, $clone->journeys[1]);
    }

    public function testIsMutable(): void
    {
        $itinerary = new Itinerary($this->flightService);
        $itinerary->addJourney($this->faroJourney);
        $itinerary->addJourney($this->gatwickJourney);
        $itinerary2 = $itinerary->clearJourneys();
        $this->assertSame($itinerary, $itinerary2);
    }

    public function testGetItineraryData(): void
    {
        $itinerary = new Itinerary($this->flightService);
        $itinerary->addJourney($this->faroJourney);
        $itinerary->addJourney($this->gatwickJourney);

        $data = $itinerary->getItineraryData();
        $this->assertCount(2, $data);
        $this->assertEquals($this->faroJourney->getItineraryData()[0], $data[0]);
        $this->assertEquals($this->gatwickJourney->getItineraryData()[0], $data[1]);
    }

    public function testConnectingFlight(): void
    {
        $itinerary = new Itinerary($this->flightService);
        $this->assertCount(0, $itinerary->flights);
        $this->assertCount(0, $itinerary->journeys);

        $itinerary->addJourney($this->connectingJourney);
        $this->assertCount(2, $itinerary->flights);
        $this->assertCount(1, $itinerary->journeys);

        $this->assertFalse($itinerary->isReturn());

        $this->assertSame($this->connectingJourney, $itinerary->outbound);
        $this->assertSame($this->connectingJourney, $itinerary->return);

        $this->assertSame($this->connectingJourney->from, $itinerary->from);
        $this->assertSame($this->connectingJourney->to, $itinerary->to);

        $this->assertEquals('PT7H40M', DateIntervalFormatter::format($itinerary->duration));
        $this->assertSame($this->connectingJourney->departure, $itinerary->departure);
        $this->assertSame($this->connectingJourney->arrival, $itinerary->arrival);
    }

    public function testMultiCityReturn(): void
    {
        // Testing LGW -> FAO -> OPO, FAO -> LGW
        $itinerary = new Itinerary($this->flightService);
        $itinerary->addJourney($this->connectingJourney);
        $itinerary->addJourney($this->gatwickJourney);

        $this->assertCount(2, $itinerary->journeys);
        $this->assertCount(3, $itinerary->flights);

        // It's not actually a return
        $this->assertFalse($itinerary->isReturn());

        $this->assertSame($this->connectingJourney, $itinerary->outbound);
        $this->assertSame($this->gatwickJourney, $itinerary->return);

        $this->assertSame($this->connectingJourney->from, $itinerary->from);
        $this->assertSame($this->gatwickJourney->to, $itinerary->to);

        $this->assertEquals('P1DT6H30M', DateIntervalFormatter::format($itinerary->duration));
        $this->assertSame($this->connectingJourney->departure, $itinerary->departure);
        $this->assertSame($this->gatwickJourney->arrival, $itinerary->arrival);
    }

    public function testNonReturnFlightUsesTotalPriceOfAllJourneys(): void
    {
        $itinerary = new Itinerary($this->flightService);
        $this->assertEquals(0, $itinerary->price);
        $this->assertEquals('GBP', $itinerary->currency);

        $itinerary->addJourney($this->faroJourney);
        $this->assertFalse($itinerary->isReturn());
        $this->assertEquals(8472, $itinerary->price);
        $this->assertEquals('GBP', $itinerary->currency);

        $itinerary->addJourney($this->portoJourney);
        $this->assertFalse($itinerary->isReturn());
        $this->assertEquals(10971, $itinerary->price);
        $this->assertEquals('GBP', $itinerary->currency);
    }

    public function testReturnFlightUsesHighestPriceOfAllJourneys(): void
    {
        // Including these as a sanity check of our fixtures
        $this->assertEquals(8472, $this->faroJourney->price);
        $this->assertEquals(9832, $this->gatwickJourney->price);
        $this->assertGreaterThan($this->faroJourney->price, $this->gatwickJourney->price);

        $itinerary = new Itinerary($this->flightService);
        $this->assertEquals(0, $itinerary->price);
        $this->assertEquals('GBP', $itinerary->currency);

        $itinerary->addJourney($this->faroJourney);
        $this->assertFalse($itinerary->isReturn());
        $this->assertEquals(8472, $itinerary->price);
        $this->assertEquals('GBP', $itinerary->currency);

        $itinerary->addJourney($this->gatwickJourney);
        $this->assertTrue($itinerary->isReturn());
        $this->assertEquals(9832, $itinerary->price);
        $this->assertEquals('GBP', $itinerary->currency);
    }

    public function testMultiCity(): void
    {
        $itinerary = new Itinerary($this->flightService);
        $itinerary->addJourney($this->faroJourney);
        $itinerary->addJourney($this->portoJourney);

        $this->assertCount(2, $itinerary->journeys);
        $this->assertCount(2, $itinerary->flights);

        $this->assertFalse($itinerary->isReturn());

        $this->assertSame($this->faroJourney, $itinerary->outbound);
        $this->assertSame($this->portoJourney, $itinerary->return);

        $this->assertSame($this->faroJourney->from, $itinerary->from);
        $this->assertSame($this->portoJourney->to, $itinerary->to);

        $this->assertSame($this->faroJourney->departure, $itinerary->departure);
        $this->assertSame($this->portoJourney->arrival, $itinerary->arrival);
        $this->assertEquals('PT7H40M', DateIntervalFormatter::format($itinerary->duration));
    }

    public function testNotes(): void
    {
        $itinerary = new Itinerary($this->flightService);
        $this->assertNull($itinerary->note);

        $itinerary->note = 'Foobar';
        $this->assertEquals('Foobar', $itinerary->note);

        $itinerary->note = null;
        $this->assertNull($itinerary->note);
    }

    public function testToArray(): void
    {
        $itinerary = new Itinerary($this->flightService);
        $itinerary->addJourney($this->faroJourney);
        $itinerary->addJourney($this->gatwickJourney);
        $itinerary->note = 'Return from Gatwick to Faro';

        $array = $itinerary->toArray();
        $this->assertIsArray($array);
        $this->assertEquals([
            'note' => 'Return from Gatwick to Faro',
            'price' => 9832,
            'currency' => 'GBP',
            'departure' => '2025-12-14T15:20:00+00:00',
            'arrival' => '2025-12-15T21:50:00+00:00',
            'duration' => 'P1DT6H30M',
            'from' => $itinerary->from->toArray(),
            'to' => $itinerary->to->toArray(),
            'flights' => [
                $itinerary->flights[0]->toArray(),
                $itinerary->flights[1]->toArray(),
            ],
            'journeys' => [
                $itinerary->journeys[0]->toArray(),
                $itinerary->journeys[1]->toArray(),
            ],
            'outbound' => $itinerary->outbound->toArray(),
            'return' => $itinerary->return->toArray(),
        ], $array);
    }
}
