<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Lcobucci\Clock\FrozenClock;
use PHPUnit\Framework\TestCase;

abstract class AbstractTestCase extends TestCase
{
    protected FrozenClock $frozenClock;

    protected function makeMockHttpClient(array $responses = [], array &$history = []): ClientInterface
    {
        $historyMiddleware = Middleware::history($history);
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push($historyMiddleware);
        return new Client([
            'handler' => $handlerStack,
        ]);
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->frozenClock = new FrozenClock(new DateTimeImmutable('2025-12-14 00:00:00'));
    }
}
