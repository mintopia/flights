<?php

declare(strict_types=1);

namespace Mintopia\Flights\Commands;

use DateInterval;
use DateTimeImmutable;
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
        $this->setDescription('Example for Flight Search');

        $this->addArgument('from', InputArgument::REQUIRED, 'Airports to leave from');
        $this->addArgument('to', InputArgument::REQUIRED, 'Airports to arrive at');
        $this->addArgument('date', InputArgument::OPTIONAL, 'Date', '+1 day');

        $this->addOption('maxstops', 'm', InputOption::VALUE_REQUIRED, 'Maximum number of stops allowed', 0);
        $this->addOption('airlines', 'a', InputOption::VALUE_REQUIRED, 'Airlines to search for', '');
        $this->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Find return flights after this many days', null);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->io->title('Flight Search');

        // Initialise our HTTP Client, Request Factory and Logger
        $client = new Client();
        $requestFactory = new HttpFactory();
        $logger = new ConsoleLogger($output);

        // As an example, pass the logger in via constructor and the other dependencies in through methods
        $flightService = new FlightService(logger: $logger)
            ->setHttpClient($client)
            ->setRequestFactory($requestFactory);

        $fromAirports = explode(',', $input->getArgument('from'));
        $toAirports = explode(',', $input->getArgument('to'));

        $date = new DateTimeImmutable($input->getArgument('date'));
        $maxStops = (int)$input->getOption('maxstops');

        $airlines = array_filter(explode(',', $input->getOption('airlines')));
        $days = $input->getOption('days') ?? null;
        $return = null;
        if ($days !== null) {
            $return = $date->add(new DateInterval('P' . $days . 'D'));
        }

        // Output our configuration
        $table = $this->io->createTable();
        $table->setStyle('box');
        $table->setHeaders([
            'Setting',
            'Value',
        ]);
        $table->addRows([
            ['From', implode(', ', $fromAirports)],
            ['To', implode(', ', $toAirports)],
            ['Date', $date->format('d-M-Y')],
            ['Return', $return?->format('d-M-Y') ?? 'One-Way'],
            ['Max Stops', $maxStops],
            ['Airlines', empty($airlines) ? 'Any' : implode(', ', $airlines)],
        ]);
        $table->render();

        // Query for Flights
        $query = $flightService->query()->addSegment($fromAirports, $toAirports, $date, $maxStops, $airlines);
        if ($return !== null) {
            $query = $query->addSegment($toAirports, $fromAirports, $return, $maxStops, $airlines);
        }

        $itineraries = $query->get();
        $this->renderItineraries($itineraries);

        return self::SUCCESS;
    }

    /**
     * @param Itinerary[] $itineraries
     * @return void
     */
    protected function renderItineraries(array $itineraries): void
    {
        $table = $this->io->createTable();
        $table->setStyle('box');
        $table->setHeaders(['Departure', 'From', 'To', 'Arrival', 'Operator', 'Flight', 'Stops', 'Duration', 'Price', 'Notes']);

        foreach ($itineraries as $i => $itinerary) {
            if ($i > 0) {
                $table->addRow(new TableSeparator());
            }
            $itineraryPrice = sprintf('%.2f', $itinerary->price / 100) . ' ' . $itinerary->currency;
            $itineraryNote = $itinerary->note;
            foreach ($itinerary->journeys as $journey) {
                $stops = $journey->stops;
                foreach ($journey->flights as $i => $flight) {
                    $format = '%hh %im';
                    if ($journey->duration->days > 0) {
                        $format = "%dd {$format}";
                    }
                    $table->addRow([
                        $flight->departure->format('d-M-Y H:i'),
                        $flight->from->code,
                        $flight->to->code,
                        $flight->arrival->format('d-M-Y H:i'),
                        $flight->operator,
                        $flight->code,
                        $stops,
                        $flight->duration->format($format),
                        $itineraryPrice,
                        $itineraryNote,
                    ]);
                    $stops = '';
                    $itineraryPrice = '';
                    $itineraryNote = '';
                }
            }
        }
        $table->render();
    }
}
