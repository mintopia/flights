<?php

declare(strict_types=1);

namespace Mintopia\Flights;

use DateInterval;
use Mintopia\Flights\Exceptions\DependencyException;
use Mintopia\Flights\Exceptions\FlightException;
use Mintopia\Flights\Exceptions\GoogleException;
use Mintopia\Flights\Support\SimpleClock;
use Mintopia\Flights\Support\SimpleContainer;
use Psr\Cache\InvalidArgumentException;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

class FlightService implements LoggerAwareInterface
{
    const string DEFAULT_USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:145.0) Gecko/20100101 Firefox/145.0';
    const string DEFAULT_CURRENCY = 'GBP';
    const string DEFAULT_LANGUAGE = 'en-GB';
    const string DEFAULT_CACHE_TTL = 'PT1H';

    const string DEFAULT_CACHE_PREFIX = 'mintopia.flights.';
    /**
     * @const string[] DEFAULT_COOKIES
     */
    const array DEFAULT_COOKIES = [
            'SOCS' => 'CAISNQgjEitib3FfaWRlbnRpdHlmcm9udGVuZHVpc2VydmVyXzIwMjUwNDIzLjA0X3AwGgJ1ayACGgYIgP6lwAY',
            'OTZ' => '8053484_44_48_123900_44_436380',
            'NID' => '8053484_44_48_123900_44_436380',
        ];


    /**
     * @var array<string,string>
     */
    protected array $cookies;
    public string $currency;
    protected string $language;
    protected DateInterval $cacheTTL;
    protected string $userAgent;
    protected string $cachePrefix;
    public LoggerInterface $log;
    public SimpleContainer $container;
    public ClockInterface $clock;

    /**
     * @return string[]
     */
    public function __sleep(): array
    {
        return [
            'cookies',
            'currency',
            'language',
            'userAgent',
            'cacheTTL',
            'cachePrefix',
        ];
    }

    public function __wakeup(): void
    {
        $this->log = new NullLogger();
        $this->clock = new SimpleClock();
        $this->container = new SimpleContainer($this);
    }

    public function __construct(
        protected ?RequestFactoryInterface $requestFactory = null,
        protected ?ClientInterface $httpClient = null,
        ?LoggerInterface $logger = null,
        ?ClockInterface $clock = null,
        protected ?CacheInterface $cache = null,
        ?ContainerInterface $container = null,
    ) {
        // Our dependencies that we can handle ourselves if needed
        $this->log = $logger ?? new NullLogger();
        $this->clock = $clock ?? new SimpleClock();

        // We have a simple container that wraps an upstream container
        $this->container = new SimpleContainer($this, $container);

        // Our defaults
        $this->cacheTTL = new DateInterval(self::DEFAULT_CACHE_TTL);
        $this->userAgent = self::DEFAULT_USER_AGENT;
        $this->cookies = self::DEFAULT_COOKIES;
        $this->cachePrefix = self::DEFAULT_CACHE_PREFIX;
        $this->currency = self::DEFAULT_CURRENCY;
        $this->language = self::DEFAULT_LANGUAGE;
    }

    // Our injected dependencies
    public function setHttpClient(?ClientInterface $httpClient = null): self
    {
        $this->httpClient = $httpClient;
        return $this;
    }

    public function setRequestFactory(?RequestFactoryInterface $requestFactory = null): self
    {
        $this->requestFactory = $requestFactory;
        return $this;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->log = $logger;
    }

    public function setCache(?CacheInterface $cache = null): self
    {
        $this->cache = $cache;
        return $this;
    }

    public function setCachePrefix(?string $prefix = null): self
    {
        $this->cachePrefix = $prefix ?? self::DEFAULT_CACHE_PREFIX;
        return $this;
    }

    public function setClock(?ClockInterface $clock = null): self
    {
        $this->clock = $clock ?? new SimpleClock();
        return $this;
    }

    public function setContainer(?ContainerInterface $container = null): self
    {
        $this->container->setParent($container);
        return $this;
    }

    // Now our configurable settings

    public function setUserAgent(?string $userAgent = null): self
    {
        $this->userAgent = $userAgent ?? self::DEFAULT_USER_AGENT;
        return $this;
    }

    public function setCacheTTL(string|int|DateInterval|null $ttl = null): self
    {
        if ($ttl === null) {
            $ttl = self::DEFAULT_CACHE_TTL;
        }
        if (is_int($ttl)) {
            $ttl = "PT{$ttl}S";
        }
        if (is_string($ttl)) {
            $ttl = new DateInterval($ttl);
        }
        $this->cacheTTL = $ttl;
        return $this;
    }

    /**
     * @param array<string, string>|null $cookies
     * @return $this
     */
    public function setCookies(?array $cookies = null): self
    {
        $this->cookies = $cookies ?? self::DEFAULT_COOKIES;
        return $this;
    }

    public function setDefaultCurrency(?string $currency = null): self
    {
        $this->currency = $currency ?? self::DEFAULT_CURRENCY;
        return $this;
    }

    public function setDefaultLanguage(?string $language = null): self
    {
        $this->language = $language ?? self::DEFAULT_LANGUAGE;
        return $this;
    }

    /**
     * Fetches a new Flight Search query builder instance with the currently configured settings.
     * @return QueryBuilder
     * @throws FlightException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function query(): QueryBuilder
    {
        // We can't cover this in tests
        // @codeCoverageIgnoreStart
        if (!class_exists('Google\Protobuf\Internal\Message')) {
            $this->log->error('Unable to find Google\Protobuf\Internal\Message class');
            throw new FlightException('Please install the ext-protobuf extension or google/protobuf library');
        }
        // @codeCoverageIgnoreEnd

        return $this->container->get(QueryBuilder::class)
            ->setFlightService($this)
            ->setCurrency($this->currency)
            ->setLanguage($this->language);
    }

    public function createRequest(string $method, string $url): RequestInterface
    {
        if ($this->requestFactory === null) {
            throw new DependencyException('No PSR-7 Request Factory implementation has been provided');
        }

        $cookies = '';
        foreach ($this->cookies as $name => $value) {
            $cookies .= "{$name}={$value};";
        }

        return $this->requestFactory->createRequest($method, $url)
            ->withHeader('User-Agent', $this->userAgent)
            ->withHeader('Cookie', $cookies);
    }

    protected function getCacheKey(RequestInterface $request): string
    {
        return $this->cachePrefix . md5(serialize((object)[
            'method' => $request->getMethod(),
            'url' => (string)$request->getUri(),
            'body' => (string)$request->getBody(),
            'headers' => $request->getHeaders(),
        ]));
    }

    public function makeRequest(RequestInterface $request, bool $cache = true): string
    {
        $cacheKey = $this->getCacheKey($request);
        if ($cache && $this->cache !== null && $this->cache->has($cacheKey)) {
            $this->log->debug("Fetching {$request->getMethod()} {$request->getUri()} from cache");
            return $this->cache->get($cacheKey);
        }
        if ($this->httpClient === null) {
            throw new DependencyException('No PSR-18 HTTP Client implementation has been provided');
        }
        $this->log->debug("Fetching {$request->getMethod()} {$request->getUri()} from Google Flights");
        $response = $this->httpClient->sendRequest($request);
        $this->log->info("{$request->getMethod()} {$request->getUri()} => {$response->getStatusCode()} {$response->getReasonPhrase()}");
        if ($response->getStatusCode() !== 200) {
            throw new GoogleException("HTTP request to Google Flights failed: [{$response->getStatusCode()}] {$response->getReasonPhrase()}", $response->getStatusCode());
        }
        $body = $response->getBody()->getContents();
        if ($this->cache !== null) {
            try {
                $this->cache->set($cacheKey, $body, $this->cacheTTL);
            } catch (InvalidArgumentException $ex) {
                $this->log->warning("Unable to store result for {$request->getMethod()} {$request->getUri()} in cache: {$ex->getMessage()}");
            }
        }
        return $body;
    }
}
