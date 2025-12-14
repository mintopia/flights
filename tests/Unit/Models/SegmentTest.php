<?php /** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace Tests\Unit\Models;

use Mintopia\Flights\Models\Segment;
use Tests\Unit\AbstractTestCase;

class SegmentTest extends AbstractTestCase
{
    public function testEncodingMinimum(): void
    {
        $segment = new Segment(['LGW'], ['FAO'], $this->frozenClock->now());
        $encoded = $segment->encode();

        $this->assertEquals(0, $encoded->getMaxStops());
        $this->assertEquals(0, $encoded->getAirlines()->count());
        $this->assertEquals('2025-12-14', $encoded->getDate());
        $this->assertEquals(1, $encoded->getFromFlight()->count());
        $this->assertEquals(1, $encoded->getToFlight()->count());
        $this->assertEquals('LGW', $encoded->getFromFlight()[0]->getAirport());
        $this->assertEquals('FAO', $encoded->getToFlight()[0]->getAirport());
    }

    public function testMaxStops(): void
    {
        $segment = new Segment(['LGW'], ['FAO'], $this->frozenClock->now(), 2);
        $encoded = $segment->encode();
        $this->assertEquals(2, $encoded->getMaxStops());
    }

    public function testAirlines(): void
    {
        $segment = new Segment(['LGW'], ['FAO'], $this->frozenClock->now(), 0, ['BA']);
        $encoded = $segment->encode();
        $this->assertEquals(1, $encoded->getAirlines()->count());
        $this->assertEquals('BA', $encoded->getAirlines()[0]);
    }

    public function testMultipleAirlines(): void
    {
        $segment = new Segment(['LGW'], ['FAO'], $this->frozenClock->now(), 0, ['BA', 'U2']);
        $encoded = $segment->encode();
        $this->assertEquals(2, $encoded->getAirlines()->count());
        $this->assertEquals('BA', $encoded->getAirlines()[0]);
        $this->assertEquals('U2', $encoded->getAirlines()[1]);
    }

    public function testMultipleFromAirports(): void
    {
        $segment = new Segment(['LGW', 'STN'], ['FAO'], $this->frozenClock->now());
        $encoded = $segment->encode();
        $this->assertEquals(2, $encoded->getFromFlight()->count());
        $this->assertEquals('LGW', $encoded->getFromFlight()[0]->getAirport());
        $this->assertEquals('STN', $encoded->getFromFlight()[1]->getAirport());
    }

    public function testMultipleToAirports(): void
    {
        $segment = new Segment(['LGW'], ['FAO', 'LIS'], $this->frozenClock->now());
        $encoded = $segment->encode();
        $this->assertEquals(2, $encoded->getToFlight()->count());
        $this->assertEquals('FAO', $encoded->getToFlight()[0]->getAirport());
        $this->assertEquals('LIS', $encoded->getToFlight()[1]->getAirport());
    }

    public function testNoAirports(): void
    {
        $segment = new Segment([], [], $this->frozenClock->now());
        $encoded = $segment->encode();
        $this->assertEquals(0, $encoded->getFromFlight()->count());
    }
}
