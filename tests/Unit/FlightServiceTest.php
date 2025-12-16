<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Lcobucci\Clock\SystemClock;
use League\Container\Container;
use Mintopia\Flights\Enums\SortOrder;
use Mintopia\Flights\Exceptions\DependencyException;
use Mintopia\Flights\Exceptions\GoogleException;
use Mintopia\Flights\FlightService;
use Mintopia\Flights\QueryBuilder;
use Mintopia\Flights\Support\SimpleClock;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Psr16Cache;

class FlightServiceTest extends AbstractTestCase
{
    public function testLoggerHasDefault(): void
    {
        $flightService = new FlightService();
        $this->assertInstanceOf(NullLogger::class, $flightService->log);
    }

    public function testLoggerCanBeSetThroughConstructor(): void
    {
        $logger = new NullLogger();
        $flightService = new FlightService(logger: $logger);
        $this->assertSame($logger, $flightService->log);
    }

    public function testLoggerCanBeSetThroughMethod(): void
    {
        $logger = new NullLogger();
        $flightService = new FlightService();
        $this->assertInstanceOf(NullLogger::class, $flightService->log);
        $this->assertNotSame($logger, $flightService->log);
        $flightService->setLogger($logger);
        $this->assertSame($logger, $flightService->log);
    }

    public function testHttpFactoryIsRequired(): void
    {
        $this->expectException(DependencyException::class);
        $this->expectExceptionMessage('No PSR-7 Request Factory implementation has been provided');
        $flightService = new FlightService();
        $flightService->createRequest('GET', 'foobar');
    }

    public function testHttpFactoryCanBeSetThroughConstructor(): void
    {
        $flightService = new FlightService(requestFactory: new HttpFactory());
        $request = $flightService->createRequest('GET', 'foobar');
        $this->assertInstanceOf(RequestInterface::class, $request);
    }

    public function testHttpFactoryCanBeSetThroughMethod(): void
    {
        $flightService = new FlightService();
        $flightService->setRequestFactory(new HttpFactory());
        $request = $flightService->createRequest('GET', 'foobar');
        $this->assertInstanceOf(RequestInterface::class, $request);
    }

    public function testHttpFactoryCanBeUnset(): void
    {
        $this->expectException(DependencyException::class);
        $this->expectExceptionMessage('No PSR-7 Request Factory implementation has been provided');
        $flightService = new FlightService();
        $flightService->setRequestFactory(new HttpFactory());
        $flightService->setRequestFactory();
        $flightService->createRequest('GET', 'foobar');
    }

    public function testUserAgentHasDefault(): void
    {
        $flightService = new FlightService(new HttpFactory());
        $request = $flightService->createRequest('GET', 'http://example.com');
        $this->assertEquals($flightService::DEFAULT_USER_AGENT, $request->getHeaderLine('User-Agent'));
    }

    public function testUserAgentCanBeSet(): void
    {
        $flightService = new FlightService(new HttpFactory());
        $flightService->setUserAgent('foo');
        $request = $flightService->createRequest('GET', 'http://example.com');
        $this->assertEquals('foo', $request->getHeaderLine('User-Agent'));
    }

    public function testUserAgentCanBeUnset(): void
    {
        $flightService = new FlightService(new HttpFactory());
        $flightService->setUserAgent('foo');
        $flightService->setUserAgent();
        $request = $flightService->createRequest('GET', 'http://example.com');
        $this->assertEquals($flightService::DEFAULT_USER_AGENT, $request->getHeaderLine('User-Agent'));
    }

    public function testHttpClientIsRequired(): void
    {
        $flightService = new FlightService(new HttpFactory());
        $request = $flightService->createRequest('GET', 'foobar');

        $this->expectException(DependencyException::class);
        $this->expectExceptionMessage('No PSR-18 HTTP Client implementation has been provided');
        $flightService->makeRequest($request);
    }

    public function testHttpClientCanBeSetThroughConstructor(): void
    {
        $history = [];
        $mockHttpClient = $this->makeMockHttpClient([
            new Response(200, [], 'Foobar123'),
        ], $history);
        $flightService = new FlightService(new HttpFactory(), $mockHttpClient);
        $request = $flightService->createRequest('GET', 'foobar');
        $this->assertCount(0, $history);
        $response = $flightService->makeRequest($request);
        $this->assertEquals('Foobar123', $response);
        $this->assertCount(1, $history);
        $this->assertEquals($request, $history[0]['request']);
    }

