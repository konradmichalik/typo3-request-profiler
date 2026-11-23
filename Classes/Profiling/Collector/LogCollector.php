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

namespace KonradMichalik\Typo3RequestProfiler\Profiling\Collector;

use TYPO3\CMS\Core\SingletonInterface;

/**
 * LogCollector.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class LogCollector implements SingletonInterface
{
    /**
     * @var array<string, array<string, int>>
     */
    private array $counts = [];

    public function record(string $level, string $component): void
    {
        $this->counts[$level][$component] = ($this->counts[$level][$component] ?? 0) + 1;
    }

    /**
     * @return array<string, array<string, int>> count per level and component
     */
    public function getCounts(): array
    {
        return $this->counts;
    }
}
