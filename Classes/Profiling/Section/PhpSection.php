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

use function count;

/**
 * PhpSection.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class PhpSection implements ProfileSection
{
    public function name(): string
    {
        return 'php';
    }

    public function priority(): int
    {
        return 50;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * @return array{included_files: int}
     */
    public function collect(ProfileContext $context): array
    {
        return ['included_files' => count(get_included_files())];
    }
}