    public function testHttpClientCanBeSetThroughMethod(): void
    {
        $history = [];
        $mockHttpClient = $this->makeMockHttpClient([
            new Response(200, [], 'Foobar123'),
        ], $history);
        $flightService = new FlightService(new HttpFactory());
        $flightService->setHttpClient($mockHttpClient);
        $request = $flightService->createRequest('GET', 'foobar');
        $this->assertCount(0, $history);
        $response = $flightService->makeRequest($request);
        $this->assertEquals('Foobar123', $response);
        $this->assertCount(1, $history);
    }

    public function testHttpClientCanBeUnset(): void
    {
        $history = [];
        $flightService = new FlightService(new HttpFactory(), $this->makeMockHttpClient([
            new  Response(200, [], 'Foobar123'),
        ], $history));
        $request = $flightService->createRequest('GET', 'foobar');

        $this->expectException(DependencyException::class);
        $this->expectExceptionMessage('No PSR-18 HTTP Client implementation has been provided');
        $flightService->setHttpClient();
        $flightService->makeRequest($request);
    }

    public function testDefaultCurrencyIsUsed(): void
    {
        $mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $mockQueryBuilder
            ->expects($this->any())
            ->method($this->anything())
            ->willReturnSelf();
        $mockQueryBuilder
            ->expects($this->once())
            ->method('setCurrency')
            ->with($this->equalTo('GBP'))
            ->willReturnSelf();
        $container = new Container();
        $container->add(QueryBuilder::class, $mockQueryBuilder);

        $this->assertEquals('GBP', FlightService::DEFAULT_CURRENCY);
        $flightService = new FlightService(container: $container);
        $this->assertEquals('GBP', $flightService->currency);
        $flightService->query();
    }

    public function testDefaultCurrencyCanBeSet(): void
    {
        $mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $mockQueryBuilder
            ->expects($this->any())
            ->method($this->anything())
            ->willReturnSelf();
        $mockQueryBuilder
            ->expects($this->once())
            ->method('setCurrency')
            ->with($this->equalTo('EUR'))
            ->willReturnSelf();
        $container = new Container();
        $container->add(QueryBuilder::class, $mockQueryBuilder);

        $flightService = new FlightService(container: $container);
        $flightService->setDefaultCurrency('EUR');
        $this->assertEquals('EUR', $flightService->currency);
        $flightService->query();
    }

    public function testDefaultCurrencyCanBeUnset(): void
    {
        $mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $mockQueryBuilder
            ->expects($this->any())
            ->method($this->anything())
            ->willReturnSelf();
        $mockQueryBuilder
            ->expects($this->once())
            ->method('setCurrency')
            ->with($this->equalTo('GBP'))
            ->willReturnSelf();
        $container = new Container();
        $container->add(QueryBuilder::class, $mockQueryBuilder);

        $flightService = new FlightService(container: $container);
        $flightService->setDefaultCurrency('EUR');
        $this->assertEquals('EUR', $flightService->currency);
        $flightService->setDefaultCurrency();
        $this->assertEquals('GBP', $flightService->currency);
        $flightService->query();
    }

    public function testDefaultLanguageIsUsed(): void
    {
        $mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $mockQueryBuilder
            ->expects($this->any())
            ->method($this->anything())
            ->willReturnSelf();
        $mockQueryBuilder
            ->expects($this->once())
            ->method('setLanguage')
            ->with($this->equalTo('en-GB'))
            ->willReturnSelf();
        $container = new Container();
        $container->add(QueryBuilder::class, $mockQueryBuilder);

        $this->assertEquals('en-GB', FlightService::DEFAULT_LANGUAGE);
        $flightService = new FlightService(container: $container);
        $flightService->query();
    }

    public function testDefaultLanguageCanBeSet(): void
    {
        $mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $mockQueryBuilder
            ->expects($this->any())
            ->method($this->anything())
            ->willReturnSelf();
        $mockQueryBuilder
            ->expects($this->once())
            ->method('setLanguage')
            ->with($this->equalTo('pt-PT'))
            ->willReturnSelf();
        $container = new Container();
        $container->add(QueryBuilder::class, $mockQueryBuilder);

        $flightService = new FlightService(container: $container);
        $flightService->setDefaultLanguage('pt-PT');
        $flightService->query();
    }

