<?php

namespace Mintopia\Flights\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Mintopia\Flights\Enums\SortOrder;
use Mintopia\Flights\Flight;
use Mintopia\Flights\Journey;
use Mintopia\Flights\Search;
use Mintopia\Flights\Trip;
use Monolog\Logger;
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
        $this->setDescription('Search for Flights');

        $this->addArgument('from', InputArgument::REQUIRED, 'Airports to leave from');
        $this->addArgument('to', InputArgument::REQUIRED, 'Airports to arrive at');
        $this->addArgument('date', InputArgument::OPTIONAL, 'Date', '+1 day');

        $this->addOption('maxstops', 's', InputOption::VALUE_REQUIRED, 'Maximum number of stops allowed', 0, [0, 1,2,3]);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->io->title('One-Way Flight Search');

        // Initialise our HTTP client and Request Factory
        $client = new Client();
        $requestFactory = new HttpFactory();

        // Initialise our logger
        $logger = new ConsoleLogger($output);

        $fromAirports = explode(',', $input->getArgument('from'));
        $toAirports = explode(',', $input->getArgument('to'));

        $date = $input->getArgument('date');
        $maxStops = $input->getOption('maxstops');

        // Returns
        $search = new Search($client, $requestFactory, $logger);
        $search->addLeg($fromAirports, $toAirports, $date, $maxStops);
        $trips = $search->getTrips();
        $this->renderTrips($trips);

        return self::SUCCESS;
    }

    /**
     * @param array<int, Trip> $trips
     * @return void
     */
    protected function renderTrips(array $trips): void
    {
        usort($trips, function ($a, $b) {
            return $a->price <=> $b->price;
        });
        $table = $this->io->createTable();
        $table->setHeaders(['Departure', 'From', 'To', 'Arrival', 'Operator', 'Flight', 'Stops', 'Duration', 'Price', 'Notes']);

        foreach ($trips as $i => $trip) {
            if ($i > 0) {
                $table->addRow(new TableSeparator());
            }
            $tripPrice = sprintf('%.2f', $trip->price / 100) . ' ' . $trip->currency;
            $tripNote = $trip->note;
            foreach ($trip->journeys as $journey) {
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
                        $tripPrice,
                        $tripNote,
                    ]);
                    $stops = '';
                    $duration = '';
                    $tripPrice = '';
                    $tripNote = '';
                }
            }
        }
        $table->render();
    }
}
