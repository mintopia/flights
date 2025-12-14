<?php

declare(strict_types=1);

namespace Mintopia\Flights\Support;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

class SimpleClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }
}
