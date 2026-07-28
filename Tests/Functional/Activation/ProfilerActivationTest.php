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

use KonradMichalik\Typo3RequestProfiler\Activation\{ActivationMode, Duration, ProfilerActivation, ProfilerStateService};
use KonradMichalik\Typo3RequestProfiler\Tests\Functional\DevelopmentContextTrait;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * ProfilerActivationTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ProfilerActivationTest extends FunctionalTestCase
{
    use DevelopmentContextTrait;

    protected bool $initializeDatabase = false;

    private ProfilerStateService $stateService;

    private ProfilerActivation $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateService = new ProfilerStateService();
        $this->subject = new ProfilerActivation($this->stateService);
    }

    protected function tearDown(): void
    {
        putenv('TYPO3_REQUEST_PROFILER');
        $this->stateService->deactivate();
        parent::tearDown();
    }

    #[Test]
    public function decideReturnsNoneOutsideDevelopmentWithoutStateFile(): void
    {
        // The functional bootstrap already runs in the "Testing" context.
        self::assertSame(ActivationMode::None, $this->subject->decide());
    }

    #[Test]
    public function decideReturnsContextInDevelopment(): void
    {
        $this->inDevelopmentContext(function (): void {
            self::assertSame(ActivationMode::Context, $this->subject->decide());
        });
    }

    #[Test]
    public function decideReturnsStateFileWhenToggleIsActiveOutsideDevelopment(): void
    {
        $this->stateService->activate(Duration::fromString('1m'));

        self::assertSame(ActivationMode::StateFile, $this->subject->decide());
    }

    #[Test]
    public function decideReturnsNoneWhenKillSwitchIsSetEvenWithStateFileActive(): void
    {
        $this->stateService->activate(Duration::fromString('1m'));
        putenv('TYPO3_REQUEST_PROFILER=0');

        self::assertSame(ActivationMode::None, $this->subject->decide());
    }

    #[Test]
    public function decideReturnsNoneWhenKillSwitchIsSetEvenInDevelopment(): void
    {
        putenv('TYPO3_REQUEST_PROFILER=0');

        $this->inDevelopmentContext(function (): void {
            self::assertSame(ActivationMode::None, $this->subject->decide());
        });
    }

    #[Test]
    public function decidePrefersStateFileOverContext(): void
    {
        $this->stateService->activate(Duration::fromString('1m'));

        $this->inDevelopmentContext(function (): void {
            self::assertSame(ActivationMode::StateFile, $this->subject->decide());
        });
    }
}
