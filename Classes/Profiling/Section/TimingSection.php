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
 * TimingSection.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class TimingSection implements ProfileSection
{
    public function name(): string
    {
        return 'timing';
    }

    public function priority(): int
    {
        return 30;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * @return array{total_ms: float}
     */
    public function collect(ProfileContext $context): array
    {
        return ['total_ms' => round($context->totalMs, 2)];
    }
}
