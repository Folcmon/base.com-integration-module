<?php

declare(strict_types=1);

namespace App\Integration\Baselinker\UI\Console;

use App\Integration\Baselinker\Application\Command\ImportOrdersCommand;
use App\Integration\Baselinker\Domain\Marketplace;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'baselinker:import-orders',
    description: 'Queue Baselinker order import for a marketplace.'
)]
final class ImportOrdersConsoleCommand extends Command
{
    public function __construct(private readonly MessageBusInterface $messageBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('marketplace', InputArgument::REQUIRED, 'Marketplace code (allegro, amazon).')
            ->addOption('from', null, InputOption::VALUE_OPTIONAL, 'Start date (YYYY-MM-DD).')
            ->addOption('to', null, InputOption::VALUE_OPTIONAL, 'End date (YYYY-MM-DD).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $marketplace = Marketplace::from((string) $input->getArgument('marketplace'));
        $from = $this->parseDate($input->getOption('from'));
        $to = $this->parseDate($input->getOption('to'));

        $this->messageBus->dispatch(new ImportOrdersCommand($marketplace, $from, $to));

        $output->writeln(sprintf('Import queued for %s.', $marketplace->code()));

        return Command::SUCCESS;
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date === false) {
            throw new \InvalidArgumentException('Invalid date format. Use YYYY-MM-DD.');
        }

        return $date;
    }
}