    public function testDefaultLanguageCanBeUnset(): void
    {
        $mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $mockQueryBuilder
            ->expects($this->any())
            ->method($this->anything())
            ->willReturnSelf();
        $mockQueryBuilder
            ->expects($this->once())
            ->method('setLanguage')
            ->with($this->equalTo('en-GB'))
            ->willReturnSelf();
        $container = new Container();
        $container->add(QueryBuilder::class, $mockQueryBuilder);

        $flightService = new FlightService(container: $container);
        $flightService->setDefaultLanguage('pt-PT');
        $flightService->setDefaultLanguage();
        $flightService->query();
    }

    public function testSerialization(): void
    {
        $parentContainer = new Container();
        $logger = new NullLogger();
        $parentContainer->add(DateTimeImmutable::class, DateTimeImmutable::class);
        $clock = new SimpleClock();
        $cache = new Psr16Cache(new ArrayAdapter());
        $history = [];
        $httpClient = $this->makeMockHttpClient([], $history);

        $history = [];
        $flightService = new FlightService(
            requestFactory: new HttpFactory(),
            httpClient: $httpClient,
            logger: $logger,
            clock: $clock,
        );
        $this->assertTrue($parentContainer->has(DateTimeImmutable::class));
        $this->assertFalse($flightService->container->has(DateTimeImmutable::class));

        $flightService->setContainer($parentContainer);
        $this->assertTrue($flightService->container->has(DateTimeImmutable::class));
        $flightService->setDefaultCurrency('USD');
        $this->assertEquals('USD', $flightService->currency);
        $flightService->setDefaultLanguage('pt_PT');

        $this->assertSame($logger, $flightService->log);
        $this->assertSame($clock, $flightService->clock);

        $serialized = serialize($flightService);
        $flightService2 = unserialize($serialized);
        $this->assertNotSame($flightService, $flightService2);

        $this->assertInstanceOf(NullLogger::class, $flightService2->log);
        $this->assertNotSame($logger, $flightService2->log);
        $this->assertInstanceOf(SimpleClock::class, $flightService2->clock);
        $this->assertNotSame($clock, $flightService2->clock);
        $this->assertNotSame($flightService->container, $flightService2->container);
        $this->assertFalse($flightService2->container->has(DateTimeImmutable::class));

        $parentContainer = new Container();
        $mockQueryBuilder = $this->createMock(QueryBuilder::class);
        $mockQueryBuilder
            ->expects($this->once())
            ->method('setFlightService')->with($flightService2)->willReturnSelf();
        $mockQueryBuilder
            ->expects($this->once())
            ->method('setCurrency')
            ->with('USD')
            ->willReturnSelf();
        $mockQueryBuilder
            ->expects($this->once())
            ->method('setLanguage')
            ->with('pt_PT')
            ->willReturnSelf();
        $parentContainer->add(QueryBuilder::class, $mockQueryBuilder);
        $flightService2->setContainer($parentContainer);
        $flightService2->query();
    }

    public function testClockHasDefault(): void
    {
        $flightService = new FlightService();
        $this->assertInstanceOf(ClockInterface::class, $flightService->clock);
    }

    public function testClockCanBeSetThroughMethod(): void
    {
        $clock = new SystemClock(new DateTimeZone('UTC'));
        $flightService = new FlightService();
        $flightService->setClock($clock);
        $this->assertSame($flightService->clock, $clock);
    }

    public function testClockCanBeUnset(): void
    {
        $flightService = new FlightService();
        $clock = $flightService->clock;
        $flightService->setClock();
        $this->assertInstanceOf(ClockInterface::class, $flightService->clock);
        $this->assertNotSame($clock, $flightService->clock);
    }

    public function testClockCanBeSetThroughConstructor()
    {
        $clock = new SystemClock(new DateTimeZone('UTC'));
        $flightService = new FlightService(clock: $clock);
        $this->assertSame($clock, $flightService->clock);
    }

