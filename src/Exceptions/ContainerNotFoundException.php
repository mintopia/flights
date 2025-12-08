<?php
declare(strict_types=1);

namespace Mintopia\Flights\Exceptions;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

class ContainerNotFoundException extends Exception implements NotFoundExceptionInterface
{
}
