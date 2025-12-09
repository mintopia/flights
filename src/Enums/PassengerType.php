<?php

declare(strict_types=1);

namespace Mintopia\Flights\Enums;

enum PassengerType: int
{
    case Unknown = 0;
    case Adult = 1;
    case Child = 2;
    case InfantInSeat = 3;
    case InfantOnLap = 4;
}
