<?php

namespace Mintopia\Flights\Enums;

enum BookingClass: string
{
    case Unknown = 'Unknown';
    case Economy = 'Economy';
    case PremiumEconomy = 'Premium Economy';
    case Business = 'Business';
    case First = 'First';
}
