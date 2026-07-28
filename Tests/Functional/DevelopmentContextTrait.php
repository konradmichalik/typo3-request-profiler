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

namespace KonradMichalik\Typo3RequestProfiler\Tests\Functional;

use TYPO3\CMS\Core\Core\{ApplicationContext, Environment};

/**
 * DevelopmentContextTrait.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
trait DevelopmentContextTrait
{
    private function inDevelopmentContext(callable $callback): void
    {
        $this->inApplicationContext('Development', $callback);
    }

    private function inApplicationContext(string $contextName, callable $callback): void
    {
        $previous = Environment::getContext();
        $this->reinitialiseContext(new ApplicationContext($contextName));

        try {
            $callback();
        } finally {
            $this->reinitialiseContext($previous);
        }
    }

    private function reinitialiseContext(ApplicationContext $context): void
    {
        Environment::initialize(
            $context,
            Environment::isCli(),
            Environment::isComposerMode(),
            Environment::getProjectPath(),
            Environment::getPublicPath(),
            Environment::getVarPath(),
            Environment::getConfigPath(),
            Environment::getCurrentScript(),
            Environment::isWindows() ? 'WINDOWS' : 'UNIX',
        );
    }
}
