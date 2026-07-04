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

namespace KonradMichalik\Typo3RequestProfiler\Profiling\Section;

use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\QueryCollector;

use function array_slice;
use function count;

/**
 * QueryAggregator.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class QueryAggregator
{
    private const MAX_DUPLICATES = 20;
    private const MAX_SLOW_QUERIES = 5;

    private ?QueryCollector $memoCollector = null;

    private int $memoRawCount = -1;

    /**
     * @var list<array{sql: string, ms: float, origin?: string|null}>
     */
    private array $memoQueries = [];

    /**
     * @var array{count: int, total_ms: float, duplicates: list<array{sql: string, count: int, total_ms: float, origin?: string}>}|null
     */
    private ?array $memoStats = null;

    /**
     * The application queries of a request: the collected queries minus dev-only
     * schema/connection introspection, so the real query picture (and any N+1)
     * stands out instead of being buried in noise.
     *
     * @return list<array{sql: string, ms: float, origin?: string|null}>
     */
    public function applicationQueries(QueryCollector $collector): array
    {
        $this->refreshMemo($collector);

        return $this->memoQueries;
    }

    /**
     * Memoized {@see aggregate} over the collector's application queries. The
     * query sections run back-to-back at write time against the same collector;
     * without memoization each section would redo the full per-query
     * normalisation regex work.
     *
     * @return array{count: int, total_ms: float, duplicates: list<array{sql: string, count: int, total_ms: float, origin?: string}>}
     */
    public function statsFor(QueryCollector $collector): array
    {
        $this->refreshMemo($collector);

        return $this->memoStats ??= $this->aggregate($this->memoQueries);
    }

    /**
     * Normalise SQL for N+1 detection:
     * 1. string + numeric literals -> ?
     * 2. collapse whitespace
     * 3. IN (?, ?, ...) -> IN (?)  (collapses variable-length IN lists)
     */
    public function normalizeSql(string $sql): string
    {
        $sql = (string) preg_replace("/'(?:[^'\\\\]|\\\\.)*'/", '?', $sql);
        $sql = (string) preg_replace('/\b\d+\b/', '?', $sql);
        $sql = (string) preg_replace('/\s+/', ' ', trim($sql));

        return (string) preg_replace('/\bIN\s*\(\s*\?(?:\s*,\s*\?)*\s*\)/i', 'IN (?)', $sql);
    }

    /**
     * Dev-only connection/schema introspection that DBAL issues in a Development
     * context. It is not application behaviour and only obscures the real query
     * picture, so it is excluded from the profile.
     */
    public function isInfrastructureQuery(string $sql): bool
    {
        $sql = ltrim($sql);

        return 1 === preg_match('/^(SHOW|SET|USE)\b/i', $sql)
            || 1 === preg_match('/^SELECT\s+(DATABASE|VERSION|CONNECTION_ID)\s*\(\)/i', $sql)
            || 1 === preg_match('/^SELECT\s+@@/i', $sql)
            || false !== stripos($sql, 'information_schema');
    }

    /**
     * @param list<array{sql: string, ms: float, origin?: string|null}> $queries
     *
     * @return array{count: int, total_ms: float, duplicates: list<array{sql: string, count: int, total_ms: float, origin?: string}>}
     */
    public function aggregate(array $queries): array
    {
        $totalMs = 0.0;
        /** @var array<string, array{sql: string, count: int, total_ms: float, origin: string|null}> $groups */
        $groups = [];

        foreach ($queries as $query) {
            $totalMs += $query['ms'];
            $normalized = $this->normalizeSql($query['sql']);
            if (!isset($groups[$normalized])) {
                $groups[$normalized] = ['sql' => $normalized, 'count' => 0, 'total_ms' => 0.0, 'origin' => null];
            }
            ++$groups[$normalized]['count'];
            $groups[$normalized]['total_ms'] += $query['ms'];
            // Keep the first known call site as representative for the group.
            $groups[$normalized]['origin'] ??= ($query['origin'] ?? null);
        }

        $duplicates = array_values(array_filter($groups, static fn (array $group): bool => $group['count'] > 1));
        usort($duplicates, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return [
            'count' => count($queries),
            'total_ms' => round($totalMs, 2),
            'duplicates' => array_map(
                static function (array $group): array {
                    $entry = [
                        'sql' => $group['sql'],
                        'count' => $group['count'],
                        'total_ms' => round($group['total_ms'], 2),
                    ];
                    if (null !== $group['origin']) {
                        $entry['origin'] = $group['origin'];
                    }

                    return $entry;
                },
                array_slice($duplicates, 0, self::MAX_DUPLICATES),
            ),
        ];
    }

    /**
     * Top single query executions by wall-clock time — surfaces the one
     * expensive query that is not necessarily part of an N+1 pattern.
     *
     * @param list<array{sql: string, ms: float, origin?: string|null}> $queries
     *
     * @return list<array{sql: string, ms: float, origin?: string}>
     */
    public function slowestQueries(array $queries): array
    {
        usort($queries, static fn (array $a, array $b): int => $b['ms'] <=> $a['ms']);

        return array_map(
            function (array $query): array {
                $entry = [
                    'sql' => $this->normalizeSql($query['sql']),
                    'ms' => round($query['ms'], 2),
                ];
                if (($query['origin'] ?? null) !== null) {
                    $entry['origin'] = $query['origin'];
                }

                return $entry;
            },
            array_slice($queries, 0, self::MAX_SLOW_QUERIES),
        );
    }

    /**
     * (Re)compute the memoized query list when the collector instance changes or
     * has collected further queries since the last call.
     */
    private function refreshMemo(QueryCollector $collector): void
    {
        $rawCount = count($collector->getQueries());
        if ($collector === $this->memoCollector && $rawCount === $this->memoRawCount) {
            return;
        }

        $this->memoCollector = $collector;
        $this->memoRawCount = $rawCount;
        $this->memoStats = null;
        $this->memoQueries = array_values(array_filter(
            $collector->getQueries(),
            fn (array $query): bool => !$this->isInfrastructureQuery($query['sql']),
        ));
    }
}
