<?php

declare(strict_types=1);

namespace Mintopia\Flights\Models;

use Mintopia\Flights\FlightService;

class Airline extends AbstractModel
{
    public function __construct(protected FlightService $service, public ?string $code = null, public ?string $name = null)
    {
        parent::__construct($service);
    }

    protected function getModelId(): string
    {
        return $this->code ?? '';
    }

    protected function getModelDescription(): string
    {
        return $this->name ?? '';
    }
}