    public function testIsMutable(): void
    {
        $flightService = new FlightService();

        $flightService2 = $flightService->setDefaultCurrency();
        $this->assertSame($flightService, $flightService2);

        $flightService2 = $flightService->setDefaultLanguage();
        $this->assertSame($flightService, $flightService2);

        $flightService2 = $flightService->setRequestFactory();
        $this->assertSame($flightService, $flightService2);

        $flightService2 = $flightService->setClock();
        $this->assertSame($flightService, $flightService2);

        $flightService2 = $flightService->setCache();
        $this->assertSame($flightService, $flightService2);

        $flightService2 = $flightService->setContainer();
        $this->assertSame($flightService, $flightService2);

        $flightService2 = $flightService->setCacheTTL();
        $this->assertSame($flightService, $flightService2);

        $flightService2 = $flightService->setHttpClient();
        $this->assertSame($flightService, $flightService2);

        $flightService2 = $flightService->setCookies();
        $this->assertSame($flightService, $flightService2);

        $flightService2 = $flightService->setUserAgent();
        $this->assertSame($flightService, $flightService2);
    }

    public function testDefaultCookiesAreUsed(): void
    {
        $history = [];
        $httpClient = $this->makeMockHttpClient([
            new Response(200, [], 'Foobar123'),
        ], $history);
        $flightService = new FlightService(new HttpFactory(), $httpClient);
        $request = $flightService->createRequest('GET', 'foobar');
        $flightService->makeRequest($request);

        $cookies = '';
        foreach ($flightService::DEFAULT_COOKIES as $name => $value) {
            $cookies .= "{$name}={$value};";
        }
        $this->assertEquals($cookies, $history[0]['request']->getHeaderLine('Cookie'));
    }

    public function testCookiesCanBeSet(): void
    {
        $history = [];
        $httpClient = $this->makeMockHttpClient([
            new Response(200, [], 'Foobar123'),
        ], $history);
        $flightService = new FlightService(new HttpFactory(), $httpClient);
        $flightService->setCookies([
            'foo' => 'bar',
            'red' => 'black',
        ]);
        $request = $flightService->createRequest('GET', 'foobar');
        $flightService->makeRequest($request);

        $this->assertEquals('foo=bar;red=black;', $history[0]['request']->getHeaderLine('Cookie'));
    }

    public function testCookiesCanBeUnset(): void
    {
        $history = [];
        $httpClient = $this->makeMockHttpClient([
            new Response(200, [], 'Foobar123'),
        ], $history);
        $flightService = new FlightService(new HttpFactory(), $httpClient);
        $flightService->setCookies([
            'foo' => 'bar',
            'red' => 'black',
        ]);
        $flightService->setCookies();
        $request = $flightService->createRequest('GET', 'foobar');
        $flightService->makeRequest($request);

        $cookies = '';
        foreach ($flightService::DEFAULT_COOKIES as $name => $value) {
            $cookies .= "{$name}={$value};";
        }
        $this->assertEquals($cookies, $history[0]['request']->getHeaderLine('Cookie'));
    }

    public function testContainerIsNotRequired(): void
    {
        $flightService = new FlightService();
        $query = $flightService->query();
        $this->assertEquals(SortOrder::Price, $query->sortOrder);
    }

    public function testContainerIsUsed(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder
            ->expects($this->any())
            ->method($this->anything())
            ->willReturnSelf();
        $queryBuilder
            ->expects($this->once())
            ->method('setFlightService')
            ->willReturnSelf();
        $container = new Container();
        $container->add(QueryBuilder::class, $queryBuilder);

        $flightService = new FlightService(container: $container);
        $queryBuilder2 = $flightService->query();
        $this->assertSame($queryBuilder, $queryBuilder2);
    }


    public function testContainerCanBeSetThroughMethod(): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder
            ->expects($this->any())
            ->method($this->anything())
            ->willReturnSelf();
        $queryBuilder
            ->expects($this->once())
            ->method('setFlightService')
            ->willReturnSelf();
        $container = new Container();
        $container->add(QueryBuilder::class, $queryBuilder);

