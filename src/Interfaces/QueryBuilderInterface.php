<?php
declare(strict_types=1);

namespace Mintopia\Flights\Interfaces;

use Mintopia\Flights\Enums\BookingClass;
use Mintopia\Flights\Enums\PassengerType;
use Mintopia\Flights\Enums\SortOrder;

interface QueryBuilderInterface
{
    public function addSegment(iterable|string $from, iterable|string $to, \DateTimeInterface|string $date = '+1 day', int $maxStops = 0, iterable|string $airlines = []): QueryBuilderInterface;
    public function clearSegments(): QueryBuilderInterface;
    public function addPassenger(PassengerType $passenger): QueryBuilderInterface;
    public function setPassengers(array $passengers): QueryBuilderInterface;
    public function clearPassengers(): QueryBuilderInterface;
    public function setBookingClass(BookingClass $bookingClass): QueryBuilderInterface;
    public function setSortOrder(SortOrder $sortOrder): QueryBuilderInterface;
    public function setLanguage(string $language): QueryBuilderInterface;
    public function setCurrency(string $currency): QueryBuilderInterface;
    public function get(): iterable;
}