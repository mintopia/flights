<?php
declare(strict_types=1);

namespace Mintopia\Flights\Models;

use Mintopia\Flights\Container;
use Mintopia\Flights\Interfaces\AirportInterface;

class Airport extends AbstractModel implements AirportInterface
{
    public function __construct(protected Container $container, public string $code, public string $name)
    {
        parent::__construct($container);
    }

    protected function getModelId(): string
    {
        return $this->code;
    }

    protected function getModelDescription(): string
    {
        return $this->name;
    }
}
