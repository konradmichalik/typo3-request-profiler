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

namespace KonradMichalik\Typo3RequestProfiler\Profiling;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Cache\CacheInstruction;
use TYPO3\CMS\Frontend\Page\PageInformation;

use function array_slice;
use function count;
use function is_string;

/**
 * ProfileWriter.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ProfileWriter
{
    private const DEFAULT_MAX_PROFILES = 50;
    private const MAX_DUPLICATES = 20;
    private const MAX_SLOW_QUERIES = 5;
    private const MAX_EVENTS = 20;

    public function write(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $token,
        QueryCollector $collector,
        EventCollector $eventCollector,
        float $totalMs,
    ): void {
        $directory = Environment::getVarPath().'/log/profiles';
        GeneralUtility::mkdir_deep($directory);

        // Drop dev-only schema/connection introspection so the application
        // queries (and any N+1) stand out instead of being buried in noise.
        $queries = array_values(array_filter(
            $collector->getQueries(),
            fn (array $query): bool => !$this->isInfrastructureQuery($query['sql']),
        ));

        $stats = $this->aggregate($queries);

        $profile = [
            'token' => $token,
            'time' => date('c'),
            'method' => $request->getMethod(),
            'url' => (string) $request->getUri(),
            'status' => $response->getStatusCode(),
            'cache' => $this->buildCache($request, $collector),
            'timing' => ['total_ms' => round($totalMs, 2)],
            'memory' => ['peak_mb' => round(memory_get_peak_usage(true) / 1048576, 1)],
            'queries' => [
                'count' => $stats['count'],
                'total_ms' => $stats['total_ms'],
            ],
            'slow_queries' => $this->slowestQueries($queries),
            'duplicate_queries' => $stats['duplicates'],
        ];

        // Only present when PSR-14 event profiling is active; an empty collector
        // means the feature is off (opt-in), so the section is omitted as noise.
        $events = $eventCollector->getEvents();
        if ([] !== $events) {
            $profile['events'] = $this->aggregateEvents($events);
        }

        $page = $this->buildPage($request);
        if (null !== $page) {
            // Keep page right after url for readability.
            $profile = array_slice($profile, 0, 5, true)
                + ['page' => $page]
                + array_slice($profile, 5, null, true);
        }

        $json = json_encode($profile, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        if (false !== $json) {
            file_put_contents($directory.'/'.$token.'.json', $json);
        }

        $this->prune($directory);
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
     * Aggregate dispatched PSR-14 events by class (count + total_ms), exposing
     * the most expensive ones — analogous to duplicate_queries.
     *
     * @param list<array{event: string, ms: float}> $events
     *
     * @return array{count: int, total_ms: float, top: list<array{event: string, count: int, total_ms: float}>}
     */
    public function aggregateEvents(array $events): array
    {
        $totalMs = 0.0;
        /** @var array<string, array{event: string, count: int, total_ms: float}> $groups */
        $groups = [];

        foreach ($events as $event) {
            $totalMs += $event['ms'];
            $name = $event['event'];
            if (!isset($groups[$name])) {
                $groups[$name] = ['event' => $name, 'count' => 0, 'total_ms' => 0.0];
            }
            ++$groups[$name]['count'];
            $groups[$name]['total_ms'] += $event['ms'];
        }

        $top = array_values($groups);
        usort($top, static fn (array $a, array $b): int => $b['total_ms'] <=> $a['total_ms']);

        return [
            'count' => count($events),
            'total_ms' => round($totalMs, 2),
            'top' => array_map(
                static fn (array $group): array => [
                    'event' => $group['event'],
                    'count' => $group['count'],
                    'total_ms' => round($group['total_ms'], 2),
                ],
                array_slice($top, 0, self::MAX_EVENTS),
            ),
        ];
    }

    /**
     * @return array{id: int, type: int}|null
     */
    private function buildPage(ServerRequestInterface $request): ?array
    {
        $pageInformation = $request->getAttribute('frontend.page.information');
        if (!$pageInformation instanceof PageInformation) {
            return null;
        }

        $type = 0;
        $routing = $request->getAttribute('routing');
        if ($routing instanceof PageArguments) {
            $type = (int) $routing->getPageType();
        }

        return ['id' => $pageInformation->getId(), 'type' => $type];
    }

    /**
     * @return array{hit: bool, cacheable: bool, disabled_reasons?: list<string>}
     */
    private function buildCache(ServerRequestInterface $request, QueryCollector $collector): array
    {
        $cacheable = true;
        $reasons = [];
        $instruction = $request->getAttribute('frontend.cache.instruction');
        if ($instruction instanceof CacheInstruction) {
            $cacheable = $instruction->isCachingAllowed();
            // Why caching is disabled (no_cache, USER_INT cObj, …) — the #1 cause
            // of slow pages. Borrowed from EXT:adminpanel's GeneralInformation
            // module; getDisabledCacheReasons() exists on v13 and v14.
            $reasons = array_values(array_filter(
                $instruction->getDisabledCacheReasons(),
                is_string(...),
            ));
        }

        // A page is only a cache hit if it is cacheable AND was not (re)generated
        // this request. Gating on cacheable resolves the no_cache caveat: such
        // pages never fire AfterCachedPageIsPersistedEvent and would otherwise be
        // misreported as a hit.
        $cache = [
            'hit' => $cacheable && !$collector->isPageGenerated(),
            'cacheable' => $cacheable,
        ];
        if ([] !== $reasons) {
            $cache['disabled_reasons'] = $reasons;
        }

        return $cache;
    }

    private function prune(string $directory): void
    {
        $keep = $this->maxProfiles();
        $files = glob($directory.'/*.json') ?: [];
        if (count($files) <= $keep) {
            return;
        }

        usort($files, static fn (string $a, string $b): int => filemtime($a) <=> filemtime($b));
        foreach (array_slice($files, 0, count($files) - $keep) as $stale) {
            @unlink($stale);
        }
    }

    /**
     * Number of profiles to retain. Configurable via TYPO3_REQUEST_PROFILER_KEEP
     * (defaults to 50); invalid or non-positive values fall back to the default.
     */
    private function maxProfiles(): int
    {
        $configured = (int) getenv('TYPO3_REQUEST_PROFILER_KEEP');

        return $configured > 0 ? $configured : self::DEFAULT_MAX_PROFILES;
    }
}
