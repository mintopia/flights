<?php
declare(strict_types=1);

namespace Mintopia\Flights;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Mintopia\Flights\Exceptions\ContainerNotFoundException;
use Mintopia\Flights\Exceptions\FlightContainerException;
use Mintopia\Flights\Interfaces\AirlineInterface;
use Mintopia\Flights\Interfaces\AirportInterface;
use Mintopia\Flights\Interfaces\FlightInterface;
use Mintopia\Flights\Interfaces\ItineraryInterface;
use Mintopia\Flights\Interfaces\JourneyInterface;
use Mintopia\Flights\Interfaces\QueryBuilderInterface;
use Mintopia\Flights\Models\Airline;
use Mintopia\Flights\Models\Airport;
use Mintopia\Flights\Models\Flight;
use Mintopia\Flights\Models\Itinerary;
use Mintopia\Flights\Models\Journey;
use Psr\Container\ContainerInterface;
use ReflectionNamedType;
use ReflectionType;

class Container implements ContainerInterface
{
    protected array $bindings = [];

    public function __construct()
    {
        $this->setupDefaultBindings();
    }

    protected function setupDefaultBindings(): void
    {
        $this->bindings[AirlineInterface::class] = Airline::class;
        $this->bindings[AirportInterface::class] = Airport::class;
        $this->bindings[FlightInterface::class] = Flight::class;
        $this->bindings[ItineraryInterface::class] = Itinerary::class;
        $this->bindings[JourneyInterface::class] = Journey::class;
        $this->bindings[QueryBuilderInterface::class] = QueryBuilder::class;
        $this->bindings[DateTimeInterface::class] = DateTimeImmutable::class;
        $this->bindings[Container::class] = $this;
        $this->bindings['iterable'] = function (iterable $value) {
            if (is_array($value)) {
                return $value;
            }
            return (array) $value;
        };
        $this->bindings[DateInterval::class] = function (string|DateInterval $value) {
            if ($value instanceof DateInterval) {
                return $value;
            }
            return new DateInterval($value);
        };
    }

    public function bind(string $abstract, mixed $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function unbind(string $abstract): void
    {
        unset($this->bindings[$abstract]);
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->bindings);
    }

    public function get(string $id, ...$args): mixed
    {
        $concrete = $this->bindings[$id] ?? null;

        if (!array_key_exists($id, $this->bindings)) {
            throw new ContainerNotFoundException("Unable to resolve abstract $id");
        }

        // If it's callable, invoke it
        if (is_callable($concrete)) {
            return call_user_func($concrete, ...$args);
        }

        // If it's not a string, return it
        if (!is_string($concrete)) {
            return $concrete;
        }

        // If it's a string the class doesn't exist, then we can't do anything
        if (!class_exists($concrete)) {
            throw new FlightContainerException("Unable to find a class for {$id}");
        }

        // Let's instantiate the object!

        // Try and fetch a constructor
        $rClass = new \ReflectionClass($concrete);
        $constructor = $rClass->getConstructor();
        if ($constructor === null) {
            return new $concrete();
        }

        // For all the parameters of the object's constructor, try and find a binding for it if it's type hinted to
        // an object class name
        $params = [];
        $constructorParams = $constructor->getParameters();
        foreach ($constructorParams as $parameter) {
            $name = $parameter->getName();
            if (isset($args[$name])) {
                $params[$name] = $args[$name];
                unset($args[$name]);
            }

            // Check if we already have this parameter
            if (array_key_exists($name, $args)) {
                // We already have a provided named parameter
                $params[$name] = $args;
                continue;
            }

            // Fetch the type of parameter
            $type = $parameter->getType();
            if ($type === null) {
                // The type is null/untyped, don't bother
                $params[$name] = null;
                continue;
            }

            if ($type instanceof ReflectionNamedType) {
                // Our type is a single type, get its name
                $types = [$type->getName()];
            } else {
                // We have multiple types, get its name
                $types = array_map(function (ReflectionType $type): string {
                    return $type->getName();
                }, $type->getTypes());
            }

            foreach ($types as $type) {
                if ($type === $id) {
                    continue;
                }
                if (isset($this->bindings[$type])) {
                    $params[$name] = $this->get($type);
                    continue 2;
                }
            }
        }
        $params = array_values(array_merge($params, $args));
        return new $concrete(...$params);
    }
}