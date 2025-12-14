<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace Tests\Unit\Models;

use Mintopia\Flights\FlightService;
use Mintopia\Flights\Models\Airline;
use Tests\Unit\AbstractTestCase;

class AirlineTest extends AbstractTestCase
{
    public function testToStringWithNoCodeOrName(): void
    {
        $airline = new Airline(new FlightService());
        $this->assertEquals('[Airline]', (string)$airline);
    }

    public function testToStringWithNameButNoCode(): void
    {
        $airline = new Airline(new FlightService());
        $airline->name = 'British Airways';
        $this->assertEquals('[Airline] British Airways', (string)$airline);
    }

    public function testToStringWithEverything(): void
    {
        $airline = new Airline(new FlightService());
        $airline->name = 'British Airways';
        $airline->code = 'BA';
        $this->assertEquals('[Airline:BA] British Airways', (string)$airline);
    }

    public function testToArray(): void
    {
        $airline = new Airline(new FlightService());
        $airline->code = 'BA';
        $airline->name = 'British Airways';
        $array = $airline->toArray();
        $this->assertIsArray($array);
        $this->assertEquals([
            'code' => 'BA',
            'name' => 'British Airways',
        ], $array);
    }
}
