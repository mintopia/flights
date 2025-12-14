<?php

declare(strict_types=1);

namespace Mintopia\Flights\Support;

use DateInterval;

class DateIntervalFormatter
{
    public static function format(DateInterval $interval): string
    {
        $datePeriods = [
            'd' => '%dD',
            'm' => '%mM',
            'y' => '%yY',
        ];
        $timePeriods = [
            's' => '%sS',
            'i' => '%iM',
            'h' => '%hH',
        ];
        $format = [];
        foreach ($timePeriods as $period => $stringFormat) {
            if (isset($interval->{$period}) && $interval->{$period} > 0) {
                $format[] = $stringFormat;
            }
        }
        if (!empty($format)) {
            $format[] = 'T';
        }
        foreach ($datePeriods as $unit => $stringFormat) {
            if (isset($interval->{$unit}) && $interval->{$unit} > 0) {
                $format[] = $stringFormat;
            }
        }
        $formatString = 'P' . implode('', array_reverse($format));
        if ($formatString === 'P') {
            $formatString = 'PT0S';
        }
        return $interval->format($formatString);
    }
}
