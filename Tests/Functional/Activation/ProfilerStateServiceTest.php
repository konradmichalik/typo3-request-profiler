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

namespace KonradMichalik\Typo3RequestProfiler\Tests\Functional\Activation;

use KonradMichalik\Typo3RequestProfiler\Activation\{Duration, ProfilerStateService};
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

use function dirname;

/**
 * ProfilerStateServiceTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ProfilerStateServiceTest extends FunctionalTestCase
{
    protected bool $initializeDatabase = false;

    private ProfilerStateService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new ProfilerStateService();
    }

    protected function tearDown(): void
    {
        // A couple of tests deliberately leave a directory behind at the state
        // file (or its .tmp sibling) to force a write/rename failure; plain
        // deactivate() only unlinks a file, so clean up defensively here.
        $this->removePath(ProfilerStateService::filePath());
        $this->removePath(ProfilerStateService::filePath().'.tmp');
        parent::tearDown();
    }

    #[Test]
    public function isActiveIsFalseWithoutAnyStateFile(): void
    {
        self::assertFalse($this->subject->isActive());
    }

    #[Test]
    public function activateMakesIsActiveTrueUntilExpiry(): void
    {
        $expiresAt = $this->subject->activate(Duration::fromString('1m'));

        self::assertTrue($this->subject->isActive());
        self::assertGreaterThan(time(), $expiresAt);
    }

    #[Test]
    public function isActiveIsFalseOnceTheStateFileHasExpired(): void
    {
        $this->subject->activate(Duration::fromString('1s'));

        // Backdate the file below the expiry threshold instead of sleeping.
        file_put_contents(ProfilerStateService::filePath(), json_encode(['expiresAt' => time() - 1]));

        self::assertFalse($this->subject->isActive());
    }

    #[Test]
    public function deactivateRemovesTheStateFile(): void
    {
        $this->subject->activate(Duration::default());
        self::assertFileExists(ProfilerStateService::filePath());

        $this->subject->deactivate();

        self::assertFileDoesNotExist(ProfilerStateService::filePath());
        self::assertFalse($this->subject->isActive());
    }

    #[Test]
    public function deactivateIsANoOpWhenNoStateFileExists(): void
    {
        $this->subject->deactivate();

        self::assertFalse($this->subject->isActive());
    }

    #[Test]
    public function isActiveIsFalseWhenStateFileContainsMalformedJson(): void
    {
        GeneralUtility::mkdir_deep(dirname(ProfilerStateService::filePath()));
        file_put_contents(ProfilerStateService::filePath(), 'not-json');

        self::assertFalse($this->subject->isActive());
    }

    #[Test]
    public function isActiveIsFalseWhenStateFileIsMissingTheExpiryKey(): void
    {
        GeneralUtility::mkdir_deep(dirname(ProfilerStateService::filePath()));
        file_put_contents(ProfilerStateService::filePath(), json_encode(['foo' => 'bar']));

        self::assertFalse($this->subject->isActive());
    }

    #[Test]
    public function activateSkipsWhenTemporaryFileCannotBeWritten(): void
    {
        $target = ProfilerStateService::filePath();
        GeneralUtility::mkdir_deep(dirname($target));
        // A directory at the temp path makes the temp write fail.
        GeneralUtility::mkdir_deep($target.'.tmp');

        $this->subject->activate(Duration::default());

        self::assertFileDoesNotExist($target);
    }

    #[Test]
    public function activateCleansUpTemporaryFileWhenRenameFails(): void
    {
        $target = ProfilerStateService::filePath();
        GeneralUtility::mkdir_deep(dirname($target));
        // A directory occupying the target path makes the atomic rename fail.
        GeneralUtility::mkdir_deep($target);

        $this->subject->activate(Duration::default());

        self::assertFileDoesNotExist($target.'.tmp');
    }

    private function removePath(string $path): void
    {
        if (is_dir($path)) {
            GeneralUtility::rmdir($path, true);
        } else {
            @unlink($path);
        }
    }
}
