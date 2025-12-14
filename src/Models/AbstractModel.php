<?php

declare(strict_types=1);

namespace Mintopia\Flights\Models;

use DateInterval;
use DateTimeInterface;
use Mintopia\Flights\FlightService;
use Mintopia\Flights\Support\DateIntervalFormatter;
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
        $vars = get_object_vars(...)->__invoke($this);
        return $this->recursiveToArray($vars);
    }

    /**
     * @param iterable<string, mixed> $props
     * @return array<string, mixed>
     */
    protected function recursiveToArray(iterable $props): array
    {
        $newProps = [];
        foreach ($props as $key => $value) {
            if ($value instanceof AbstractModel) {
                $newProps[$key] = $value->toArray();
            } elseif ($value instanceof DateTimeInterface) {
                $newProps[$key] = $value->format('c');
            } elseif ($value instanceof DateInterval) {
                $newProps[$key] = DateIntervalFormatter::format($value);
            } elseif (is_iterable($value)) {
                $newProps[$key] = $this->recursiveToArray($value);
            } else {
                $newProps[$key] = $value;
            }
        }
        return $newProps;
    }

    public function __toString(): string
    {
        $rClass = new ReflectionClass($this);
        $model = $rClass->getShortName();

        $id = $this->getModelId();
        if ($id) {
            $id = ':' . $id;
        }
        return trim("[{$model}{$id}] {$this->getModelDescription()}");
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
            foreach ($this->{$array} as $key => $value) {
                $this->{$array}[$key] = clone $value;
            }
        }
    }
}
