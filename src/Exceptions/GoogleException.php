<?php

namespace Mintopia\Flights\Exceptions;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class GoogleException extends \Exception
{
    public ?RequestInterface $request;
    public ?ResponseInterface $response;
}
