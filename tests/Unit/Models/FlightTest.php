<?php /** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace Tests\Unit\Models;

use Mintopia\Flights\FlightService;
use Mintopia\Flights\Models\Airport;
use Mintopia\Flights\Models\Flight;
use Mintopia\Flights\Support\DateIntervalFormatter;
use Tests\Unit\AbstractTestCase;
use Tests\Unit\Models\Traits\FixturesData;

class FlightTest extends AbstractTestCase
{
    use FixturesData;

    protected function makeFlight(): Flight
    {
        $flightService = new FlightService();
        $flight = new Flight($flightService);
        return $flight->parse($this->getFaroFlightPayload(), $this->getFaroFlightProtobuf());
    }
    public function testToStringEmpty(): void
    {
        $flight = new Flight(new FlightService());
        $this->assertEquals('[Flight]', (string)$flight);
    }

    public function testToStringWithCode(): void
    {
        $flight = new Flight(new FlightService());
        $flight->code = 'BA2662';
        $this->assertEquals('[Flight:BA2662]', (string)$flight);
    }

    public function testToStringWithToAirportNoCode(): void
    {
        $flightService = new FlightService();
        $flight = new Flight($flightService);
        $flight->code = 'BA2662';
        $flight->to = new Airport($flightService);
        $this->assertEquals('[Flight:BA2662]', (string)$flight);
    }
    public function testToStringWithToAirportAndCode(): void
    {
        $flightService = new FlightService();
        $flight = new Flight($flightService);
        $flight->code = 'BA2662';
        $flight->to = new Airport($flightService);
        $flight->to->code = 'FAO';
        $this->assertEquals('[Flight:BA2662]', (string)$flight);
    }
    public function testToStringWithFromAirportAndCode(): void
    {
        $flightService = new FlightService();
        $flight = new Flight($flightService);
        $flight->code = 'BA2662';
        $flight->to = new Airport($flightService);
        $flight->to->code = 'LGW';
        $this->assertEquals('[Flight:BA2662]', (string)$flight);
    }
    public function testToStringWithFromAirportNoCode(): void
    {
        $flightService = new FlightService();
        $flight = new Flight($flightService);
        $flight->code = 'BA2662';
        $flight->from = new Airport($flightService);
        $this->assertEquals('[Flight:BA2662]', (string)$flight);
    }

    public function testToStringWithAirports(): void
    {
        $flight = $this->makeFlight();
        $this->assertEquals('[Flight:BA2662] LGW to FAO', (string)$flight);
    }

    public function testToArray(): void
    {
        $flight = $this->makeFlight();
        $array = $flight->toArray();
        $this->assertIsArray($array);
        $this->assertEquals([
            'from' => $flight->from->toArray(),
            'to' => $flight->to->toArray(),
            'code' => 'BA2662',
            'departure' => '2025-12-14T15:20:00+00:00',
            'arrival' => '2025-12-14T18:15:00+00:00',
            'duration' => 'PT2H55M',
            'airline' => $flight->airline->toArray(),
            'operator' => 'BA Euroflyer',
            'number' => '2662',
        ], $array);
    }

    public function testParsingData(): void
    {
        $flight = $this->makeFlight();
        $this->assertEquals('BA2662', $flight->code);
        $this->assertEquals('LGW', $flight->from->code);
        $this->assertEquals('FAO', $flight->to->code);
        $this->assertEquals('2025-12-14T15:20:00+00:00', $flight->departure->format('c'));
        $this->assertEquals('2025-12-14T18:15:00+00:00', $flight->arrival->format('c'));
        $this->assertEquals('PT2H55M', DateIntervalFormatter::format($flight->duration));
        $this->assertEquals('BA', $flight->airline->code);
        $this->assertEquals('British Airways', $flight->airline->name);
        $this->assertEquals('BA Euroflyer', $flight->operator);
    }

    public function testOperatorIsSetToAirlineIfNotSpecified(): void
    {
        $flight = new Flight(new FlightService());
        $payload = $this->getFaroFlightPayload();
        $payload[2] = null;
        $flight->parse($payload, $this->getFaroFlightProtobuf());
        $this->assertEquals('British Airways', $flight->operator);
    }

    protected function testIsMutable(): void
    {
        $flight = new Flight(new FlightService());
        $flight2 = $flight->parse($this->getFaroFlightPayload(), $this->getFaroFlightProtobuf());
        $this->assertSame($flight, $flight2);
    }
}
