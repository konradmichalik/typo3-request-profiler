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
use KonradMichalik\Typo3RequestProfiler\Profiling\{ProfileComparer, ProfileResolver};
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

use function in_array;
use function is_string;
use function sprintf;

/**
 * ProfilerCompareCommand.
 *
 * @phpstan-import-type Comparison from ProfileComparer
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[AsCommand(
    name: 'profiler:compare',
    description: 'Compare two request profiles (tokens or JSON file paths) and print what changed.',
)]
final class ProfilerCompareCommand extends Command
{
    public function __construct(
        private readonly ProfileComparer $comparer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('base', InputArgument::REQUIRED, 'Token or file path of the baseline profile')
            ->addArgument('head', InputArgument::REQUIRED, 'Token or file path of the profile to compare against the baseline')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text or json', 'text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = $input->getOption('format');
        $base = $input->getArgument('base');
        $head = $input->getArgument('head');
        if (!in_array($format, ['text', 'json'], true) || !is_string($base) || !is_string($head)) {
            $output->writeln('<error>Unknown format, use text or json.</error>');

            return Command::INVALID;
        }

        try {
            $comparison = $this->comparer->compare(ProfileResolver::resolve($base), ProfileResolver::resolve($head), $base, $head);
        } catch (InvalidArgumentException $exception) {
            $output->writeln('<error>'.OutputFormatter::escape($exception->getMessage()).'</error>');

            return Command::FAILURE;
        }

        if ('json' === $format) {
            // Raw, because SQL in the payload can contain <...> that the console formatter would swallow.
            $output->writeln(json_encode($comparison, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);

            return Command::SUCCESS;
        }

        $output->writeln(OutputFormatter::escape(implode("\n", $this->textLines($comparison))));

        return Command::SUCCESS;
    }

    /**
     * @param Comparison $comparison
     *
     * @return list<string>
     */
    private function textLines(array $comparison): array
    {
        $lines = [sprintf('Comparing %s → %s', $comparison['base'], $comparison['head'])];
        foreach ($comparison['warnings'] as $warning) {
            $lines[] = 'Warning: '.$warning;
        }
        if (!$comparison['comparable']) {
            $lines[] = 'These profiles are not comparable, treat the numbers below with care.';
        }

        $changes = [
            ...$this->metricLines($comparison['metrics']),
            ...$this->topListLines('duplicate_queries', $comparison['duplicate_queries']),
            ...$this->topListLines('slow_queries', $comparison['slow_queries']),
        ];

        return [...$lines, ...([] === $changes ? ['No changes.'] : $changes)];
    }

    /**
     * @param array<string, array{base: int|float, head: int|float, delta: int|float, delta_pct: float|null}> $metrics
     *
     * @return list<string>
     */
    private function metricLines(array $metrics): array
    {
        $lines = [];
        foreach ($metrics as $name => $metric) {
            if (0.0 === (float) $metric['delta']) {
                continue;
            }
            $percent = null === $metric['delta_pct'] ? '' : sprintf(' (%s%%)', $this->signed($metric['delta_pct']));
            $lines[] = sprintf('%-20s %s → %s  %s%s', $name, $metric['base'], $metric['head'], $this->signed($metric['delta']), $percent);
        }

        return $lines;
    }

    /**
     * @param array{added: list<array<string, int|float|string>>, left_top_list: list<array<string, int|float|string>>, changed: list<array{sql: string, base: int|float, head: int|float, delta: int|float}>} $diff
     *
     * @return list<string>
     */
    private function topListLines(string $name, array $diff): array
    {
        $lines = [];
        foreach ($diff['added'] as $entry) {
            $lines[] = sprintf('%-20s new: %s (%s)', $name, $entry['sql'], $this->amount($entry));
        }
        foreach ($diff['changed'] as $entry) {
            $lines[] = sprintf('%-20s changed: %s (%s → %s, %s)', $name, $entry['sql'], $entry['base'], $entry['head'], $this->signed($entry['delta']));
        }
        foreach ($diff['left_top_list'] as $entry) {
            $lines[] = sprintf('%-20s left the top list: %s (%s)', $name, $entry['sql'], $this->amount($entry));
        }

        return $lines;
    }

    private function signed(int|float $value): string
    {
        return ($value > 0 ? '+' : '').$value;
    }

    /**
     * @param array<string, int|float|string> $entry
     */
    private function amount(array $entry): string
    {
        return isset($entry['count']) ? '×'.$entry['count'] : ($entry['ms'] ?? 0).' ms';
    }
}
