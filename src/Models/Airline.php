<?php
declare(strict_types=1);
namespace Mintopia\Flights\Models;

use Mintopia\Flights\Container;
use Mintopia\Flights\Interfaces\AirlineInterface;

class Airline extends AbstractModel implements AirlineInterface
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
