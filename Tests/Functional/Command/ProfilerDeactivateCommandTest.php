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

namespace KonradMichalik\Typo3RequestProfiler\Tests\Functional\Command;

use KonradMichalik\Typo3RequestProfiler\Activation\{Duration, ProfilerStateService};
use KonradMichalik\Typo3RequestProfiler\Command\ProfilerDeactivateCommand;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * ProfilerDeactivateCommandTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ProfilerDeactivateCommandTest extends FunctionalTestCase
{
    protected bool $initializeDatabase = false;

    #[Test]
    public function deactivatesAnActiveToggle(): void
    {
        $stateService = new ProfilerStateService();
        $stateService->activate(Duration::default());
        self::assertTrue($stateService->isActive());

        $tester = new CommandTester(new ProfilerDeactivateCommand($stateService));
        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertFalse($stateService->isActive());
        self::assertStringContainsString('Profiling deactivated', $tester->getDisplay());
    }
}
