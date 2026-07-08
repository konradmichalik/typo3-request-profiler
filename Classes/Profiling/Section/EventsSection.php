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
use function count;

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
        $events = GeneralUtility::makeInstance(EventCollector::class)->getEvents();

        if ([] === $events) {
            return null;
        }

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
}
