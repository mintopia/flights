<?php

declare(strict_types=1);

namespace Mintopia\Flights\Models;

use DateTimeInterface;

class CacheItem
{
    public function __construct(public string $key, public string $value, public ?DateTimeInterface $expiry = null)
    {
    }
}
