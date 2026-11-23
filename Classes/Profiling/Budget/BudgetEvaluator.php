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

namespace KonradMichalik\Typo3RequestProfiler\Profiling\Budget;

use function count;
use function is_array;
use function is_float;
use function is_int;
use function is_string;

/**
 * BudgetEvaluator.
 *
 * Checks persisted profiles against validated budgets, see BudgetLoader.
 * A value is within budget when it does not exceed its limit.
 *
 * @phpstan-import-type Budgets from BudgetLoader
 *
 * @phpstan-type Check array{key: string, actual: int|float|null, limit: int|float, status: 'ok'|'fail'|'n/a', sql?: string}
 * @phpstan-type ProfileResult array{reference: string, token: string|null, path: string, status: 'ok'|'violation'|'skipped', notices: list<string>, checks: list<Check>}
 * @phpstan-type Report array{schemaVersion: int, summary: array{profiles: int, within_budget: int, over_budget: int, skipped: int}, profiles: list<ProfileResult>}
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class BudgetEvaluator
{
    public const SCHEMA_VERSION = 1;

    /**
     * Budget key to the persisted field and the value for an absent field. A
     * section that is left out when empty (queries, http, log) counts as zero.
     * Without such a default the key is reported as not available, because a
     * missing measurement must not pass as a met budget.
     */
    public const KEYS = [
        'timing.total_ms' => [['timing', 'total_ms'], null],
        'queries.count' => [['queries', 'count'], 0],
        'queries.total_ms' => [['queries', 'total_ms'], 0],
        'duplicate_queries.max_count' => [[], 0],
        'http.count' => [['http', 'count'], 0],
        'http.total_ms' => [['http', 'total_ms'], 0],
        'memory.peak_mb' => [['memory', 'peak_mb'], null],
        'memory.peak_pct' => [['memory', 'peak_pct'], null],
        'log.by_level.error' => [['log', 'by_level', 'error'], 0],
    ];

    private const DUPLICATES = 'duplicate_queries.max_count';

    /**
     * @param array<array<string, mixed>> $profiles profiles keyed by the reference they were resolved from
     * @param Budgets                     $budgets
     *
     * @return Report
     */
    public function report(array $profiles, array $budgets): array
    {
        $results = [];
        foreach ($profiles as $reference => $profile) {
            $results[] = $this->evaluate((string) $reference, $profile, $budgets);
        }

        $statuses = array_count_values(array_column($results, 'status'));

        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'summary' => [
                'profiles' => count($results),
                'within_budget' => $statuses['ok'] ?? 0,
                'over_budget' => $statuses['violation'] ?? 0,
                'skipped' => $statuses['skipped'] ?? 0,
            ],
            'profiles' => $results,
        ];
    }

    /**
     * @param array<string, mixed> $profile
     * @param Budgets              $budgets
     *
     * @return ProfileResult
     */
    public function evaluate(string $reference, array $profile, array $budgets): array
    {
        $result = [
            'reference' => $reference,
            'token' => is_string($profile['token'] ?? null) ? $profile['token'] : null,
            'path' => $this->path($profile),
            'status' => 'ok',
            'notices' => [],
            'checks' => [],
        ];

        // A page cache hit is nearly empty and would pass any budget, which hides real problems.
        if (true === $this->nested($profile, 'cache', 'hit')) {
            return [...$result, 'status' => 'skipped', 'notices' => ['page cache hit, not checked: a cached response is nearly empty by design']];
        }

        $checks = [];
        foreach ($this->limitsFor($profile, $budgets) as $key => $limit) {
            $checks[] = $this->check($profile, $key, $limit);
        }

        return [
            ...$result,
            'status' => [] === array_filter($checks, static fn (array $check): bool => 'fail' === $check['status']) ? 'ok' : 'violation',
            'notices' => $this->notices($profile),
            'checks' => $checks,
        ];
    }

    /**
     * Defaults first, then every matching page entry in file order, so a
     * later entry wins over an earlier one.
     *
     * @param array<string, mixed> $profile
     * @param Budgets              $budgets
     *
     * @return array<string, int|float>
     */
    private function limitsFor(array $profile, array $budgets): array
    {
        $limits = $budgets['defaults'];
        foreach ($budgets['pages'] as $page) {
            if ($this->matches($profile, $page['match'])) {
                $limits = [...$limits, ...$page['budgets']];
            }
        }

        return $limits;
    }

    /**
     * @param array<string, mixed>                $profile
     * @param array{path?: string, page_id?: int} $match
     */
    private function matches(array $profile, array $match): bool
    {
        if (isset($match['path']) && $match['path'] !== $this->path($profile)) {
            return false;
        }

        return !isset($match['page_id']) || $match['page_id'] === $this->nested($profile, 'page', 'id');
    }

    /**
     * @param array<string, mixed> $profile
     *
     * @return Check
     */
    private function check(array $profile, string $key, int|float $limit): array
    {
        $actual = $this->measure($profile, $key);
        if (null === $actual) {
            return ['key' => $key, 'actual' => null, 'limit' => $limit, 'status' => 'n/a'];
        }

        $check = ['key' => $key, 'actual' => $actual, 'limit' => $limit, 'status' => $actual > $limit ? 'fail' : 'ok'];
        $sql = self::DUPLICATES === $key && $actual > $limit ? $this->heaviestDuplicate($profile)['sql'] : null;

        return null === $sql ? $check : [...$check, 'sql' => $sql];
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function measure(array $profile, string $key): int|float|null
    {
        if (self::DUPLICATES === $key) {
            return $this->heaviestDuplicate($profile)['count'];
        }

        [$path, $default] = self::KEYS[$key];
        $value = $profile;
        foreach ($path as $segment) {
            $value = is_array($value) ? ($value[$segment] ?? null) : null;
        }

        return is_int($value) || is_float($value) ? $value : $default;
    }

    /**
     * @param array<string, mixed> $profile
     *
     * @return array{count: int, sql: string|null}
     */
    private function heaviestDuplicate(array $profile): array
    {
        $entries = $profile['duplicate_queries'] ?? [];
        $heaviest = ['count' => 0, 'sql' => null];
        foreach (is_array($entries) ? $entries : [] as $entry) {
            if (is_array($entry) && is_int($entry['count'] ?? null) && $entry['count'] > $heaviest['count']) {
                $heaviest = ['count' => $entry['count'], 'sql' => is_string($entry['sql'] ?? null) ? $entry['sql'] : null];
            }
        }

        return $heaviest;
    }

    /**
     * @param array<string, mixed> $profile
     *
     * @return list<string>
     */
    private function notices(array $profile): array
    {
        if (true !== $this->nested($profile, 'queries', 'truncated')) {
            return [];
        }

        return ['queries truncated by the collector cap: count and total_ms are exact, duplicate_queries.max_count only covers the first recorded queries and can be too low'];
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function path(array $profile): string
    {
        $url = $profile['url'] ?? null;
        $path = is_string($url) ? parse_url($url, \PHP_URL_PATH) : null;

        return is_string($path) && '' !== $path ? $path : '/';
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function nested(array $profile, string $section, string $field): mixed
    {
        $data = $profile[$section] ?? null;

        return is_array($data) ? ($data[$field] ?? null) : null;
    }
}
