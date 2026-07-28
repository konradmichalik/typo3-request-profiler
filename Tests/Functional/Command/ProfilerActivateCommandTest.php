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

use KonradMichalik\Typo3RequestProfiler\Activation\ProfilerStateService;
use KonradMichalik\Typo3RequestProfiler\Command\ProfilerActivateCommand;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

use function dirname;

/**
 * ProfilerActivateCommandTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ProfilerActivateCommandTest extends FunctionalTestCase
{
    protected bool $initializeDatabase = false;

    private ProfilerStateService $stateService;

    protected function tearDown(): void
    {
        // The failure test below deliberately locks this directory down;
        // restore it before deactivate()'s own unlink attempt.
        @chmod(dirname(ProfilerStateService::filePath()), 0o700);
        $this->stateService->deactivate();
        parent::tearDown();
    }

    #[Test]
    public function activatesWithTheDefaultDuration(): void
    {
        $tester = $this->commandTester();

        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertTrue($this->stateService->isActive());
        self::assertStringContainsString('Profiling activated until', $tester->getDisplay());
    }

    #[Test]
    public function activatesWithACustomDuration(): void
    {
        $tester = $this->commandTester();

        $exitCode = $tester->execute(['--duration' => '2h']);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('7200 seconds', $tester->getDisplay());
    }

    #[Test]
    public function failsWithAnInvalidDuration(): void
    {
        $tester = $this->commandTester();

        $exitCode = $tester->execute(['--duration' => 'not-a-duration']);

        self::assertSame(1, $exitCode);
        self::assertFalse($this->stateService->isActive());
        self::assertStringContainsString('Invalid duration', $tester->getDisplay());
    }

    #[Test]
    public function reportsFailureWhenTheStateFileCannotBeWritten(): void
    {
        $tester = $this->commandTester();
        $directory = dirname(ProfilerStateService::filePath());
        GeneralUtility::mkdir_deep($directory);
        chmod($directory, 0o500);

        $exitCode = $tester->execute([]);

        self::assertSame(1, $exitCode);
        self::assertFalse($this->stateService->isActive());
        self::assertStringContainsString('Failed to write', $tester->getDisplay());
    }

    private function commandTester(): CommandTester
    {
        $this->stateService = new ProfilerStateService();

        return new CommandTester(new ProfilerActivateCommand($this->stateService));
    }
}
