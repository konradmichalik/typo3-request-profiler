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
use KonradMichalik\Typo3RequestProfiler\Configuration;
use KonradMichalik\Typo3RequestProfiler\Profiling\Budget\{BudgetEvaluator, BudgetLoader};
use KonradMichalik\Typo3RequestProfiler\Profiling\ProfileResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

use function in_array;
use function is_array;
use function is_string;
use function sprintf;
use function strlen;

/**
 * ProfilerCheckCommand.
 *
 * Exit codes: 0 within budget (or no budgets), 1 at least one violation,
 * 2 a profile or the budget file could not be used.
 *
 * @phpstan-import-type Report from BudgetEvaluator
 * @phpstan-import-type ProfileResult from BudgetEvaluator
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[AsCommand(
    name: 'profiler:check',
    description: 'Check request profiles (tokens or JSON file paths) against the performance budgets and fail on violations.',
)]
final class ProfilerCheckCommand extends Command
{
    private const TOKEN_PREFIX_LENGTH = 8;

    public function __construct(
        private readonly BudgetLoader $loader,
        private readonly BudgetEvaluator $evaluator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('profiles', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Tokens or file paths of the profiles to check')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text or json', 'text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = $input->getOption('format');
        $references = $input->getArgument('profiles');
        if (!in_array($format, ['text', 'json'], true) || !is_array($references)) {
            $output->writeln('<error>Unknown format, use text or json.</error>');

            return Command::INVALID;
        }

        try {
            $budgets = $this->budgets();
            $profiles = null === $budgets ? [] : $this->resolve(array_values(array_filter($references, is_string(...))));
        } catch (InvalidArgumentException $exception) {
            $output->writeln('<error>'.OutputFormatter::escape($exception->getMessage()).'</error>');

            return Command::INVALID;
        }

        $report = $this->evaluator->report($profiles, $budgets ?? ['defaults' => [], 'pages' => []]);
        $this->print($output, $format, $report, null === $budgets);

        return $report['summary']['over_budget'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return array{defaults: array<string, int|float>, pages: list<array{match: array{path?: string, page_id?: int}, budgets: array<string, int|float>}>}|null null when no budgets are configured
     */
    private function budgets(): ?array
    {
        $file = Configuration::budgetsFile();
        if (null === $file) {
            return null;
        }

        $budgets = $this->loader->load($file);

        return [] === $budgets['defaults'] && [] === $budgets['pages'] ? null : $budgets;
    }

    /**
     * Resolves every reference before anything is evaluated, so one bad token
     * is reported together with the others instead of hiding behind a partial result.
     *
     * @param list<string> $references
     *
     * @return array<string, array<string, mixed>>
     */
    private function resolve(array $references): array
    {
        $profiles = [];
        $errors = [];
        foreach ($references as $reference) {
            try {
                $profiles[$reference] = ProfileResolver::resolve($reference);
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        if ([] !== $errors) {
            throw new InvalidArgumentException(implode("\n", $errors), 1736210020);
        }

        return $profiles;
    }

    /**
     * @param Report $report
     */
    private function print(OutputInterface $output, string $format, array $report, bool $noBudgets): void
    {
        if ('json' === $format) {
            // Raw, because SQL in the payload can contain <...> that the console formatter would swallow.
            $output->writeln(json_encode($report, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);

            return;
        }

        $lines = $noBudgets ? ['No budgets configured, nothing to check. Set TYPO3_REQUEST_PROFILER_BUDGETS to a budget file.'] : $this->textLines($report);
        $output->writeln(OutputFormatter::escape(implode("\n", $lines)));
    }

    /**
     * @param Report $report
     *
     * @return list<string>
     */
    private function textLines(array $report): array
    {
        $lines = [];
        foreach ($report['profiles'] as $result) {
            $lines = [...$lines, ...$this->profileLines($result)];
        }

        $summary = $report['summary'];
        $total = sprintf('%d of %d profiles over budget', $summary['over_budget'], $summary['profiles']);

        return [...$lines, $total.($summary['skipped'] > 0 ? sprintf(', %d skipped', $summary['skipped']) : '')];
    }

    /**
     * @param ProfileResult $result
     *
     * @return list<string>
     */
    private function profileLines(array $result): array
    {
        $label = sprintf('%s (token %s)', $result['path'], $this->shortToken($result['token'] ?? $result['reference']));
        $notices = array_map(static fn (string $notice): string => '  Notice: '.$notice, $result['notices']);

        if ('skipped' === $result['status']) {
            return ["- {$label} skipped", ...$notices];
        }

        // A budget the profile cannot measure is shown even on a passing page, so it never goes unnoticed.
        $unmeasured = [] !== array_filter($result['checks'], static fn (array $check): bool => 'n/a' === $check['status']);
        if ('ok' === $result['status'] && !$unmeasured) {
            return [[] === $result['checks'] ? "✓ {$label}   no budgets apply" : "✓ {$label}   all budgets met", ...$notices];
        }

        return [('ok' === $result['status'] ? '✓' : '✗').' '.$label, ...$this->checkLines($result['checks']), ...$notices];
    }

    /**
     * @param list<array{key: string, actual: int|float|null, limit: int|float, status: string, sql?: string}> $checks
     *
     * @return list<string>
     */
    private function checkLines(array $checks): array
    {
        $lines = [];
        foreach ($checks as $check) {
            $lines[] = sprintf(
                '    %-30s %-14s %s%s',
                $check['key'],
                $this->comparison($check),
                match ($check['status']) {
                    'fail' => 'FAIL',
                    'n/a' => 'n/a',
                    default => 'ok',
                },
                isset($check['sql']) ? '  '.$check['sql'] : '',
            );
        }

        return $lines;
    }

    /**
     * @param array{actual: int|float|null, limit: int|float, status: string} $check
     */
    private function comparison(array $check): string
    {
        if (null === $check['actual']) {
            return sprintf('not in profile (limit %s)', $check['limit']);
        }

        return sprintf('%s %s %s', $check['actual'], 'fail' === $check['status'] ? '>' : '≤', $check['limit']);
    }

    private function shortToken(string $token): string
    {
        return strlen($token) > self::TOKEN_PREFIX_LENGTH ? substr($token, 0, self::TOKEN_PREFIX_LENGTH).'…' : $token;
    }
}
