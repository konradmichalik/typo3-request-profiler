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

use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\LogCollector;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function array_slice;
use function count;

/**
 * LogSection.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class LogSection implements ProfileSection
{
    private const MAX_COMPONENTS = 10;

    /**
     * PSR-3 levels from most to least severe, used to order the per-level
     * breakdown so the most important entries surface first.
     */
    private const LEVEL_SEVERITY = [
        'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug',
    ];

    public function name(): string
    {
        return 'log';
    }

    public function priority(): int
    {
        return 90;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * @return array{count: int, by_level: array<string, int>, top_components: list<array{component: string, count: int}>}|null
     */
    public function collect(ProfileContext $context): ?array
    {
        $records = GeneralUtility::makeInstance(LogCollector::class)->getRecords();

        // A clean request stays free of an empty section, matching the no-noise stance.
        if ([] === $records) {
            return null;
        }

        /** @var array<string, int> $levelCounts */
        $levelCounts = [];
        /** @var array<string, int> $componentCounts */
        $componentCounts = [];

        foreach ($records as $record) {
            $levelCounts[$record['level']] = ($levelCounts[$record['level']] ?? 0) + 1;
            $componentCounts[$record['component']] = ($componentCounts[$record['component']] ?? 0) + 1;
        }

        $byLevel = [];
        foreach (self::LEVEL_SEVERITY as $level) {
            if (isset($levelCounts[$level])) {
                $byLevel[$level] = $levelCounts[$level];
            }
        }

        arsort($componentCounts);
        $topComponents = [];
        foreach (array_slice($componentCounts, 0, self::MAX_COMPONENTS, true) as $component => $count) {
            $topComponents[] = ['component' => $component, 'count' => $count];
        }

        return [
            'count' => count($records),
            'by_level' => $byLevel,
            'top_components' => $topComponents,
        ];
    }
}
