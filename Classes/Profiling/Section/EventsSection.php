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

use KonradMichalik\Typo3RequestProfiler\Configuration;
use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\EventCollector;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function array_slice;

/**
 * EventsSection.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class EventsSection implements ProfileSection
{
    private const MAX_EVENTS = 20;

    public function name(): string
    {
        return 'events';
    }

    public function priority(): int
    {
        return 100;
    }

    public function isEnabled(): bool
    {
        return Configuration::isEnvFlagEnabled('TYPO3_REQUEST_PROFILER_EVENTS');
    }

    /**
     * @return array{count: int, total_ms: float, top: list<array{event: string, count: int, total_ms: float}>}|null
     */
    public function collect(ProfileContext $context): ?array
    {
        $groups = GeneralUtility::makeInstance(EventCollector::class)->getGroups();

        if ([] === $groups) {
            return null;
        }

        $count = 0;
        $totalMs = 0.0;
        $top = [];
        foreach ($groups as $event => $group) {
            $count += $group['count'];
            $totalMs += $group['total_ms'];
            $top[] = ['event' => $event, 'count' => $group['count'], 'total_ms' => round($group['total_ms'], 2)];
        }

        usort($top, static fn (array $a, array $b): int => $b['total_ms'] <=> $a['total_ms']);

        return [
            'count' => $count,
            'total_ms' => round($totalMs, 2),
            'top' => array_slice($top, 0, self::MAX_EVENTS),
        ];
    }
}
