<?php

declare(strict_types=1);

/*
 * This file is part of the "typo3_request_profiler" TYPO3 CMS extension.
 *
 * (c) 2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KonradMichalik\Typo3RequestProfiler\Command;

use InvalidArgumentException;
use KonradMichalik\Typo3RequestProfiler\Activation\{Duration, ProfilerStateService};
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

use function is_string;
use function sprintf;

/**
 * ProfilerActivateCommand.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[AsCommand(
    name: 'profiler:activate',
    description: 'Temporarily activate request profiling (xdebug-style toggle).',
)]
final class ProfilerActivateCommand extends Command
{
    public function __construct(
        private readonly ProfilerStateService $stateService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'duration',
            null,
            InputOption::VALUE_REQUIRED,
            'How long to keep profiling active, e.g. "15m", "1h", "300s" or a plain second count (max 7 days).',
            '15m',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $durationOption = $input->getOption('duration');
        if (!is_string($durationOption)) {
            $output->writeln('<error>Invalid duration: expected a string value.</error>');

            return Command::FAILURE;
        }

        try {
            $duration = Duration::fromString($durationOption);
        } catch (InvalidArgumentException $exception) {
            $output->writeln('<error>'.$exception->getMessage().'</error>');

            return Command::FAILURE;
        }

        try {
            $expiresAt = $this->stateService->activate($duration);
        } catch (RuntimeException $exception) {
            $output->writeln('<error>'.$exception->getMessage().'</error>');

            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            '<info>Profiling activated until %s (%d seconds).</info>',
            date('c', $expiresAt),
            $duration->seconds(),
        ));

        return Command::SUCCESS;
    }
}
