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

use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\HttpCollector;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function array_slice;

/**
 * SlowHttpSection.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class SlowHttpSection implements ProfileSection
{
    private const MAX_SLOW_REQUESTS = 5;

    public function name(): string
    {
        return 'slow_http';
    }

    public function priority(): int
    {
        return 87;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * @return list<array{method: string, url: string, ms: float, status?: int}>|null
     */
    public function collect(ProfileContext $context): ?array
    {
        $requests = GeneralUtility::makeInstance(HttpCollector::class)->getRequests();
        if ([] === $requests) {
            return null;
        }

        usort($requests, static fn (array $a, array $b): int => $b['ms'] <=> $a['ms']);

        return array_map(
            static function (array $request): array {
                $entry = [
                    'method' => $request['method'],
                    'url' => $request['url'],
                    'ms' => round($request['ms'], 2),
                ];
                if (null !== $request['status']) {
                    $entry['status'] = $request['status'];
                }

                return $entry;
            },
            array_slice($requests, 0, self::MAX_SLOW_REQUESTS),
        );
    }
}
