<?php

declare(strict_types=1);

namespace Mintopia\Flights\Models;

use Mintopia\Flights\FlightService;

class Airport extends AbstractModel
{
    public function __construct(protected FlightService $flightService, public ?string $code = null, public ?string $name = null)
    {
        parent::__construct($flightService);
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
