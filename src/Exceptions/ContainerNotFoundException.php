<?php

declare(strict_types=1);

namespace Mintopia\Flights\Exceptions;

use Psr\Container\NotFoundExceptionInterface;

class ContainerNotFoundException extends FlightException implements NotFoundExceptionInterface
{
}
