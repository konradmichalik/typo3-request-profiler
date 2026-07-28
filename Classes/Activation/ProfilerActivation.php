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

namespace KonradMichalik\Typo3RequestProfiler\Activation;

use KonradMichalik\Typo3RequestProfiler\Configuration;

use function getenv;

/**
 * ProfilerActivation.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class ProfilerActivation
{
    public function __construct(
        private ProfilerStateService $stateService,
    ) {}

    public function decide(): ActivationMode
    {
        if ('0' === getenv('TYPO3_REQUEST_PROFILER')) {
            return ActivationMode::None;
        }

        if ($this->stateService->isActive()) {
            return ActivationMode::StateFile;
        }

        if (Configuration::isProfilingActive()) {
            return ActivationMode::Context;
        }

        return ActivationMode::None;
    }
}
