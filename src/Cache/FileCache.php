<?php

declare(strict_types=1);

namespace Mintopia\Flights\Cache;

use Psr\Log\LoggerInterface;

class FileCache extends MemoryCache
{
    public function __construct(
        ?LoggerInterface $log = null,
        protected ?string $cacheFilename = null,
        protected bool $autoFlush = false
    ) {
        parent::__construct($log);
        $this->loadCacheFromFile();
    }

    public function __destruct()
    {
        $this->saveCacheToFile();
    }

    protected function loadCacheFromFile(): void
    {
        $filename = $this->getCacheFilename();
        if (!file_exists($filename)) {
            $this->log->notice("FileCache: Cache file {$filename} does not exist");
            return;
        }

        $data = file_get_contents($filename);
        if ($data === false) {
            $this->log->warning("FileCache: Cache file {$filename} could not be loaded");
            return;
        }

        $cacheData = @unserialize($data);
        if (!is_array($cacheData)) {
            $this->log->warning("FileCache: Cache file {$filename} could not be unserialized to an array");
            return;
        }

        $this->cache = $cacheData;
        $count = count($this->cache);
        $this->log->debug("FileCache: Loaded {$count} items from {$filename}");
    }

    protected function saveCacheToFile(): void
    {
        $filename = $this->getCacheFilename();
        $data = serialize($this->cache);
        if (!file_put_contents($filename, $data)) {
            $this->log->warning("FileCache: Cache file {$filename} could not be saved");
        }
        $count = count($this->cache);
        $this->log->debug("FileCache: Saved {$count} items to {$filename}");
    }

    protected function getCacheFilename(): string
    {
        return $this->cacheFilename ?? sys_get_temp_dir() . '/mintopia_flights_cache';
    }

    public function flush(): self
    {
        $this->saveCacheToFile();
        return $this;
    }

    protected function hasBeenModified(): void
    {
        if ($this->autoFlush) {
            $this->flush();
        }
    }
}
