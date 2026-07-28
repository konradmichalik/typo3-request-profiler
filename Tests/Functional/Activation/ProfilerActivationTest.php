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
use TYPO3\CMS\Core\Http\ServerRequest;
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
        putenv('TYPO3_REQUEST_PROFILER_SECRET');
        $this->stateService->deactivate();
        parent::tearDown();
    }

    #[Test]
    public function decideReturnsNoneOutsideDevelopmentWithoutStateFile(): void
    {
        // The functional bootstrap already runs in the "Testing" context.
        self::assertSame(ActivationMode::None, $this->subject->decide($this->request()));
    }

    #[Test]
    public function decideReturnsContextInDevelopment(): void
    {
        $this->inDevelopmentContext(function (): void {
            self::assertSame(ActivationMode::Context, $this->subject->decide($this->request()));
        });
    }

    #[Test]
    public function decideReturnsStateFileWhenToggleIsActiveOutsideDevelopment(): void
    {
        $this->stateService->activate(Duration::fromString('1m'));

        self::assertSame(ActivationMode::StateFile, $this->subject->decide($this->request()));
    }

    #[Test]
    public function decideReturnsNoneWhenKillSwitchIsSetEvenWithStateFileActive(): void
    {
        $this->stateService->activate(Duration::fromString('1m'));
        putenv('TYPO3_REQUEST_PROFILER=0');

        self::assertSame(ActivationMode::None, $this->subject->decide($this->request()));
    }

    #[Test]
    public function decideReturnsNoneWhenKillSwitchIsSetEvenInDevelopment(): void
    {
        putenv('TYPO3_REQUEST_PROFILER=0');

        $this->inDevelopmentContext(function (): void {
            self::assertSame(ActivationMode::None, $this->subject->decide($this->request()));
        });
    }

    #[Test]
    public function decidePrefersStateFileOverContext(): void
    {
        $this->stateService->activate(Duration::fromString('1m'));

        $this->inDevelopmentContext(function (): void {
            self::assertSame(ActivationMode::StateFile, $this->subject->decide($this->request()));
        });
    }

    #[Test]
    public function decideReturnsNoneOutsideDevelopmentWhenHeaderIsPresentButNoSecretIsConfigured(): void
    {
        self::assertSame(ActivationMode::None, $this->subject->decide($this->request('anything')));
    }

    #[Test]
    public function decideReturnsHeaderOutsideDevelopmentWhenTokenMatchesTheConfiguredSecret(): void
    {
        putenv('TYPO3_REQUEST_PROFILER_SECRET=s3cr3t-token-value');

        self::assertSame(
            ActivationMode::Header,
            $this->subject->decide($this->request('s3cr3t-token-value')),
        );
    }

    #[Test]
    public function decideReturnsNoneOutsideDevelopmentWhenTokenDoesNotMatchTheConfiguredSecret(): void
    {
        putenv('TYPO3_REQUEST_PROFILER_SECRET=s3cr3t-token-value');

        self::assertSame(ActivationMode::None, $this->subject->decide($this->request('wrong-token')));
    }

    #[Test]
    public function decidePrefersContextOverHeaderTrigger(): void
    {
        // decide()'s own header fallback never actually wins in Development
        // (Context already covers it) — isHeaderTriggered() below is the
        // separate, always-checked signal the middleware uses for the
        // response's correlation header regardless of which mode "won" here.
        putenv('TYPO3_REQUEST_PROFILER_SECRET=s3cr3t-token-value');

        $this->inDevelopmentContext(function (): void {
            self::assertSame(
                ActivationMode::Context,
                $this->subject->decide($this->request('s3cr3t-token-value')),
            );
        });
    }

    #[Test]
    public function isHeaderTriggeredIsTrueInDevelopmentWithAnyNonZeroValue(): void
    {
        $this->inDevelopmentContext(function (): void {
            self::assertTrue($this->subject->isHeaderTriggered($this->request('1')));
        });
    }

    #[Test]
    public function isHeaderTriggeredIsFalseInDevelopmentWhenValueIsZero(): void
    {
        $this->inDevelopmentContext(function (): void {
            self::assertFalse($this->subject->isHeaderTriggered($this->request('0')));
        });
    }

    #[Test]
    public function isHeaderTriggeredIsFalseWhenNoHeaderWasSent(): void
    {
        $this->inDevelopmentContext(function (): void {
            self::assertFalse($this->subject->isHeaderTriggered($this->request()));
        });
    }

    #[Test]
    public function isHeaderTriggeredIsFalseOutsideDevelopmentWithoutAConfiguredSecret(): void
    {
        self::assertFalse($this->subject->isHeaderTriggered($this->request('anything')));
    }

    #[Test]
    public function isHeaderTriggeredIsTrueOutsideDevelopmentWhenTokenMatchesTheSecret(): void
    {
        putenv('TYPO3_REQUEST_PROFILER_SECRET=s3cr3t-token-value');

        self::assertTrue($this->subject->isHeaderTriggered($this->request('s3cr3t-token-value')));
    }

    #[Test]
    public function isHeaderTriggeredIsFalseOutsideDevelopmentWhenTokenDoesNotMatch(): void
    {
        putenv('TYPO3_REQUEST_PROFILER_SECRET=s3cr3t-token-value');

        self::assertFalse($this->subject->isHeaderTriggered($this->request('wrong-token')));
    }

    #[Test]
    public function isHeaderTriggeredStaysTrueEvenWhenContextAlreadyActivatedProfiling(): void
    {
        putenv('TYPO3_REQUEST_PROFILER_SECRET=s3cr3t-token-value');

        $this->inDevelopmentContext(function (): void {
            self::assertTrue($this->subject->isHeaderTriggered($this->request('s3cr3t-token-value')));
        });
    }

    private function request(?string $headerValue = null): ServerRequest
    {
        $request = new ServerRequest('https://example.com/', 'GET');

        return null === $headerValue ? $request : $request->withHeader('Typo3-Profiler', $headerValue);
    }
}
