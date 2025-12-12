<?php

declare(strict_types=1);

namespace Mintopia\Flights;

use DateInterval;
use Mintopia\Flights\Exceptions\DependencyException;
use Mintopia\Flights\Exceptions\FlightException;
use Mintopia\Flights\Exceptions\GoogleException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

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
    protected ?array $cookies = null;
    protected ?string $currency = null;
    protected ?string $language = null;
    protected ?string $userAgent = null;

    protected ?DateInterval $cacheTTL = null;
    protected ?string $cachePrefix = null;

    public LoggerInterface $log;

    /**
     * @return string[]
     */
    public function __serialize(): array
    {
        return [
            'cookies',
            'currency',
            'language',
            'userAgent',
        ];
    }

    public function __wakeup(): void
    {
        $this->log = new NullLogger();
    }

    public function __construct(
        protected ?RequestFactoryInterface $requestFactory = null,
        protected ?ClientInterface $httpClient = null,
        ?LoggerInterface $logger = null,
        protected ?CacheInterface $cache = null
    ) {
        if ($logger === null) {
            $logger = new NullLogger();
        }
        $this->log = $logger;
    }

    public function query(): QueryBuilder
    {
        if (!class_exists('Google\Protobuf\Internal\Message')) {
            $this->log->error('Unable to find Google\Protobuf\Internal\Message class');
            throw new FlightException('Please install the ext-protobuf extension or google/protobuf library');
        }

        return new QueryBuilder($this)
            ->setCurrency($this->getCurrency())
            ->setLanguage($this->getLanguage());
    }

    public function setHttpClient(ClientInterface $httpClient): self
    {
        $this->httpClient = $httpClient;
        return $this;
    }

    public function setRequestFactory(?RequestFactoryInterface $requestFactory): self
    {
        $this->requestFactory = $requestFactory;
        return $this;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->log = $logger;
    }

    public function setCache(?CacheInterface $cache): self
    {
        $this->cache = $cache;
        return $this;
    }

    public function setUserAgent(?string $userAgent = null): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function setDefaultCurrency(?string $currency = null): self
    {
        $this->currency = $currency;
        return $this;
    }

    public function setDefaultLanguage(?string $language = null): self
    {
        $this->language = $language;
        return $this;
    }

    public function setCacheTTL(string|int|DateInterval|null $ttl): self
    {
        if ($ttl === null) {
            $this->cacheTTL = null;
            return $this;
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

    protected function getCacheTTL(): DateInterval
    {
        if ($this->cacheTTL === null) {
            return new DateInterval(self::DEFAULT_CACHE_TTL);
        }
        return $this->cacheTTL;
    }

    public function setCachePrefix(?string $prefix = null): self
    {
        $this->cachePrefix = $prefix;
        return $this;
    }

    /**
     * @param array<string, string>|null $cookies
     * @return $this
     */
    public function setCookies(?array $cookies): self
    {
        $this->cookies = $cookies;
        return $this;
    }

    public function createRequest(string $method, string $url): RequestInterface
    {
        if ($this->requestFactory === null) {
            throw new DependencyException('No PSR-7 Request Factory implementation has been provided');
        }

        return $this->requestFactory->createRequest($method, $url)
            ->withHeader('User-Agent', $this->getUserAgent())
            ->withHeader('Cookie', $this->getCookies());
    }

    protected function getCacheKey(RequestInterface $request): string
    {
        $key = $this->cachePrefix ?? self::DEFAULT_CACHE_PREFIX;
        return $key . md5(serialize((object)[
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
        $body = (string)$response->getBody()->getContents();
        if ($this->cache !== null) {
            try {
                $this->cache->set($cacheKey, $body, $this->getCacheTTL());
            } catch (InvalidArgumentException $ex) {
                $this->log->warning("Unable to store result for {$request->getMethod()} {$request->getUri()} in cache: {$ex->getMessage()}");
            }
        }
        return $body;
    }

    protected function getUserAgent(): string
    {
        return $this->userAgent ?? self::DEFAULT_USER_AGENT;
    }

    protected function getCookies(): string
    {
        $cookies = $this->cookies ?? self::DEFAULT_COOKIES;
        $output = '';
        foreach ($cookies as $name => $value) {
            $output .= "{$name}={$value}; ";
        }
        return trim($output);
    }

    public function getCurrency(): string
    {
        return $this->currency ?? self::DEFAULT_CURRENCY;
    }

    protected function getLanguage(): string
    {
        return $this->language ?? self::DEFAULT_LANGUAGE;
    }
}
