<?php

declare(strict_types=1);

namespace Mintopia\Flights\Exceptions;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GoogleException extends FlightException
{
    public ?RequestInterface $request;
    public ?ResponseInterface $response;
}
