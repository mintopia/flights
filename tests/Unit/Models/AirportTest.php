<?php

declare(strict_types=1);

namespace Unit\Models;

use Mintopia\Flights\FlightService;
use Mintopia\Flights\Models\Airport;
use Tests\Unit\AbstractTestCase;

class AirportTest extends AbstractTestCase
{
    public function testToStringWithNoCodeOrName(): void
    {
        $airport = new Airport(new FlightService());
        $this->assertEquals('[Airport]', (string)$airport);
    }

    public function testToStringWithNameButNoCode(): void
    {
        $airport = new Airport(new FlightService());
        $airport->name = 'London Gatwick';
        $this->assertEquals('[Airport] London Gatwick', (string)$airport);
    }

    public function testToStringWithEverything(): void
    {
        $airport = new Airport(new FlightService());
        $airport->name = 'London Gatwick';
        $airport->code = 'LGW';
        $this->assertEquals('[Airport:LGW] London Gatwick', (string)$airport);
    }

    public function testToArray(): void
    {
        $airline = new Airport(new FlightService());
        $airline->code = 'LGW';
        $airline->name = 'London Gatwick';
        $array = $airline->toArray();
        $this->assertIsArray($array);
        $this->assertEquals([
            'code' => 'LGW',
            'name' => 'London Gatwick',
        ], $array);
    }
}
