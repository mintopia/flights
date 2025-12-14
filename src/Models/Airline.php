<?php

declare(strict_types=1);

namespace Mintopia\Flights\Models;

class Airline extends AbstractModel
{
    public string $code;
    public string $name;

    protected function getModelId(): string
    {
        return $this->code ?? parent::getModelId();
    }

    protected function getModelDescription(): string
    {
        return $this->name ?? parent::getModelDescription();
    }
}
