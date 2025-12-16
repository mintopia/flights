<?php

/** @noinspection PhpIllegalPsrClassPathInspection */

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Lcobucci\Clock\FrozenClock;
use Mintopia\Flights\Enums\BookingClass;
use Mintopia\Flights\Enums\PassengerType;
use Mintopia\Flights\Enums\SortOrder;
use Mintopia\Flights\Exceptions\DecoderException;
use Mintopia\Flights\Exceptions\DependencyException;
use Mintopia\Flights\Exceptions\GoogleException;
use Mintopia\Flights\Exceptions\SearchException;
use Mintopia\Flights\FlightService;
use Mintopia\Flights\Protobuf\Info;
use Mintopia\Flights\Protobuf\Passenger;
use Mintopia\Flights\Protobuf\Seat;
use Mintopia\Flights\Protobuf\Trip;
use Mintopia\Flights\QueryBuilder;

class QueryBuilderTest extends AbstractTestCase
{
    protected function makeFlightServiceWithHttpFixtures(array $responses = []): FlightService
    {
        $responses = array_map(function (string $filename) {
            return new Response(200, [], file_get_contents(__DIR__ . "/../fixtures/{$filename}"));
        }, $responses);
        $client = $this->makeMockHttpClient($responses);
        return new FlightService(new HttpFactory(), $client);
    }

