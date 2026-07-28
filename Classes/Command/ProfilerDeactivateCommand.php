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

use KonradMichalik\Typo3RequestProfiler\Activation\ProfilerStateService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * ProfilerDeactivateCommand.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ProfilerDeactivateCommand extends Command
{
    public function __construct(
        private readonly ProfilerStateService $stateService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Deactivate the temporary request profiling toggle.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->stateService->deactivate()) {
            $output->writeln('<error>Failed to remove the profiler activation state file.</error>');

            return Command::FAILURE;
        }

        $output->writeln('<info>Profiling deactivated.</info>');

        return Command::SUCCESS;
    }
}
