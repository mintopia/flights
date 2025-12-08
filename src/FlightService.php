<?php
declare(strict_types=1);
namespace Mintopia\Flights;

use Mintopia\Flights\Exceptions\FlightException;
use Mintopia\Flights\Exceptions\GoogleException;
use Mintopia\Flights\Interfaces\QueryBuilderInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class FlightService implements LoggerAwareInterface
{
    const string DEFAULT_USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:145.0) Gecko/20100101 Firefox/145.0';
    const string DEFAULT_CURRENCY = 'GBP';
    const string DEFAULT_LANGUAGE = 'en-GB';
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

    protected Container $container;
    protected LoggerInterface $log;

    public function __construct(
        ?RequestFactoryInterface $requestFactory = null,
        ?ClientInterface $httpClient = null,
        ?LoggerInterface $logger = null)
    {
        if ($logger === null) {
            $logger = new NullLogger();
        }
        $this->log = $logger;

        $this->container = new  Container();

        $bindings = [
            LoggerInterface::class => $logger,
            RequestFactoryInterface::class => $requestFactory,
            ClientInterface::class => $httpClient,
            FlightService::class => $this,
        ];
        foreach ($bindings as $id => $value) {
            if ($value === null) {
                continue;
            }
            $this->container->bind($id, $value);
        }
    }

    public function query(): QueryBuilder
    {
        if (!class_exists('Google\Protobuf\Internal\Message')) {
            $this->log->error('Unable to find Google\Protobuf\Internal\Message class');
            throw new FlightException('Please install the ext-protobuf extension or google/protobuf library');
        }

        return $this->container->get(QueryBuilderInterface::class)
            ->setCurrency($this->getCurrency())
            ->setLanguage($this->getLanguage());
    }

    public function setHttpClient(ClientInterface $httpClient): self
    {
        $this->container->bind(ClientInterface::class, $httpClient);
        return $this;
    }

    public function setRequestFactory(?RequestFactoryInterface $requestFactory): self
    {
        $this->container->bind(RequestFactoryInterface::class, $requestFactory);
        return $this;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->log = $logger;
        $this->container->bind(LoggerInterface::class, $logger);
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

    public function setCookies(?array $cookies): self
    {
        $this->cookies = $cookies;
        return $this;
    }

    public function makeRequest(RequestInterface $request): string
    {
        $httpClient = $this->container->get(ClientInterface::class);
        if ($httpClient === null) {
            return $this->fallbackHttpRequest($request);
        }
        $request = $request
            ->withHeader('User-Agent', $this->getUserAgent())
            ->withHeader('Cookie', $this->getCookies());
        $response = $httpClient->sendRequest($request);
        if ($response->getStatusCode() !== 200) {
            throw new GoogleException("HTTP request to Google Flights failed: [{$response->getStatusCode()}] {$response->getReasonPhrase()}", $response->getStatusCode());
        }
        return $response->getBody()->getContents();
    }

    protected function fallbackHttpRequest(RequestInterface $request): string
    {
        $uri = (string)$request->getUri();
        $result = file_get_contents($uri, context: $this->getHttpContext());
        if ($result !== false) {
            return $result;
        }
        $headers = http_get_last_response_headers();
        foreach ($headers as $header) {
            preg_match_all('/^HTTP\/[^\s]*\s*(?<code>\d+)\s*(?<reason>.*)$/', $header, $matches);
            if (!isset($matches['code'])) {
                continue;
            }
            $reason = $matches['reason'] ?? '';
            throw new GoogleException(trim("HTTP request to Google Flights failed: [{$matches['code']}] {$reason}"));
        }
        throw new GoogleException("HTTP request to Google Flights failed");
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

    protected function getCurrency(): string
    {
        return $this->currency ?? self::DEFAULT_CURRENCY;
    }

    protected function getLanguage(): string
    {
        return $this->language ?? self::DEFAULT_LANGUAGE;
    }

    protected function getHttpContext()
    {
        $headers = [
            "User-Agent: {$this->getUserAgent()}",
            "Cookie: {$this->getCookies()}"
        ];
        return stream_context_create([
            'http' => [
                'method' => "GET",
                // Use CRLF \r\n to separate multiple headers
                'header' => implode("\r\n", $headers)
            ]
        ]);
    }

    public function bind(string $id, mixed $abstract): self
    {
        $this->container->bind($id, $abstract);
        return $this;
    }

    public function unbind(string $id): self
    {
        $this->container->unbind($id);
    }
}