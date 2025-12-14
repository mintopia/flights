<?php

declare(strict_types=1);

namespace Unit\Support;

use DateTimeImmutable;
use Mintopia\Flights\Support\SimpleClock;
use Tests\Unit\AbstractTestCase;

class SimpleClockTest extends AbstractTestCase
{
    public function testNow(): void
    {
        $clock = new SimpleClock();
        $this->assertInstanceOf(DateTimeImmutable::class, $clock->now());
    }

    public function testSystemTimezoneIsUsed(): void
    {
        $clock = new SimpleClock();
        $tz = date_default_timezone_get();
        $this->assertEquals($tz, $clock->now()->getTimezone()->getName());

        date_default_timezone_set('Europe/London');
        $this->assertEquals('Europe/London', $clock->now()->getTimezone()->getName());

        date_default_timezone_set('Europe/Paris');
        $this->assertEquals('Europe/Paris', $clock->now()->getTimezone()->getName());
    }
}
