# PHP Flight Search Library

[![codecov](https://codecov.io/gh/mintopia/flights/branch/main/graph/badge.svg?token=W3KMU4MZ9P)](https://codecov.io/gh/mintopia/flights)
![GitHub Actions Workflow Status](https://img.shields.io/github/actions/workflow/status/mintopia/flights/code-quality.yml?branch=main)
[![Packagist Version](https://img.shields.io/packagist/v/mintopia/flights)](https://packagist.org/packages/mintopia/flights)
[![GitHub Sponsors](https://img.shields.io/github/sponsors/mintopia)](https://github.com/sponsors/mintopia)

## Introduction

This is a PHP library for looking up flight information with Google Flights. There isn't an official free API so instead
this uses the same endpoints that the Google Flights website does.

The Google Flights endpoint is a mix of protobuf and nested JSON arrays. This library will encode and decode the
requests and extract the JSON from the HTML pages.

## Why would I use this?

Google Flights and ITA Matrix is great, but it doesn't cover all use-cases. It will only do certain return flights with
certain airlines; it won't show layovers shorter than recommended minimums and sometimes you have a problem to solve
that is harder than you can easily do on Google Flights/ITA Matrix.

Being able to script your searches and make decisions based on your own logic has so much potential. I've used this for
such things as:

- Working out the cheapest way to get from one small domestic airport to another small domestic airport in a different
  country on 4 different airlines; where travel times are after working hours on a Friday, unless its a public holiday.
- Finding an efficient and low cost route for obtaining airline tier points.
- Sending a weekly summary email of affordable flights that fit in around my working hours.

One you can script these searches and do the lookups yourself, you can do all sorts of fun and complex things - it's the
reason I wrote this library!

## Requirements

- PHP 8.4
- Either [`ext-protobuf`](https://pecl.php.net/package/protobuf)
  or the[`google/protobuf`](https://packagist.org/packages/google/protobuf) package.

## Terminology

There's some terminology used in the library that is helpful to understand.

- `Flight` - An individual flight, with a flight number, a departure and arrival time.
- `Journey` - 1 or more flights that make up a particular segment of trip, eg. Outbound or Return. It can contain
  multiple flights if there are connections. Each journey does have a price. You can add these using the `addSegment`
  method on the Search class.
- `Itinerary` - A collection of journeys that make up a single ticketed and priced trip. This would include both the
  outbound and return flights.

Airports are represented by their 3-digit IATA codes, eg. `LHR` for London Heathrow or `LON` for all airports in
the London area.

Airlines are represented by their 2-digit IATA codes, eg. `BA` for British Airways or `VS` for Virgin Atlantic.

## Usage

The main entrypoint is the `\Mintopia\Flights\FlightService` class. From here you can configure your search parameters
and then fetch all trips that meet those parameters.

### Instantiating the Library

The library requires a PSR 18 compatible HTTP client and a Request Factory. A library like Guzzle is able to provide
this. It can be passed in to the constructor or a service container can provide these.

```php
$client = new GuzzleHttp\Client();
$requestFactory = new GuzzleHttp\Psr7\HttpFactory();

$search = new Mintopia\Flights\FlightService(requestFactory: $requestFactory, httpClient: $client);
```

You can also specify a PSR-3 compatible logging interface to either the constructor or via the `setLogger` method. If
one isn't supplied then a null logger is used instead. An example using Monolog is:

```php
// Our HTTP client and request factory from Guzzle
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

$client = new Client();
$requestFactory = new Psr7\HttpFactory();

// Create a logger using Monolog
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Create a log channel to write to flights.log
$log = new Logger('flights');
$log->pushHandler(new StreamHandler('flights.log', Level::Debug));

// Now create the flight search
use Mintopia\Flights\FlightService;
$search = new QueryBuilder(requestFactory: $requestFactory, httpClient: $client, logger: $log);
```

### Searching for flights

Once you have a client, you can start a query and add segments to it, then when done call `getItineraries()`. The
library will then use Google Flights to fetch possible itineraries.

The query builder is immutable, so it will return new instance of itself on every mutable method call.

```php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Mintopia\Flights\FlightService;

// Our dependencies
$client = new Client();
$requestFactory = new Psr7\HttpFactory();

// Create our search client
$flightService = new FlightService($requestFactory, $client);
$itineraries = $flightService->query()->addSegment(
    from: ['LHR', 'LGW'],
    to: 'JFK',
    date: '2025-12-10',
    maxStops: 0,
    airlines: ['BA', 'VS']
)->get();
```

This would perform a search for all flights on 10th December 2025 from London Heathrow and London Gatwick, to New
York's JFK with no stops and limited to Virgin Atlantic.

```php
$flightService = new FlightService($requestFactory, $client);
$flightService->query()->addSegment('LHR', 'FAO')->get();
```

You can leave out most of the options, it'll default to tomorrow for the date with 0 stops and any airline. Airports can
be specified either as an array for multiple or the single airport code.

To search for a return flight, add another segment:

```php
$flightService = new FlightService($requestFactory, $client)
$itineraries = $flightService
    ->query()
    ->addSegment('LGW', 'FAO', '+1 day')
    ->addSegment('FAO', 'LGW', '+3 days')
    ->get();
```

By default, it searches for flights for one passenger, but you can add more:

```php
use Mintopia\Flights\Enums\PassengerType;

$flightService = new FlightService($requestFactory, $client)
$itineraries = $flightService
    ->query()
    ->addSegment('LGW', 'FAO')
    ->addPassenger(PassengerType::Adult)
    ->get();
```

You can also set passengers in a single call:

```php
$flightService = new FlightService($requestFactory, $client)
$itineraries = $flightService
    ->query()
    ->addSegment('LGW', 'FAO')
    ->setPassengers([
        PassengerType::Adult,
        PassengerType::Adult,
        PassengderType::Child,
    ])
    ->get();
```

If you want to search for a different class, eg. Business or Premium Economy, you can also specify it:

```php
use Mintopia\Flights\Enums\BookingClass;

$flightService = new FlightService($requestFactory, $client)
$itineraries = $flightService
    ->query()
    ->addSegment('LGW', 'FAO')
    ->setBookingClass(BookingClass::Business)
    ->get();
```

Finally, you can sort the results by adding the `sortOrder()` call to the search.

```php
use Mintopia\Flights\Enums\SortOrder;

$flightService = new FlightService($requestFactory, $client)
$itineraries = $flightService
    ->query()
    ->addSegment('LGW', 'FAO')
    ->sortOrder(SortOrder::Price)
    ->get();
```

### Caching

The library supports a PSR16 compatible cache. To use it, either pass it in as a constructor argument or into the
`setCache` method. All HTTP requests that result in a 200 OK response will be cached for the TTL. You can set the TTL
with the `setCacheTTL` method which can take either a DateInterval object, a date interval string or a number of
seconds. The default is 1 hour.

There are two PSR16 cache interfaces included with the library, mostly for testing, and they aren't enabled by default.
You can instantiate them and pass them in if you want them, but you probably have better ones available to you.

```php
use Mintopia\Flights\FlightService;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Cache\Adapter\FilesystemAdapter

// The cache also supports a PSR-3 logger
$log = new Logger('flights');
$log->pushHandler(new StreamHandler('flights.log', Level::Debug));

// Use Symfony's filesystem cache, but pass it through their PSR16 translation first
$cache = new Psr16Cache(new FilesystemAdapter());

$flightService = new FlightService();
$flightService->setCache($fileCache);
```

### Putting it all together

So let's see a full implementation of the library:

```php
<?php
// Include the composer autoload
include __DIR__ . '/vendor/autoload.php';

// Third-party libraries
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

// Create a logger for flights.log
$log = new Logger('flights');
$log->pushHandler(new StreamHandler('flights.log', Level::Debug));

// Make our HTTP dependencies
$client = new Client();
$httpFactory = new HttpFactory();

// Now make our cache
$cache = new Psr16Cache(new FilesystemAdapter());

// Create our flight service and use it!
$flightService = new FlightService($httpFactory, $client, $log, $cache);
$itineraries = $flightService->query()->addSegment('LON', 'FAO')->get();

// Now iterate and do what you want with $itineraries
```

## Advanced Usage

For more advanced usage, the library also supports the PSR-11 Container interface and PSR-20 Clock standards. These
allow you to specify a Container (for dependency injection), which will be used when the library attempts to resolve
some classes (although not its own dependencies). It will first see if the container provided is able to provide the
class, and if not, will use itself to create objects within the `Mintopia\Flights` namespace.

The library supports supplying a PSR-20 ClockInterface object. This is used whenever the library wants to obtain the
current timestamp. It comes with its own simple implementation, so one isn't needed as a dependency.

They can be passed in through either constructor parameters or method calls:

```php
use Lcobucci\Clock\SystemClock;
use League\Container\Container;
use Mintopia\Flights\FlightService;

$clock = new \Lcobucci\Clock\SystemClock(new \DateTimeZone('UTC'));
$container = new Container();

// Set them in the constructor
$flightService = new FlightService(clock: $clock, container: $container);

// Or you can set them through method calls
$flightService = new FlightService();
$flightService->setClock($clock);
$flightService->setContainer($container);

// Or you can unset them and they'll go back to their defaults.
$flightService->setClock();
$flightService->setContainer();
```

The main reason to support this is to allow easier testing. By supporting using a container, you can easily pass use
mock objects and stubs when writing tests. The clock interface allows you to easily inject a frozen clock that will
always return a specific time.

## Issues

This library is using the Google Flights website and scraping results. It is then decoding them from bundles of JSON and
reverse engineered protobuf definitions. It is fragile, it may break in new and wonderful ways, especially if Google
change anything.

It is also possible that your IP address may be flagged by Google for suspicious bot activity - and they're not wrong!
However, as long as you're using this for personal use, you'll probably be fine.

If you do encounter a problem, please raise an issue and include details of what you were searching for and any
errors and exceptions.

## Contributing

Please fork and raise pull requests! If you encounter any issues, please raise it and I'll investigate and hopefully
fix it.

The project is targeting PHP 8.4 and 8.5; I will also be aiming to make it support PHP 8.6 when RC's are available. Any
breaking changes for a version of PHP that is supported will result in a new major version.

PHPStan is used at level 8 for static analysis, PHP Code Sniffer is set up for PSR12 compliance. There are comprehensive
unit tests in PHPUnit with 100% test coverage.

Finally - if you're using this in something cool - let me know! I love seeing things being used. If you're using it
in something making you money - a coffee would be appreciated!

## Development

There are some development libraries included for code quality, as well as Symfony Console to provide some CLI tooling
to perform basic searches and to compile the protobuf files.

### CLI One-Way Flight Search

This is mostly used for testing. Just specify the to/from airports. It'll do a search for 1 adult for the following day
and 0 max stops.

```bash
flights search LGW FAO
```

For full help, use `flights search --help`

### Compiling Protobuf Definitions

The `flights.proto` file contains the protobuf definitions that have been reverse engineered from Google Flights. This
was originally from the [hexus07/google-flight-scraper](https://github.com/hexus07/google_flight_scraper/) Python
project.

If you have `protoc` installed, you can compile the protobuf files by using `flights compile`. They'll be built and
copied to the correct location.

## Todo

- Support for multi city searches
- More features, plane type, Wi-Fi, seat pitch, etc.
- Mutation testing
- Full documentation
- Laravel adapter to support configuration, service provider, Collections, CarbonImmutable for datetimes (this is done,
  I just need to release it!)

## Changelog

### v1.0.0 - 16th December 2025

- Initial version of the library

## Thanks

- [Zoë O'Connell](https://github.com/zoeimogen) for inspiring me with her
  [find-flights](https://github.com/zoeimogen/find-flights/) project.
- [Daniil Chuhai](https://github.com/hexus07) for his
  [google_flight_scraper](https://github.com/hexus07/google_flight_scraper/) which I took the protobuf definitions from.

## Licensing

### Protobuf Definitions

The `flights.proto` file is based on [hexus07/google_flight_scraper/](https://github.com/hexus07/google_flight_scraper/) which is used under the MIT
license and has been modified further. The `flights.proto` file contains the copyright and license .

### Flights Library
MIT License

Copyright (c) 2025 Jessica Smith

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.