<?php
declare(strict_types=1);

namespace Mintopia\Flights\Exceptions;

use Exception;
use Psr\Container\ContainerExceptionInterface;

class FlightContainerException extends Exception implements ContainerExceptionInterface
{
}
