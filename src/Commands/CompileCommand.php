<?php

declare(strict_types=1);

namespace Mintopia\Flights\Commands;

use Mintopia\Flights\Exceptions\FlightException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CompileCommand extends Command
{
    protected SymfonyStyle $io;

    protected function configure(): void
    {
        $this->setName('compile');
        $this->setDescription('Compile flights.proto into PHP classes using protoc');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->io->title('Protobuf Compiler');

        if (!$this->checkProtoC()) {
            return self::FAILURE;
        }

        if (!$this->checkFlightsProto()) {
            return self::FAILURE;
        }

        $this->compileProtobufs();
        if (!$this->moveCompiledProtobufs()) {
            return self::FAILURE;
        }

        $this->io->success('Protobuf compilation complete!');

        return self::SUCCESS;
    }

    protected function moveCompiledProtobufs(): bool
    {
        $src = '.protobufs/Mintopia/Flights/Protobuf/';
        $dst = 'src/Protobuf/';
        $this->io->writeln("Moving compiled Protobufs from <fg=cyan>{$src}</> to <fg=cyan>{$dst}</>");
        passthru("mv ./{$src}* ./{$dst}", $resultCode);
        if ($resultCode !== 0) {
            $this->io->error('Unable to move compiled Protobuf files');
            return false;
        }
        return true;
    }

    protected function compileProtobufs(): bool
    {
        $rootPath = $this->getRootDir();
        $this->io->writeln("Compiling protobufs");
        chdir($rootPath);
        $compiledPath = "{$rootPath}/.protobufs";
        if (!file_exists($compiledPath)) {
            $this->io->writeln("Creating <fg=cyan>{$compiledPath}</>");
            mkdir($compiledPath);
        }
        passthru("protoc --php_out='{$compiledPath}' flights.proto", $resultCode);
        if ($resultCode !== 0) {
            $this->io->error("Unable to compile protobufs");
            return false;
        }
        $this->io->writeln("Finished compiling protobufs");
        return true;
    }

    protected function checkProtoC(): bool
    {
        $result = shell_exec('protoc --version');
        if ($result === false || $result === null || !str_contains($result, 'libprotoc')) {
            $this->io->error('Unable to find protoc. Is it installed?');
            return false;
        }
        $result = trim($result);
        $this->io->writeln("Found <fg=cyan>{$result}</> as the compiler");
        return true;
    }

    protected function checkFlightsProto(): bool
    {
        $filename = $this->getProtoFilename();
        if (!file_exists($filename)) {
            $this->io->error("Unable to find {$filename}");
            return false;
        }
        $this->io->writeln("Found <fg=cyan>{$filename}</> definition");
        return true;
    }

    protected function getProtoFilename(): string
    {
        return $this->getRootDir() . "/flights.proto";
    }

    protected function getRootDir(): string
    {
        $path = realpath(__DIR__ . '/../../');
        if ($path === false) {
            throw new FlightException('Unable to locate root directory');
        }
        return $path;
    }
}
