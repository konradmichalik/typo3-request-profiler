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
     * @var list<array{level: string, component: string}>
     */
    private array $records = [];

    public function record(string $level, string $component): void
    {
        $this->records[] = ['level' => $level, 'component' => $component];
    }

    /**
     * @return list<array{level: string, component: string}>
     */
    public function getRecords(): array
    {
        return $this->records;
    }
}
