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

use function ini_get;

/**
 * MemorySection.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class MemorySection implements ProfileSection
{
    public function name(): string
    {
        return 'memory';
    }

    public function priority(): int
    {
        return 40;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * @return array{peak_mb: float, limit_mb: float|null, peak_pct?: float}
     */
    public function collect(ProfileContext $context): array
    {
        $peakBytes = memory_get_peak_usage(true);
        $limitMb = $this->limitInMb(ini_get('memory_limit'));

        $memory = [
            'peak_mb' => round($peakBytes / 1048576, 1),
            'limit_mb' => $limitMb,
        ];

        if (null !== $limitMb && $limitMb > 0.0) {
            $memory['peak_pct'] = round($peakBytes / 1048576 / $limitMb * 100, 1);
        }

        return $memory;
    }

    /**
     * Normalises PHP's shorthand memory_limit notation (e.g. "512M", "1G",
     * "262144K", case-insensitive) to megabytes. "-1" means unlimited and has
     * no meaningful megabyte value.
     */
    private function limitInMb(string $rawLimit): ?float
    {
        $rawLimit = trim($rawLimit);
        if ('' === $rawLimit || '-1' === $rawLimit) {
            return null;
        }

        $unit = strtoupper(substr($rawLimit, -1));
        $number = (float) $rawLimit;

        $bytes = match ($unit) {
            'G' => $number * 1024 * 1024 * 1024,
            'M' => $number * 1024 * 1024,
            'K' => $number * 1024,
            default => $number,
        };

        return round($bytes / 1048576, 1);
    }
}
