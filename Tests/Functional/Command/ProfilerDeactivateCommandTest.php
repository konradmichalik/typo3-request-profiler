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
use TYPO3\CMS\Core\Console\CommandRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

use function dirname;

/**
 * ProfilerDeactivateCommandTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ProfilerDeactivateCommandTest extends FunctionalTestCase
{
    protected bool $initializeDatabase = false;

    // See ProfilerActivateCommandTest: only the registration test below needs
    // the extension activated as a package.
    protected array $testExtensionsToLoad = ['typo3_request_profiler'];

    protected function tearDown(): void
    {
        $directory = dirname(ProfilerStateService::filePath());
        // The failure test below deliberately locks this directory down;
        // restore it before the parent's own cleanup tries to remove it.
        @chmod($directory, 0o700);
        (new ProfilerStateService())->deactivate();
        parent::tearDown();
    }

    #[Test]
    public function isRegisteredWithTheConsoleCommandRegistry(): void
    {
        self::assertTrue(GeneralUtility::makeInstance(CommandRegistry::class)->has('profiler:deactivate'));
    }

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

    #[Test]
    public function reportsFailureWhenTheStateFileCannotBeRemoved(): void
    {
        $stateService = new ProfilerStateService();
        $stateService->activate(Duration::default());
        $directory = dirname(ProfilerStateService::filePath());
        chmod($directory, 0o500);

        $tester = new CommandTester(new ProfilerDeactivateCommand($stateService));
        $exitCode = $tester->execute([]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Failed to remove', $tester->getDisplay());
    }
}
