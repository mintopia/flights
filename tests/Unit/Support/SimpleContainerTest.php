<?php

declare(strict_types=1);

namespace Unit\Support;

use DateTimeImmutable;
use DateTimeInterface;
use League\Container\Container;
use Mintopia\Flights\Exceptions\ContainerNotFoundException;
use Mintopia\Flights\FlightService;
use Mintopia\Flights\Models\AbstractModel;
use Mintopia\Flights\Models\Airport;
use Mintopia\Flights\QueryBuilder;
use Mintopia\Flights\Support\SimpleClock;
use Mintopia\Flights\Support\SimpleContainer;
use Psr\Container\ContainerInterface;
use Tests\Unit\AbstractTestCase;

class SimpleContainerTest extends AbstractTestCase
{
    protected function getContainer(?ContainerInterface $parent = null): SimpleContainer
    {
        $flightService = new FlightService();
        return new SimpleContainer($flightService, $parent);
    }

    public function testIsMutable(): void
    {
        $container = $this->getContainer();

        $parent = new Container();
        $result = $container->setParent($parent);
        $this->assertEquals($container, $result);
    }
    public function testContainerFindLocalClassesWithOutParent(): void
    {
        $container = $this->getContainer();

        $this->assertTrue($container->has(SimpleClock::class));
        $this->assertFalse($container->has(DateTimeImmutable::class));
    }

    public function testContainerFindLocalClassesWithParent(): void
    {
        $parent = new Container();

        $container = $this->getContainer($parent);
        $parent->add(DateTimeImmutable::class, DateTimeImmutable::class);

        $this->assertFalse($parent->has(SimpleClock::class));
        $this->assertTrue($container->has(SimpleClock::class));

        $this->assertTrue($parent->has(DateTimeImmutable::class));
        $this->assertTrue($container->has(DateTimeImmutable::class));
    }

    public function testContainerCanCreateLocalObjects(): void
    {
        $container = $this->getContainer();
        $this->assertInstanceOf(SimpleClock::class, $container->get(SimpleClock::class));
    }

    public function testContainerCantCreateLocalObjectsWithoutParent(): void
    {
        $this->expectException(ContainerNotFoundException::class);
        $container = $this->getContainer();
        $container->get(DateTimeInterface::class);
    }

    public function testContainerCanCreateLocalObjectsWithParent(): void
    {
        $parent = new Container();
        $container = $this->getContainer($parent);
        $this->assertInstanceOf(SimpleClock::class, $container->get(SimpleClock::class));
    }

    public function testContainerCanCreateNonLocalObjectsThroughParent(): void
    {
        $parent = new Container();
        $parent->add(DateTimeImmutable::class, DateTimeImmutable::class);
        $container = $this->getContainer($parent);
        $this->assertInstanceOf(DateTimeImmutable::class, $container->get(DateTimeImmutable::class));
    }


    public function testContainerInstantiatesModelsWithFlightService(): void
    {
        $flightService = new FlightService();
        $container = new  SimpleContainer($flightService);

        $airport = $container->get(Airport::class);
        $this->assertInstanceOf(AbstractModel::class, $airport);
        $this->assertInstanceOf(Airport::class, $airport);
    }

    public function testContainerCanCreateQueryBuilder(): void
    {
        $container = $this->getContainer();
        $queryBuilder = $container->get(QueryBuilder::class);
        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
    }
}
