<?php

declare(strict_types=1);

namespace Unit\Support;

use DateInterval;
use Mintopia\Flights\Support\DateIntervalFormatter;
use Tests\Unit\AbstractTestCase;

class DateIntervalFormatterTest extends AbstractTestCase
{
    public function testJustSeconds(): void
    {
        $interval = new DateInterval('PT15S');
        $this->assertEquals(15, $interval->s);
        $this->assertEquals('PT15S', DateIntervalFormatter::format($interval));
    }

    public function testJustMinutes(): void
    {
        $interval = new DateInterval('PT15M');
        $this->assertEquals(15, $interval->i);
        $this->assertEquals('PT15M', DateIntervalFormatter::format($interval));
    }

    public function testJustHours(): void
    {
        $interval = new DateInterval('PT15H');
        $this->assertEquals(15, $interval->h);
        $this->assertEquals('PT15H', DateIntervalFormatter::format($interval));
    }

    public function testCombinedTime(): void
    {
        $interval = new DateInterval('PT15H42M23S');
        $this->assertEquals(15, $interval->h);
        $this->assertEquals(42, $interval->i);
        $this->assertEquals(23, $interval->s);
        $this->assertEquals('PT15H42M23S', DateIntervalFormatter::format($interval));
    }

    public function testJustDays(): void
    {
        $interval = new DateInterval('P12D');
        $this->assertEquals('P12D', DateIntervalFormatter::format($interval));
    }
    public function testJustMonths(): void
    {
        $interval = new DateInterval('P6M');
        $this->assertEquals('P6M', DateIntervalFormatter::format($interval));
    }
    public function testJustYears(): void
    {
        $interval = new DateInterval('P1Y');
        $this->assertEquals('P1Y', DateIntervalFormatter::format($interval));
    }
    public function testCombinedDate(): void
    {
        $interval = new DateInterval('P14Y8M16D');
        $this->assertEquals('P14Y8M16D', DateIntervalFormatter::format($interval));
    }

    public function testWeKeepExpandedTimePeriods(): void
    {
        $interval = new DateInterval('P18M');
        $this->assertEquals('P18M', DateIntervalFormatter::format($interval));
    }
    public function testFormatNoDifference(): void
    {
        $interval = new DateInterval('PT0S');
        $this->assertEquals('PT0S', DateIntervalFormatter::format($interval));
    }
}