    protected function getRequestHistoryForQueryBuilder(QueryBuilder $queryBuilder): array
    {
        $history = [];
        $httpClient = $this->makeMockHttpClient([
            new Response(500, [], 'Not Implemented'),
        ], $history);
        $flightService = new FlightService(new HttpFactory(), $httpClient, clock: $this->frozenClock);
        $queryBuilder = $queryBuilder->setFlightService($flightService);
        try {
            $queryBuilder->get();
        } catch (GoogleException $ex) {
            // Do Nothing - this is expected
        }
        return $history;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @return object{ request: Request, query: array<string, string>, info: Info }
     * @throws \Exception
     */
    protected function makeRequest(QueryBuilder $queryBuilder): object
    {
        $history = $this->getRequestHistoryForQueryBuilder($queryBuilder);
        $request = $history[0]['request'];
        parse_str($request->getUri()->getQuery(), $query);
        $info = new Info();
        $info->mergeFromString(base64_decode($query['tfs']));
        return (object)[
            'request' => $request,
            'query' => $query,
            'info' => $info,
        ];
    }

    public function testSetFlightServiceFlightService(): void
    {
        $flightService = new FlightService();
        $queryBuilder = new QueryBuilder();
        $clone = $queryBuilder->setFlightService($flightService);
        $this->assertNotSame($queryBuilder, $clone);
    }

    public function testSetSortOrder(): void
    {
        $queryBuilder = new QueryBuilder();
        $this->assertSame(SortOrder::Price, $queryBuilder->sortOrder);

        $clone = $queryBuilder->setSortOrder(SortOrder::DepartureTime);
        $this->assertNotSame($queryBuilder, $clone);
        $this->assertSame(SortOrder::DepartureTime, $clone->sortOrder);
    }

    public function testSetBookingClass(): void
    {
        $queryBuilder = new QueryBuilder();
        $this->assertSame(BookingClass::Economy, $queryBuilder->bookingClass);

        $clone = $queryBuilder->setBookingClass(BookingClass::Business);
        $this->assertNotSame($queryBuilder, $clone);
        $this->assertSame(BookingClass::Business, $clone->bookingClass);
    }

    public function testAddingSegmentNeedsFlightService(): void
    {
        $this->expectException(DependencyException::class);
        $this->expectExceptionMessage('FlightService not provided for QueryBuilder');

        $queryBuilder = new QueryBuilder();
        $queryBuilder->addSegment('LGW', 'FAO');
    }

    public function testAddingSegmentNeedsQueryBuilder(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder = $queryBuilder->setFlightService(new FlightService());
        $this->assertCount(0, $queryBuilder->segments);
        $clone = $queryBuilder->addSegment('LGW', 'FAO');
        $this->assertNotSame($queryBuilder, $clone);
        $this->assertCount(0, $queryBuilder->segments);
        $this->assertCount(1, $clone->segments);
    }

    public function testSegmentAirportString(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2025-12-10 00:00:00', new DateTimeZone('UTC')));
        $queryBuilder = new QueryBuilder(new FlightService(clock: $clock));

        $this->assertCount(0, $queryBuilder->segments);
        $clone = $queryBuilder->addSegment('LGW', 'FAO');
        $this->assertNotSame($queryBuilder, $clone);
        $this->assertCount(0, $queryBuilder->segments);
        $this->assertCount(1, $clone->segments);

        $flightData = $clone->segments[0]->encode();
        $this->assertEquals('2025-12-10', $flightData->getDate());
        $this->assertEquals(0, $flightData->getMaxStops());
        $this->assertCount(0, $flightData->getAirlines());

        $this->assertCount(1, $flightData->getFromFlight());
        $this->assertEquals('LGW', $flightData->getFromFlight()[0]->getAirport());
        $this->assertCount(1, $flightData->getToFlight());
        $this->assertEquals('FAO', $flightData->getToFlight()[0]->getAirport());
    }

    public function testAddSegmentAirtportArray(): void
    {
        $queryBuilder = new QueryBuilder(new FlightService())
            ->addSegment(['LGW', 'STN'], ['FAO', 'LIS']);
        $this->assertCount(1, $queryBuilder->segments);
        $flightData = $queryBuilder->segments[0]->encode();

        $this->assertCount(2, $flightData->getFromFlight());
        $this->assertEquals('LGW', $flightData->getFromFlight()[0]->getAirport());
        $this->assertEquals('STN', $flightData->getFromFlight()[1]->getAirport());

        $this->assertCount(2, $flightData->getToFlight());
        $this->assertEquals('FAO', $flightData->getToFlight()[0]->getAirport());
        $this->assertEquals('LIS', $flightData->getToFlight()[1]->getAirport());
    }

    public function testAddSegmentAirportCommaString(): void
    {
        $queryBuilder = new QueryBuilder(new FlightService())
            ->addSegment('LGW,STN', 'FAO,LIS');
        $this->assertCount(1, $queryBuilder->segments);
        $flightData = $queryBuilder->segments[0]->encode();

        $this->assertCount(2, $flightData->getFromFlight());
        $this->assertEquals('LGW', $flightData->getFromFlight()[0]->getAirport());
        $this->assertEquals('STN', $flightData->getFromFlight()[1]->getAirport());

        $this->assertCount(2, $flightData->getToFlight());
        $this->assertEquals('FAO', $flightData->getToFlight()[0]->getAirport());
        $this->assertEquals('LIS', $flightData->getToFlight()[1]->getAirport());
    }

    public function testAddSegmentAirlineString(): void
    {
        $queryBuilder = new QueryBuilder(new FlightService())
            ->addSegment('LGW', 'FAO', airlines: 'BA');
        $flightData = $queryBuilder->segments[0]->encode();
        $this->assertCount(1, $flightData->getAirlines());
        $this->assertEquals('BA', $flightData->getAirlines()[0]);
    }

    public function testAddSegmentAirlineCommaString(): void
    {
        $queryBuilder = new QueryBuilder(new FlightService())
            ->addSegment('LGW', 'FAO', airlines: 'BA,U2');
        $flightData = $queryBuilder->segments[0]->encode();
        $this->assertCount(2, $flightData->getAirlines());
        $this->assertEquals('BA', $flightData->getAirlines()[0]);
        $this->assertEquals('U2', $flightData->getAirlines()[1]);
    }

    public function testAddSegmentAirlineArray(): void
    {
        $queryBuilder = new QueryBuilder(new FlightService())
            ->addSegment('LGW', 'FAO', airlines: ['BA', 'U2']);
        $flightData = $queryBuilder->segments[0]->encode();
        $this->assertCount(2, $flightData->getAirlines());
        $this->assertEquals('BA', $flightData->getAirlines()[0]);
        $this->assertEquals('U2', $flightData->getAirlines()[1]);
    }

    public function testSegmentAddMaxStops(): void
    {
        $queryBuilder = new QueryBuilder(new FlightService())
            ->addSegment('LGW', 'FAO', maxStops: 3);
        $flightData = $queryBuilder->segments[0]->encode();
        $this->assertEquals(3, $flightData->getMaxStops());
    }

    public function testSegmentAddDateString(): void
    {
        $queryBuilder = new QueryBuilder(new FlightService())
            ->addSegment('LGW', 'FAO', '2025-12-15');
        $flightData = $queryBuilder->segments[0]->encode();
        $this->assertEquals('2025-12-15', $flightData->getDate());
    }

    public function testSegmentAddDateImmutable(): void
    {
        $queryBuilder = new QueryBuilder(new FlightService())
            ->addSegment('LGW', 'FAO', new DateTimeImmutable('2025-12-15'));
        $flightData = $queryBuilder->segments[0]->encode();
        $this->assertEquals('2025-12-15', $flightData->getDate());
    }

    public function testClearSegments(): void
    {
        $queryBuilder = new QueryBuilder(new FlightService());
        $this->assertCount(0, $queryBuilder->segments);

        $queryBuilder2 = $queryBuilder->addSegment('LGW', 'FAO');
        $this->assertNotSame($queryBuilder, $queryBuilder2);
        $this->assertCount(0, $queryBuilder->segments);
        $this->assertCount(1, $queryBuilder2->segments);

        $queryBuilder3 = $queryBuilder2->clearSegments();
        $this->assertNotSame($queryBuilder2, $queryBuilder3);
        $this->assertCount(1, $queryBuilder2->segments);
        $this->assertCount(0, $queryBuilder3->segments);
    }

    public function testSetLanguage(): void
    {
        $queryBuilder = new QueryBuilder();
        $clone = $queryBuilder->setLanguage('en-US');
        $this->assertNotSame($queryBuilder, $clone);
    }

    public function testWithoutCache(): void
    {
        $queryBuilder = new QueryBuilder();
        $clone = $queryBuilder->withoutCache();
        $this->assertNotSame($queryBuilder, $clone);
    }

    public function testSetCurrency(): void
    {
        $queryBuilder = new QueryBuilder();
        $clone = $queryBuilder->setCurrency('EUR');
        $this->assertNotSame($queryBuilder, $clone);
    }

    public function testAddPassenger(): void
    {
        $queryBuilder = new QueryBuilder();
        $clone = $queryBuilder->addPassenger(PassengerType::Adult);
        $this->assertNotSame($queryBuilder, $clone);
    }

    public function testSetPassengers(): void
    {
        $queryBuilder = new QueryBuilder();
        $clone = $queryBuilder->setPassengers([PassengerType::Adult]);
        $this->assertNotSame($queryBuilder, $clone);
    }

    public function testClearPassengers(): void
    {
        $queryBuilder = new QueryBuilder();
        $clone = $queryBuilder->clearPassengers();
        $this->assertNotSame($queryBuilder, $clone);
    }

    public function testEncodingBasicRequest(): void
    {
        $queryBuilder = new FlightService(clock: $this->frozenClock)->query()
            ->addSegment('LGW', 'FAO');

        $result = $this->makeRequest($queryBuilder);
        $this->assertEquals('GET', $result->request->getMethod());
        $this->assertEquals('https', $result->request->getUri()->getScheme());
        $this->assertEquals('www.google.com', $result->request->getUri()->getHost());
        $this->assertEquals('/travel/flights', $result->request->getUri()->getPath());

        $this->assertEquals('GBP', $result->query['curr']);
        $this->assertEquals('en-GB', $result->query['hl']);
        $this->assertEquals('EgQIAhABIgA', $result->query['tfu']);

        $this->assertCount(1, $result->info->getPassengers());
        $this->assertEquals(Passenger::ADULT, $result->info->getPassengers()[0]);
        $this->assertEquals(Seat::ECONOMY, $result->info->getSeat());
        $this->assertEquals(Trip::ONE_WAY, $result->info->getTrip());

        $this->assertCount(1, $result->info->getData());
        $flightData = $result->info->getData()[0];

        $this->assertCount(1, $flightData->getFromFlight());
        $this->assertEquals('LGW', $flightData->getFromFlight()[0]->getAirport());
        $this->assertCount(1, $flightData->getToFlight());
        $this->assertEquals('FAO', $flightData->getToFlight()[0]->getAirport());
        $this->assertEquals('2025-12-14', $flightData->getDate());
        $this->assertCount(0, $flightData->getAirlines());
        $this->assertEquals(0, $flightData->getMaxStops());
    }

    public function testEncodingMaxStops(): void
    {
        $queryBuilder = new FlightService()
            ->query()
            ->addSegment('LGW', 'FAO', maxStops: 2);
        $result = $this->makeRequest($queryBuilder);
        $this->assertEquals(2, $result->info->getData()[0]->getMaxStops());
    }

    public function testEncodingSortOrder(): void
    {
        $queryBuilder = new FlightService()
            ->query()
            ->addSegment('LGW', 'FAO')
            ->setSortOrder(SortOrder::DepartureTime);
        $result = $this->makeRequest($queryBuilder);
        $this->assertEquals('EgQIAxABIgA', $result->query['tfu']);

        $queryBuilder = new FlightService()
            ->query()
            ->addSegment('LGW', 'FAO')
            ->setSortOrder(SortOrder::Best);
        $result = $this->makeRequest($queryBuilder);
        $this->assertEquals('EgQIARABIgA', $result->query['tfu']);

        $queryBuilder = new FlightService()
            ->query()
            ->addSegment('LGW', 'FAO')
            ->setSortOrder(SortOrder::Duration);
        $result = $this->makeRequest($queryBuilder);
        $this->assertEquals('EgQIBRABIgA', $result->query['tfu']);
    }

    public function testEncodingReturnTrip(): void
    {
        $queryBuilder = new FlightService()
            ->query()
            ->addSegment('LGW', 'FAO', '2025-12-14')
            ->addSegment('FAO', 'LGW', '2025-12-15');
        $result = $this->makeRequest($queryBuilder);
        $this->assertCount(2, $result->info->getData());
        $this->assertEquals(Trip::ROUND_TRIP, $result->info->getTrip());
    }

    public function testEncodingImplicitMultiCity(): void
    {
        $this->expectException(SearchException::class);
        $this->expectExceptionMessage('Multi-city trips are not supported');
        $queryBuilder = new FlightService()
            ->query()
            ->addSegment('LGW', 'FAO', '2025-12-14')
            ->addSegment('FAO', 'OPO', '2025-12-15');
        $result = $this->makeRequest($queryBuilder);
    }

    public function testMultiCityThrowsException(): void
    {
        $this->expectException(SearchException::class);
        $this->expectExceptionMessage('Multi-city trips are not supported');
        $queryBuilder = new FlightService()
            ->query()
            ->addSegment('LGW', 'FAO', '2025-12-14')
            ->addSegment('FAO', 'LGW', '2025-12-15')
            ->addSegment('FAO', 'LGW', '2025-12-15');
        $this->makeRequest($queryBuilder);
    }

    public function testNoSegmentsThrowsException(): void
    {
        $this->expectException(SearchException::class);
        $this->expectExceptionMessage('No segments specified');
        $queryBuilder = new FlightService()
            ->query();
        $this->makeRequest($queryBuilder);
    }

    public function testNoFlightServiceThrowsException(): void
    {
        $this->expectException(DependencyException::class);
        $this->expectExceptionMessage('FlightService not provided for QueryBuilder');
        new QueryBuilder()->get();
    }

    public function testEncodingBookingClass(): void
    {
        $queryBuilder = new FlightService()
            ->query()
            ->addSegment('LGW', 'FAO');

        $result = $this->makeRequest($queryBuilder->setBookingClass(BookingClass::Business));
        $this->assertEquals(Seat::BUSINESS, $result->info->getSeat());

        $result = $this->makeRequest($queryBuilder->setBookingClass(BookingClass::PremiumEconomy));
        $this->assertEquals(Seat::PREMIUM_ECONOMY, $result->info->getSeat());

        $result = $this->makeRequest($queryBuilder->setBookingClass(BookingClass::First));
        $this->assertEquals(Seat::FIRST, $result->info->getSeat());
    }

    public function testEncodingPassengers(): void
    {
        $queryBuilder = new FlightService()
            ->query()
            ->addSegment('LGW', 'FAO');

        $passengers = [
            PassengerType::Adult,
            PassengerType::Adult,
            PassengerType::Child,
            PassengerType::Child,
            PassengerType::Child,
            PassengerType::InfantInSeat,
            PassengerType::InfantInSeat,
            PassengerType::InfantInSeat,
            PassengerType::InfantInSeat,
            PassengerType::InfantOnLap,
            PassengerType::InfantOnLap,
            PassengerType::InfantOnLap,
            PassengerType::InfantOnLap,
            PassengerType::InfantOnLap,
        ];
        $result = $this->makeRequest($queryBuilder->setPassengers($passengers));
        $encodedPassengers = iterator_to_array($result->info->getPassengers());
        $actualPassengers = array_map(function (PassengerType $passenger) {
            return $passenger->value;
        }, $passengers);
        $this->assertEquals($actualPassengers, $encodedPassengers);
    }

    public function testResponseParsingOneWay(): void
    {
        $flightService = $this->makeFlightServiceWithHttpFixtures([
            'lgw-fao.html'
        ]);
        $itineraries = $flightService
            ->query()
            ->addSegment('LGW', 'FAO')
            ->get();
        $this->assertCount(5, $itineraries);
        $this->assertEquals('U28529', $itineraries[0]->flights[0]->code);
        $this->assertEquals('U28533', $itineraries[1]->flights[0]->code);
        $this->assertEquals('W95731', $itineraries[2]->flights[0]->code);
        $this->assertEquals('BA2660', $itineraries[3]->flights[0]->code);
        $this->assertEquals('BA2662', $itineraries[4]->flights[0]->code);
    }

    public function testSortingByDepartureTime(): void
    {
        $flightService = $this->makeFlightServiceWithHttpFixtures([
            'lgw-fao.html'
        ]);
        $itineraries = $flightService
            ->query()
            ->addSegment('LGW', 'FAO')
            ->setSortOrder(SortOrder::DepartureTime)
            ->get();
        $this->assertCount(5, $itineraries);
        $this->assertEquals('W95731', $itineraries[0]->flights[0]->code);
        $this->assertEquals('U28529', $itineraries[1]->flights[0]->code);
        $this->assertEquals('BA2660', $itineraries[2]->flights[0]->code);
        $this->assertEquals('BA2662', $itineraries[3]->flights[0]->code);
        $this->assertEquals('U28533', $itineraries[4]->flights[0]->code);
    }

    public function testSortingByDuration(): void
    {
        $flightService = $this->makeFlightServiceWithHttpFixtures([
            'lgw-fao.html'
        ]);
        $itineraries = $flightService
            ->query()
            ->addSegment('LGW', 'FAO')
            ->setSortOrder(SortOrder::Duration)
            ->get();
        $this->assertCount(5, $itineraries);
        $this->assertEquals('BA2660', $itineraries[0]->flights[0]->code);
        $this->assertEquals('BA2662', $itineraries[1]->flights[0]->code);
        $this->assertEquals('U28533', $itineraries[2]->flights[0]->code);
        $this->assertEquals('U28529', $itineraries[3]->flights[0]->code);
        $this->assertEquals('W95731', $itineraries[4]->flights[0]->code);
    }

    public function testSortbyBest(): void
    {
        $flightService = $this->makeFlightServiceWithHttpFixtures([
            'lgw-fao-indirect.html'
        ]);
        $itineraries = $flightService
            ->query()
            ->addSegment('LGW', 'FAO', maxStops: 1)
            ->setSortOrder(SortOrder::Best)
            ->get();
        $this->assertCount(15, $itineraries);

        foreach ($itineraries as $i => $itinerary) {
            $next = $itineraries[$i + 1] ?? null;
            if (!$next) {
                break;
            }
            $this->assertLessThanOrEqual($next->outbound->stops, $itinerary->outbound->stops);
            if ($itinerary->outbound->stops === $next->outbound->stops) {
                $this->assertLessThanOrEqual($next->price, $itinerary->price);
            }
        }
    }

    public function testBasicReturnParsing(): void
    {
        $flightService = $this->makeFlightServiceWithHttpFixtures([
            'lgw-fao-2way-outbound.html',
            'lgw-fao-2way-return.html'
        ]);
        $itineraries = $flightService
            ->query()
            ->addSegment('LGW', 'FAO', '2025-12-16', 0, 'W9')
            ->addSegment('FAO', 'LGW', '2025-12-18', 0, 'W9')
            ->get();
        $this->assertCount(1, $itineraries);
        $this->assertEquals('W95731', $itineraries[0]->outbound->flights[0]->code);
        $this->assertEquals('W95732', $itineraries[0]->return->flights[0]->code);
    }

    public function testReturnWithNoFlights(): void
    {
        $flightService = $this->makeFlightServiceWithHttpFixtures([
            'lgw-fao-2way-outbound.html',
            'lgw-fao-2way-return-noflights.html'
        ]);
        $itineraries = $flightService
            ->query()
            ->addSegment('LGW', 'FAO', '2025-12-16', 0, 'W9')
            ->addSegment('FAO', 'LGW', '2025-12-17', 0, 'W9')
            ->get();
        $this->assertCount(0, $itineraries);
    }

    public function testParsingWithNoScriptTag(): void
    {
        $flightService = $this->makeFlightServiceWithHttpFixtures([
            'invalid-noscripttag.html',
        ]);
        $this->expectException(GoogleException::class);
        $this->expectExceptionMessage('Unable to find script tag in Google response');
        $flightService
            ->query()
            ->addSegment('LGW', 'FAO',)
            ->get();
    }

    public function testParsingWithNoDataStart(): void
    {
        $flightService = $this->makeFlightServiceWithHttpFixtures([
            'invalid-nodatastart.html',
        ]);
        $this->expectException(GoogleException::class);
        $this->expectExceptionMessage('Unable to find data start in Google response');
        $flightService
            ->query()
            ->addSegment('LGW', 'FAO',)
            ->get();
    }
    public function testParsingWithNoDataEnd(): void
    {
        $flightService = $this->makeFlightServiceWithHttpFixtures([
            'invalid-nodataend.html',
        ]);
        $this->expectException(GoogleException::class);
        $this->expectExceptionMessage('Unable to find data end in Google response');
        $flightService
            ->query()
            ->addSegment('LGW', 'FAO',)
            ->get();
    }
    public function testParsingWithInvalidJSON(): void
    {
        $flightService = $this->makeFlightServiceWithHttpFixtures([
            'invalid-notjson.html',
        ]);
        $this->expectException(DecoderException::class);
        $this->expectExceptionMessage('JSON response did not decode to an array: Syntax error');
        $flightService
            ->query()
            ->addSegment('LGW', 'FAO',)
            ->get();
    }
}
