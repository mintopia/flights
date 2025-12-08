# PHP Google Flights Library

## Introduction

This is a PHP library for looking up flight information with Google Flights. There isn't an official free API so instead
this uses the same endpoints that the Google Flights website does.

The Google Flights endpoint is a mix of protobuf and nested JSON arrays. This library will encode and decode the
requests and extract the JSON from the HTML pages.

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
  and return flights.

Airports are represented by their 3-digit IATA codes, eg. `LHR` for London Heathrow or `LON` for all airports in
the London area.

Airlines are represented by their 2-digit IATA codes, eg. `BA` for British Airways or `VS` for Virgin Atlantic.

## Usage

The main entrypoint is the `\Mintopia\Flights\Search` class. From here you can configure your search parameters and then
fetch all trips that meet those parameters.

### Instantiating the Library

The library requires a PSR 18 compatible HTTP client and a Request Factory. A library like Guzzle is able to provide
this. It can be passed in to the constructor or a servce container can provide these.

```php
$client = new GuzzleHttp\Client();
$requestFactory = new GuzzleHttp\Psr7\HttpFactory();

$search = new Mintopia\Flights\QueryBuilder($client, $requestFactory);
```

You can also specify a PSR-3 compatible logging interface to either the constructor or via the `setLogger` method. If
one is isn't supplied then a null logger is used instead. An example using Monolog is:

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
use Mintopia\Flights\QueryBuilder;
$search = new QueryBuilder($client, $requestFactory, $log);
```

Finally, if you're using Laravel, then you can just type hint the search client in your controller actions or
constructors.

```php
namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Mintopia\Flights\QueryBuilder;

class FlightController extends Controller
{
  public function index(QueryBuilder $search): Response
  {
    // Use the search client
    $itineraries = $client->addSegment('LHR', 'JFK')->getItineraries();
  }
}
```
You can also just use `$app->make();` or the `make()` helper function.

```php
<?php
// These all do the same thing
$search = $app->make(Mintopia\Flights\QueryBuilder::class);
$search = App::make(Mintopia\Flights\QueryBuilder::class);
$search = make(Mintopia\Flights\QueryBuilder::class);
```

### Searching for flights

Once you have a client, you can add segments to your search and then when done call `getItineraries()`. The library
will then use Google Flights to fetch possible itineraries.

```php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Mintopia\Flights\QueryBuilder;

// Our dependencies
$client = new Client();
$requestFactory = new Psr7\HttpFactory();

// Create our search client
$search = new QueryBuilder($client, $requestFactory);
$search->addSegment(
    from: ['LHR', 'LGW'],
    to: 'JFK',
    date: '2025-12-10',
    maxStops: 0,
    airlines: ['BA', 'VS']
);
$itineraries = $search->getItineraries();
```

This would perform a search for all flights on 10th December 2025 from London Heathrow and London Gatwick, to New
York's JFK with no stops and limited to Virgin Atlantic.

```php
$search = new Search($client, $requestFactory);
$search->addSegment('LHR', 'FAO');
```

You can leave out most of the options, it'll default to tomorrow for the date with 0 stops and any airline. Airports can
be specified either as an array for multiple or the single airport code.

To search for a return flight, add another segment:

```php
$search = new Search($client, $requestFactory)
    ->addSegment('LGW', 'FAO', '+1 day')
    ->addSegment('FAO', 'LGW', '+3 days')
    ->getItineraries();
```

By default it searches for flights for one passenger, but you can add more:

```php
use Mintopia\Flights\Enums\PassengerType;

$search = new Search($client, $requestFactory)
    ->addSegment('LGW', 'FAO')
    ->addPassenger(PassengerType::Adult)
    ->getItineraries();
```

You can also set passengers in a single call:

```php
$search = new Search($client, $requestFactory)
    ->addSegment('LGW', 'FAO')
    ->setPassengers([
        PassengerType::Adult,
        PassengerType::Adult,
        PassengderType::Child,
    ])
    ->getItineraries();
```

If you want to search for a different class, eg. Business or Premium Economy, you can also specify it:

```php
use Mintopia\Flights\Enums\BookingClass;

$search = new Search($client, $requestFactory)
    ->addSegment('LGW', 'FAO')
    ->setBookingClass(BookingClass::Business)
    ->getItineraries();
```

Finally you can sort the results by adding the `sortOrder()` call to the search.

```php
use Mintopia\Flights\Enums\SortOrder;

$search = new Search($client, $requestFactory)
    ->addSegment('LGW', 'FAO')
    ->sortOrder(SortOrder::Price)
    ->getItineraries();
```

## Contributing

Please fork and raise pull requests! If you encounter any issues, please raise it and I'll investigate and hopefully
fix it.

The project is targeting PHP 8.4 onwards, any breaking changes for a version of PHP that is supported will result in a
new major version.

PHPStan is used at level 8 for static analysis, PHP Code Sniffer is setup for PSR12 compliance. Testing is not written
yet but the aim is to use PHPUnit, 100% coverage and mutation testing using Infection.

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
- More features, plane type, WiFi, seat pitch, etc.
- Unit tests, coverage and mutation testing
- Full documentation

## Thanks

- Zoë O'Connell for inspiring me with her [find-flights](https://github.com/zoeimogen/find-flights/) project.

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