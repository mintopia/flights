<?php

declare(strict_types=1);

namespace Mintopia\Flights\Models;

use Mintopia\Flights\FlightService;
use ReflectionClass;

abstract class AbstractModel
{
    public function __construct(protected FlightService $flightService)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->recursiveToArray(get_object_vars($this));
    }

    /**
     * @param array<string, mixed> $props
     * @return array<string, mixed>
     */
    protected function recursiveToArray(array $props): array
    {
        foreach ($props as $key => $value) {
            if ($value instanceof AbstractModel) {
                $props[$key] = $value->toArray();
            }
            if (is_array($value)) {
                $props[$key] = $this->recursiveToArray($value);
            }
        }
        return $props;
    }

    public function __toString(): string
    {
        $rClass = new ReflectionClass($this);
        $model = $rClass->getShortName();

        $id = $this->getModelId();
        if ($id) {
            $id = ':' . $id;
        }
        return trim("[{$model}{$id}] {$this->getModelDescription()}}");
    }

    protected function getModelDescription(): string
    {
        return '';
    }

    protected function getModelId(): string
    {
        return '';
    }

    /**
     * @param string[] $arrays
     * @return void
     */
    protected function cloneArrays(array $arrays): void
    {
        foreach ($arrays as $array) {
            if (is_object($this->{$array})) {
                $this->{$array} = clone $this->{$array};
            }
            foreach ($this->{$array} as $key => $value) {
                $this->{$array}[$key] = clone $value;
            }
        }
    }
}
