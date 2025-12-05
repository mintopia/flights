<?php

namespace Mintopia\Flights\Enums;

enum PassengerType: int
{
    case Unknown = 0;
    case Adult = 1;
    case Child = 2;
    case ChildInSeat = 3;
    case ChildOnLap = 4;
}
