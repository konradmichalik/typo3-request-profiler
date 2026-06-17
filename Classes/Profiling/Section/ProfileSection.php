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
 * ProfileSection.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
interface ProfileSection
{
    /**
     * The top-level key this section is written under in the profile.
     */
    public function name(): string;

    /**
     * Lower runs first; controls the key order within the profile.
     */
    public function priority(): int;

    /**
     * Opt-in sections check their environment flag here.
     */
    public function isEnabled(): bool;

    /**
     * Return the section payload, or null to omit the section entirely (e.g.
     * when there is nothing to report this request).
     *
     * @return array<mixed>|null
     */
    public function collect(ProfileContext $context): ?array;
}
