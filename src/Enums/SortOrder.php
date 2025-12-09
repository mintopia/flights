<?php

declare(strict_types=1);

namespace Mintopia\Flights\Enums;

enum SortOrder: string
{
    case Best = 'Best';
    case Price = 'Price';
    case Duration = 'Duration';
    case DepartureTime = 'Departure Time';
}
