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

use function count;

/**
 * HttpSection.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class HttpSection implements ProfileSection
{
    public function name(): string
    {
        return 'http';
    }

    public function priority(): int
    {
        return 85;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * @return array{count: int, total_ms: float}|null
     */
    public function collect(ProfileContext $context): ?array
    {
        $requests = GeneralUtility::makeInstance(HttpCollector::class)->getRequests();
        if ([] === $requests) {
            return null;
        }

        $totalMs = 0.0;
        foreach ($requests as $request) {
            $totalMs += $request['ms'];
        }

        return ['count' => count($requests), 'total_ms' => round($totalMs, 2)];
    }
}
