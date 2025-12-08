<?php
declare(strict_types=1);
namespace Mintopia\Flights\Models;

use Mintopia\Flights\Container;
use Mintopia\Flights\Interfaces\AbstractModelInterface;

abstract class AbstractModel {

    public function __construct(protected Container $container)
    {
    }

    public function toArray(): array
    {
        return $this->recursiveToArray(get_object_vars($this));
    }

    protected function recursiveToArray(iterable $props): array
    {
        foreach ($props as $key => $value) {
            if ($value instanceof AbstractModelInterface) {
                $props[$key] = $value->toArray();
            }
            if (is_iterable($value)) {
                $props[$key] = $this->recursiveToArray($value);
            }
        }
        if (!is_array($props)) {
            $props = (array) $props;
        }
        return $props;
    }

    public function __toString(): string
    {
        $rClass = new \ReflectionClass($this);
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

    protected function initialiseIterables(array $iterables): void
    {
        foreach ($iterables as $iterable) {
            $this->{$iterable} = $this->container->get('iterable', []);
        }
    }

    protected function cloneIterables(array $iterables): void
    {
        foreach ($iterables as $iterable) {
            if (is_object($this->{$iterable})) {
                $this->{$iterable} = clone $this->{$iterable};
            }
            foreach ($this->{$iterable} as $key => $value) {
                $this->{$iterable}[$key] = clone $value;
            }
        }
    }

    public function __clone(): void
    {
    }
}