        $flightService = new FlightService();
        $flightService->setContainer($container);
        $queryBuilder2 = $flightService->query();
        $this->assertSame($queryBuilder, $queryBuilder2);
    }

    public function testContainerCanBeUnset(): void
    {
        $container = $this->createMock(Container::class);
        $container
            ->expects($this->never())
            ->method($this->anything());

        $flightService = new FlightService(container: $container);
        $flightService->setContainer();
        $flightService->query();
    }

    public function testCacheCanBeSetThroughConstructor(): void
    {
        $history = [];
        $httpClient = $this->makeMockHttpClient([
            new Response(200, [], 'Foobar 123'),
        ], $history);
        $cache = $this->createMock(Psr16Cache::class);
        $cache
            ->expects($this->once())
            ->method('has')
            ->with($this->stringStartsWith(FlightService::DEFAULT_CACHE_PREFIX))
            ->willReturn(false);
        $cache
            ->expects($this->once())
            ->method('set')
            ->with(
                key: $this->stringStartsWith(FlightService::DEFAULT_CACHE_PREFIX),
                value: $this->identicalTo('Foobar 123'),
                ttl: $this->equalTo(new \DateInterval(FlightService::DEFAULT_CACHE_TTL)),
            )
            ->willReturn(true);

        $flightService = new FlightService(new HttpFactory(), $httpClient, cache: $cache);
        $this->expectException(GoogleException::class);
        $this->expectExceptionMessage('nable to find script tag in Google response');
        $flightService->query()->addSegment('LGW', 'FAO')->get();
    }

    public function testCacheResultIsUsed(): void
    {
        $cache = $this->createMock(Psr16Cache::class);
        $cache
            ->expects($this->once())
            ->method('has')
            ->with($this->stringStartsWith(FlightService::DEFAULT_CACHE_PREFIX))
            ->willReturn(true);
        $cache->expects($this->once())
            ->method('get')
            ->with($this->stringStartsWith(FlightService::DEFAULT_CACHE_PREFIX))
            ->willReturn('Foobar 123');

        $flightService = new FlightService(new HttpFactory(), cache: $cache);
        $request = $flightService->createRequest('GET', 'foobar');
        $response = $flightService->makeRequest($request);
        $this->assertEquals('Foobar 123', $response);
    }

    public function testCacheIsNotUsedIfForced(): void
    {
        $history = [];
        $mockHttpClient = $this->makeMockHttpClient([
            new Response(200, [], 'Foobar 123'),
        ], $history);
        $cache = $this->createMock(Psr16Cache::class);
        $cache
            ->expects($this->never())
            ->method('has');
        $cache->expects($this->never())
            ->method('get');
        $cache
            ->expects($this->once())
            ->method('set')
            ->with(
                key: $this->stringStartsWith(FlightService::DEFAULT_CACHE_PREFIX),
                value: $this->identicalTo('Foobar 123'),
                ttl: $this->equalTo(new \DateInterval(FlightService::DEFAULT_CACHE_TTL)),
            )
            ->willReturn(true);
        $flightService = new FlightService(new HttpFactory(), $mockHttpClient, cache: $cache);
        $request = $flightService->createRequest('GET', 'foobar');
        $response = $flightService->makeRequest($request, false);
        $this->assertEquals('Foobar 123', $response);
    }

    public function testCacheExceptionIsHandled(): void
    {
        $history = [];
        $mockHttpClient = $this->makeMockHttpClient([
            new Response(200, [], 'Foobar 123'),
        ], $history);
        $cache = $this->createMock(Psr16Cache::class);
        $cache
            ->expects($this->never())
            ->method('has');
        $cache->expects($this->never())
            ->method('get');
        $cache
            ->expects($this->once())
            ->method('set')
            ->with(
                key: $this->stringStartsWith(FlightService::DEFAULT_CACHE_PREFIX),
                value: $this->identicalTo('Foobar 123'),
                ttl: $this->equalTo(new \DateInterval(FlightService::DEFAULT_CACHE_TTL)),
            )
            ->willThrowException(new InvalidArgumentException());
        $flightService = new FlightService(new HttpFactory(), $mockHttpClient, cache: $cache);
        $request = $flightService->createRequest('GET', 'foobar');
        $response = $flightService->makeRequest($request, false);
        $this->assertEquals('Foobar 123', $response);
    }

    public function testCacheCanBeSetThroughMethod(): void
    {
        $cache = $this->createMock(Psr16Cache::class);
        $cache
            ->expects($this->once())
            ->method('has')
            ->with($this->stringStartsWith(FlightService::DEFAULT_CACHE_PREFIX))
            ->willReturn(true);
        $cache->expects($this->once())
            ->method('get')
            ->with($this->stringStartsWith(FlightService::DEFAULT_CACHE_PREFIX))
            ->willReturn('Foobar 123');

        $flightService = new FlightService(new HttpFactory());
        $flightService->setCache($cache);
        $request = $flightService->createRequest('GET', 'foobar');
        $response = $flightService->makeRequest($request);
        $this->assertEquals('Foobar 123', $response);
    }

    public function testCacheCanBeUnset(): void
    {
        $history = [];
        $mockHttpClient = $this->makeMockHttpClient([
            new Response(200, [], 'Testing 123'),
        ], $history);

        $cache = $this->createMock(Psr16Cache::class);
        $cache
            ->expects($this->never())
            ->method('has');
        $cache->expects($this->never())
            ->method('get');

        $flightService = new FlightService(new HttpFactory(), $mockHttpClient, cache: $cache);
        $request = $flightService->createRequest('GET', 'foobar');
        $flightService->setCache();
        $response = $flightService->makeRequest($request);
        $this->assertEquals('Testing 123', $response);
    }

    public function testCachePrefixCanBeSet(): void
    {
        $history = [];
        $mockHttpClient = $this->makeMockHttpClient([
            new Response(200, [], 'Foobar 123'),
        ], $history);
        $cache = $this->createMock(Psr16Cache::class);
        $cache
            ->expects($this->once())
            ->method('has')
            ->with($this->stringStartsWith('mycacheprefix'))
            ->willReturn(false);
        $cache->expects($this->never())
            ->method('get');
        $cache
            ->expects($this->once())
            ->method('set')
            ->with(
                key: $this->stringStartsWith('mycacheprefix.'),
                value: $this->identicalTo('Foobar 123'),
                ttl: $this->equalTo(new \DateInterval(FlightService::DEFAULT_CACHE_TTL)),
            )
            ->willReturn(true);
        $flightService = new FlightService(new HttpFactory(), $mockHttpClient, cache: $cache);
        $flightService->setCachePrefix('mycacheprefix.');
        $request = $flightService->createRequest('GET', 'foobar');
        $response = $flightService->makeRequest($request);
        $this->assertEquals('Foobar 123', $response);
    }

    public function testCachePrefixCanBeUnset(): void
    {

        $history = [];
        $mockHttpClient = $this->makeMockHttpClient([
            new Response(200, [], 'Foobar 123'),
        ], $history);
        $cache = $this->createMock(Psr16Cache::class);
        $cache
            ->expects($this->once())
            ->method('has')
            ->with($this->stringStartsWith(FlightService::DEFAULT_CACHE_PREFIX))
            ->willReturn(false);
        $cache->expects($this->never())
            ->method('get');
        $cache
            ->expects($this->once())
            ->method('set')
            ->with(
                key: $this->stringStartsWith(FlightService::DEFAULT_CACHE_PREFIX),
                value: $this->identicalTo('Foobar 123'),
                ttl: $this->equalTo(new \DateInterval(FlightService::DEFAULT_CACHE_TTL)),
            )
            ->willReturn(true);
        $flightService = new FlightService(new HttpFactory(), $mockHttpClient, cache: $cache);
        $flightService->setCachePrefix('mytestcacheprefix.');
        $flightService->setCachePrefix();
        $request = $flightService->createRequest('GET', 'foobar');
        $response = $flightService->makeRequest($request);
        $this->assertEquals('Foobar 123', $response);
    }

    public function testCacheKeyUniqueness(): void
    {

        $history = [];
        $mockHttpClient = $this->makeMockHttpClient([
            new Response(200, [], 'Foobar 123'),
            new Response(200, [], 'Another Thing'),
        ], $history);
        $cache = $this->createMock(Psr16Cache::class);
        $cacheHistory = [];
        $cache
            ->expects($this->exactly(2))
            ->method('has')
            ->with($this->stringStartsWith(FlightService::DEFAULT_CACHE_PREFIX),)
            ->willReturnCallback(function (...$args) use (&$cacheHistory) {
                $cacheHistory[] = $args[0];
                return false;
            });
        $cache->expects($this->never())
            ->method('get');
        $cache
            ->expects($this->exactly(2))
            ->method('set')
            ->willReturn(true);
        $flightService = new FlightService(new HttpFactory(), $mockHttpClient, cache: $cache);
        $request = $flightService->createRequest('GET', 'foobar');
        $response = $flightService->makeRequest($request);
        $this->assertEquals('Foobar 123', $response);

        $request = $flightService->createRequest('GET', 'somethingelse');
        $response = $flightService->makeRequest($request);
        $this->assertEquals('Another Thing', $response);
        $this->assertNotEquals($cacheHistory[0], $cacheHistory[1]);
    }

    public function testCacheTTLCanBeSetToString(): void
    {
        $history = [];
        $mockHttpClient = $this->makeMockHttpClient([
            new Response(200, [], 'Foobar 123'),
        ], $history);
        $cache = $this->createMock(Psr16Cache::class);
        $cache->expects($this->once())->method('has')->willReturn(false);
        $cache
            ->expects($this->once())
            ->method('set')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->equalTo(new DateInterval('PT4H')),
            )->willReturn(true);
        $flightService = new FlightService(new HttpFactory(), $mockHttpClient, cache: $cache);
        $this->assertNotEquals('PT4H', FlightService::DEFAULT_CACHE_TTL);
        $flightService->setCacheTTL('PT4H');
        $request = $flightService->createRequest('GET', 'foobar');
        $response = $flightService->makeRequest($request);
        $this->assertEquals('Foobar 123', $response);
    }

    public function testCacheTTLCanBeSetToInt(): void
    {
        $history = [];
        $mockHttpClient = $this->makeMockHttpClient([
            new Response(200, [], 'Foobar 123'),
        ], $history);
        $cache = $this->createMock(Psr16Cache::class);
        $cache->expects($this->once())->method('has')->willReturn(false);
        $cache
            ->expects($this->once())
            ->method('set')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->equalTo(new DateInterval('PT112S')),
            )->willReturn(true);
        $flightService = new FlightService(new HttpFactory(), $mockHttpClient, cache: $cache);
        $this->assertNotEquals('PT112S', FlightService::DEFAULT_CACHE_TTL);
        $flightService->setCacheTTL(112);
        $request = $flightService->createRequest('GET', 'foobar');
        $response = $flightService->makeRequest($request);
        $this->assertEquals('Foobar 123', $response);
    }

    public function testCacheTTLCanBeUnset(): void
    {
        $history = [];
        $mockHttpClient = $this->makeMockHttpClient([
            new Response(200, [], 'Foobar 123'),
        ], $history);
        $cache = $this->createMock(Psr16Cache::class);
        $cache->expects($this->once())->method('has')->willReturn(false);
        $cache
            ->expects($this->once())
            ->method('set')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->equalTo(new DateInterval(FlightService::DEFAULT_CACHE_TTL)),
            )->willReturn(true);
        $flightService = new FlightService(new HttpFactory(), $mockHttpClient, cache: $cache);
        $flightService->setCacheTTL(112);
        $flightService->setCacheTTL();
        $request = $flightService->createRequest('GET', 'foobar');
        $response = $flightService->makeRequest($request);
        $this->assertEquals('Foobar 123', $response);
    }

    public static function responseCodes(): array
    {
        return [
            [400, 'Bad Request'],
            [401, 'Unauthorized'],
            [403, 'Forbidden'],
            [404, 'Not Found'],
            [405, 'Method Not Allowed'],
            [406, 'Not Acceptable'],
            [408, 'Request Time-out'],
            [418, 'I\'m a teapot'],
            [422, 'Unprocessable Entity'],
            [429, 'Too Many Requests'],
            [500, 'Internal Server Error'],
            [501, 'Not Implemented'],
            [502, 'Bad Gateway'],
            [503, 'Service Unavailable'],
            [504, 'Gateway Time-out'],
        ];
    }

    #[DataProvider('responseCodes')]
    public function testResponses(int $code, string $message): void
    {
        $httpClient = $this->makeMockHttpClient([
            new Response($code, [], $message),
        ]);
        $this->expectException(GoogleException::class);
        $this->expectExceptionCode($code);
        $this->expectExceptionMessage("HTTP request to Google Flights failed: [{$code}] {$message}");
        $flightService = new FlightService(new HttpFactory(), $httpClient);
        $flightService
            ->query()
            ->addSegment('LGW', 'FAO')
            ->get();
    }
}
