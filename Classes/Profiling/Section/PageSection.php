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

use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Frontend\Page\PageInformation;

/**
 * PageSection.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class PageSection implements ProfileSection
{
    public function name(): string
    {
        return 'page';
    }

    public function priority(): int
    {
        return 10;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    /**
     * @return array{id: int, type: int}|null
     */
    public function collect(ProfileContext $context): ?array
    {
        $pageInformation = $context->request->getAttribute('frontend.page.information');
        if (!$pageInformation instanceof PageInformation) {
            return null;
        }

        $type = 0;
        $routing = $context->request->getAttribute('routing');
        if ($routing instanceof PageArguments) {
            $type = (int) $routing->getPageType();
        }

        return ['id' => $pageInformation->getId(), 'type' => $type];
    }
}
