<?php

declare(strict_types=1);

/*
 * This file is part of the "typo3_request_profiler" TYPO3 CMS extension.
 *
 * (c) 2026 Konrad Michalik <km@move-elevator.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KonradMichalik\Typo3RequestProfiler\Profiling;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Cache\CacheInstruction;
use TYPO3\CMS\Frontend\Page\PageInformation;

/**
 * Aggregates the collected queries into a compact per-request profile and writes
 * it to var/log/profiles/{token}.json (Symfony FileProfilerStorage idea, minimal).
 *
 * The SQL normalisation and aggregation methods are intentionally free of TYPO3
 * runtime dependencies so they can be unit-tested in isolation.
 */
final class ProfileWriter
{
    private const MAX_PROFILES = 50;
    private const MAX_DUPLICATES = 20;

    public function write(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $token,
        QueryCollector $collector,
        float $totalMs,
    ): void {
        $directory = Environment::getVarPath() . '/log/profiles';
        GeneralUtility::mkdir_deep($directory);

        $stats = $this->aggregate($collector->getQueries());

        $profile = [
            'token' => $token,
            'time' => date('c'),
            'method' => $request->getMethod(),
            'url' => (string)$request->getUri(),
            'status' => $response->getStatusCode(),
            'cache' => $this->buildCache($request, $collector),
            'timing' => ['total_ms' => round($totalMs, 2)],
            'queries' => [
                'count' => $stats['count'],
                'total_ms' => $stats['total_ms'],
            ],
            'duplicate_queries' => $stats['duplicates'],
        ];

        $page = $this->buildPage($request);
        if ($page !== null) {
            // Keep page right after url for readability.
            $profile = array_slice($profile, 0, 5, true)
                + ['page' => $page]
                + array_slice($profile, 5, null, true);
        }

        $json = json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json !== false) {
            file_put_contents($directory . '/' . $token . '.json', $json);
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
        $sql = (string)preg_replace("/'(?:[^'\\\\]|\\\\.)*'/", '?', $sql);
        $sql = (string)preg_replace('/\b\d+\b/', '?', $sql);
        $sql = (string)preg_replace('/\s+/', ' ', trim($sql));

        return (string)preg_replace('/\bIN\s*\(\s*\?(?:\s*,\s*\?)*\s*\)/i', 'IN (?)', $sql);
    }

    /**
     * @param list<array{sql: string, ms: float}> $queries
     * @return array{count: int, total_ms: float, duplicates: list<array{sql: string, count: int, total_ms: float}>}
     */
    public function aggregate(array $queries): array
    {
        $totalMs = 0.0;
        /** @var array<string, array{sql: string, count: int, total_ms: float}> $groups */
        $groups = [];

        foreach ($queries as $query) {
            $totalMs += $query['ms'];
            $normalized = $this->normalizeSql($query['sql']);
            if (!isset($groups[$normalized])) {
                $groups[$normalized] = ['sql' => $normalized, 'count' => 0, 'total_ms' => 0.0];
            }
            ++$groups[$normalized]['count'];
            $groups[$normalized]['total_ms'] += $query['ms'];
        }

        $duplicates = array_values(array_filter($groups, static fn (array $group): bool => $group['count'] > 1));
        usort($duplicates, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return [
            'count' => count($queries),
            'total_ms' => round($totalMs, 2),
            'duplicates' => array_map(
                static fn (array $group): array => [
                    'sql' => $group['sql'],
                    'count' => $group['count'],
                    'total_ms' => round($group['total_ms'], 2),
                ],
                array_slice($duplicates, 0, self::MAX_DUPLICATES),
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
            $type = (int)$routing->getPageType();
        }

        return ['id' => $pageInformation->getId(), 'type' => $type];
    }

    /**
     * @return array{hit: bool, cacheable: bool}
     */
    private function buildCache(ServerRequestInterface $request, QueryCollector $collector): array
    {
        $cacheable = true;
        $instruction = $request->getAttribute('frontend.cache.instruction');
        if ($instruction instanceof CacheInstruction) {
            $cacheable = $instruction->isCachingAllowed();
        }

        // A page is only a cache hit if it is cacheable AND was not (re)generated
        // this request. Gating on cacheable resolves the no_cache caveat: such
        // pages never fire AfterCachedPageIsPersistedEvent and would otherwise be
        // misreported as a hit.
        return [
            'hit' => $cacheable && !$collector->isPageGenerated(),
            'cacheable' => $cacheable,
        ];
    }

    private function prune(string $directory): void
    {
        $files = glob($directory . '/*.json') ?: [];
        if (count($files) <= self::MAX_PROFILES) {
            return;
        }

        usort($files, static fn (string $a, string $b): int => filemtime($a) <=> filemtime($b));
        foreach (array_slice($files, 0, count($files) - self::MAX_PROFILES) as $stale) {
            @unlink($stale);
        }
    }
}
