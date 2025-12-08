<?php
declare(strict_types=1);

namespace Mintopia\Flights\Commands;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Mintopia\Flights\FlightService;
use Mintopia\Flights\Models\Itinerary;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SearchCommand extends Command
{
    protected SymfonyStyle $io;

    protected function configure(): void
    {
        $this->setName('search');
        $this->setDescription('Example for One-Way Flight Search');

        $this->addArgument('from', InputArgument::REQUIRED, 'Airports to leave from');
        $this->addArgument('to', InputArgument::REQUIRED, 'Airports to arrive at');
        $this->addArgument('date', InputArgument::OPTIONAL, 'Date', '+1 day');

        $this->addOption('maxstops', 'm', InputOption::VALUE_REQUIRED, 'Maximum number of stops allowed', 0);
        $this->addOption('airlines', 'a', InputOption::VALUE_OPTIONAL, 'Airlines to search for', '');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->io->title('One-Way Flight QueryBuilder');

        // Initialise our HTTP client and Request Factory
        $client = new Client();
        $requestFactory = new HttpFactory();
        $logger = new ConsoleLogger($output);

        $flightService = new FlightService(logger: $logger)
            ->setHttpClient($client)
            ->setRequestFactory($requestFactory)
            ->bind('iterable', function (iterable $input) {
                if (!is_array($input)) {
                    return $input;
                }
                return new \ArrayIterator($input);
            })
            ->bind(DateTimeInterface::class, CarbonImmutable::class);

        $fromAirports = new \ArrayIterator(explode(',', $input->getArgument('from')));
        $toAirports = new \ArrayIterator(explode(',', $input->getArgument('to')));

        $date = new CarbonImmutable($input->getArgument('date'));
        $maxStops = $input->getOption('maxstops');

        $airlines = explode(',', $input->getOption('airlines'));

        // Returns
        $itineraries = $flightService
            ->query()
            ->addSegment($fromAirports, $toAirports, $date, $maxStops, $airlines)
            ->get();
        $this->renderItineraries($itineraries);

        return self::SUCCESS;
    }

    /**
     * @param iterable<int, Itinerary> $itineraries
     * @return void
     */
    protected function renderItineraries(iterable $itineraries): void
    {
        $table = $this->io->createTable();
        $table->setHeaders(['Departure', 'From', 'To', 'Arrival', 'Operator', 'Flight', 'Stops', 'Duration', 'Price', 'Notes']);

        foreach ($itineraries as $i => $itinerary) {
            if ($i > 0) {
                $table->addRow(new TableSeparator());
            }
            $itineraryPrice = sprintf('%.2f', $itinerary->price / 100) . ' ' . $itinerary->currency;
            $itineraryNote = $itinerary->note;
            foreach ($itinerary->journeys as $journey) {
                $stops = $journey->stops;
                $duration = $journey->duration->format('%dd %hh %im');
                foreach ($journey->flights as $i => $flight) {
                    $table->addRow([
                        $flight->departure->format('d-M-Y H:i'),
                        $flight->from->code,
                        $flight->to->code,
                        $flight->arrival->format('d-M-Y H:i'),
                        $flight->operator,
                        $flight->code,
                        $stops,
                        $duration,
                        $itineraryPrice,
                        $itineraryNote,
                    ]);
                    $stops = '';
                    $duration = '';
                    $itineraryPrice = '';
                    $itineraryNote = '';
                }
            }
        }
        $table->render();
    }
}
