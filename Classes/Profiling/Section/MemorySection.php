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
     * @return array{peak_mb: float}
     */
    public function collect(ProfileContext $context): array
    {
        return ['peak_mb' => round(memory_get_peak_usage(true) / 1048576, 1)];
    }
}
