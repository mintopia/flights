<?php /** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use Lcobucci\Clock\FrozenClock;
use PHPUnit\Framework\TestCase;

abstract class AbstractTestCase extends TestCase
{
    protected FrozenClock $frozenClock;

    public function setUp(): void
    {
        parent::setUp();
        $this->frozenClock = new FrozenClock(new DateTimeImmutable('2025-12-14 00:00:00'));
    }
}
