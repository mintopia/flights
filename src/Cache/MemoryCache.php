<?php

declare(strict_types=1);

namespace Mintopia\Flights\Cache;

use DateInterval;
use DateTimeImmutable;
use Mintopia\Flights\Models\CacheItem;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

class MemoryCache implements CacheInterface, LoggerAwareInterface
{
    /**
     * @var CacheItem[]
     */
    protected array $cache = [];

    protected LoggerInterface $log;

    public function __construct(?LoggerInterface $log)
    {
        if ($log === null) {
            $log = new NullLogger();
        }
        $this->log = $log;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->has($key)) {
            return $this->cache[$this->hashKey($key)]->value;
        }
        return $default;
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $result = $this->setKey($key, $value, $ttl);
        if ($result) {
            $this->hasBeenModified();
        }
        return $result;
    }

    protected function setKey(string $key, mixed $value, DateInterval|int|null $ttl): bool
    {
        $expiry = null;
        if (is_int($ttl)) {
            $ttl = new  DateInterval("PT{$ttl}S");
        }
        if ($ttl instanceof DateInterval) {
            $expiry = new DateTimeImmutable()->add($ttl);
        }
        $this->cache[$this->hashKey($key)] = new CacheItem($key, $value, $expiry);
        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->cache[$this->hashKey($key)]);
        $this->hasBeenModified();
        return true;
    }

    public function clear(): bool
    {
        $this->cache = [];
        $this->hasBeenModified();
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    /**
     * @param iterable<string, mixed> $values
     * @param DateInterval|int|null $ttl
     * @return bool
     */
    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $result = $this->setKey($key, $value, $ttl);
            if ($result === false) {
                return false;
            }
        }
        $this->hasBeenModified();
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        $this->hasBeenModified();
        return true;
    }

    public function has(string $key): bool
    {
        $key = $this->hashKey($key);
        if (!array_key_exists($key, $this->cache)) {
            return false;
        }
        $item = $this->cache[$key];
        if ($item->expiry === null || $item->expiry >= new DateTimeImmutable()) {
            return true;
        }
        return false;
    }

    protected function hashKey(string $key): string
    {
        return md5($key);
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->log = $logger;
    }

    protected function hasBeenModified(): void
    {
        // Do nothing by default, child classes may want to override
    }
}
