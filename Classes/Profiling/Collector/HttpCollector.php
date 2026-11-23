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

use function count;

/**
 * HttpCollector.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class HttpCollector implements SingletonInterface
{
    public const MAX_ENTRIES = QueryCollector::MAX_ENTRIES;

    /**
     * @var list<array{method: string, url: string, ms: float, status: int|null}>
     */
    private array $requests = [];

    private int $omittedCount = 0;

    private float $omittedMs = 0.0;

    /**
     * $status is null when the call never received a response (e.g. a
     * connect timeout).
     */
    public function addRequest(string $method, string $url, float $ms, ?int $status): void
    {
        if (count($this->requests) >= self::MAX_ENTRIES) {
            ++$this->omittedCount;
            $this->omittedMs += $ms;

            return;
        }

        $this->requests[] = ['method' => $method, 'url' => $url, 'ms' => $ms, 'status' => $status];
    }

    /**
     * @return list<array{method: string, url: string, ms: float, status: int|null}>
     */
    public function getRequests(): array
    {
        return $this->requests;
    }

    public function getOmittedCount(): int
    {
        return $this->omittedCount;
    }

    public function getOmittedMs(): float
    {
        return $this->omittedMs;
    }

    public function isTruncated(): bool
    {
        return $this->omittedCount > 0;
    }
}
