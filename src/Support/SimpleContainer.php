<?php

declare(strict_types=1);

namespace Mintopia\Flights\Support;

use Mintopia\Flights\Exceptions\ContainerNotFoundException;
use Mintopia\Flights\FlightService;
use Mintopia\Flights\Models\AbstractModel;
use Psr\Container\ContainerInterface;
use ReflectionClass;

class SimpleContainer implements ContainerInterface
{
    public function __construct(protected FlightService $flightService, protected ?ContainerInterface $parent = null)
    {
    }

    public function setParent(?ContainerInterface $parent = null): self
    {
        $this->parent = $parent;
        return $this;
    }

    public function get(string $id)
    {
        if ($this->parent !== null && $this->parent->has($id)) {
            return $this->parent->get($id);
        }

        if (!$this->isOurNamespace($id)) {
            throw new ContainerNotFoundException("Unable to find {$id}");
        }

        if (is_subclass_of($id, AbstractModel::class)) {
            return new $id($this->flightService);
        }

        return new $id();
    }

    public function has(string $id): bool
    {
        if ($this->parent !== null) {
            $foundInParent = $this->parent->has($id);
            if ($foundInParent) {
                return true;
            }
        }

        return $this->isOurNamespace($id);
    }

    protected function isOurNamespace(string $id): bool
    {
        if (!class_exists($id)) {
            return false;
        }

        $reflectionClass = new ReflectionClass($id);
        $namespace = $reflectionClass->getNamespaceName();
        return str_starts_with($namespace, 'Mintopia\Flights');
    }
}
