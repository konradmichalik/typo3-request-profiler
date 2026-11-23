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

namespace KonradMichalik\Typo3RequestProfiler\Tests\Unit\Activation;

use KonradMichalik\Ttt\Attribute\{InApplicationContext, WithEnvironment};
use KonradMichalik\Typo3RequestProfiler\Activation\{ProfilerActivation, ProfilerStateService};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Log\{LogManager, Logger};
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function str_repeat;

/**
 * ProfilerActivationTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[WithEnvironment]
final class ProfilerActivationTest extends TestCase
{
    private const SECRET_ENV_VAR = 'TYPO3_REQUEST_PROFILER_SECRET';

    private LogManager&MockObject $logManager;

    protected function setUp(): void
    {
        $this->logManager = $this->createMock(LogManager::class);
    }

    protected function tearDown(): void
    {
        putenv(self::SECRET_ENV_VAR);
        GeneralUtility::purgeInstances();
    }

    #[Test]
    #[InApplicationContext('Production')]
    public function headerTriggerIsActiveWithASecretOfExactlyTheMinimumLength(): void
    {
        $secret = str_repeat('a', 32);
        putenv(self::SECRET_ENV_VAR.'='.$secret);

        self::assertTrue($this->subject()->isHeaderTriggered($this->request($secret)));
    }

    #[Test]
    #[InApplicationContext('Production')]
    #[DataProvider('tooShortSecretLengthProvider')]
    public function headerTriggerIsInactiveWithASecretBelowTheMinimumLength(int $length): void
    {
        $secret = str_repeat('a', $length);
        putenv(self::SECRET_ENV_VAR.'='.$secret);
        $this->expectLogManagerReturning($this->createMock(Logger::class));

        self::assertFalse($this->subject()->isHeaderTriggered($this->request($secret)));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function tooShortSecretLengthProvider(): iterable
    {
        yield 'one character' => [1];
        yield 'eighteen characters' => [18];
        yield 'one below the minimum' => [31];
    }

    #[Test]
    #[InApplicationContext('Production')]
    public function weakSecretWarningNamesTheRequirementButNeverTheSecret(): void
    {
        $secret = 'weak-secret-value';
        putenv(self::SECRET_ENV_VAR.'='.$secret);

        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(self::logicalAnd(
                self::stringContains(self::SECRET_ENV_VAR),
                self::stringContains('32'),
                self::logicalNot(self::stringContains($secret)),
            ));
        $this->expectLogManagerReturning($logger);

        $this->subject()->isHeaderTriggered($this->request($secret));
    }

    #[Test]
    #[InApplicationContext('Production')]
    public function weakSecretWarningIsLoggedOnlyOncePerInstance(): void
    {
        putenv(self::SECRET_ENV_VAR.'=short');

        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())->method('warning');
        $this->expectLogManagerReturning($logger);

        $subject = $this->subject();
        $subject->isHeaderTriggered($this->request('short'));
        $subject->isHeaderTriggered($this->request('short'));
    }

    #[Test]
    #[InApplicationContext('Production')]
    public function noWarningIsLoggedWithoutAHeader(): void
    {
        putenv(self::SECRET_ENV_VAR.'=short');

        $logger = $this->createMock(Logger::class);
        $logger->expects(self::never())->method('warning');
        $this->expectLogManagerReturning($logger);

        $this->subject()->isHeaderTriggered($this->request());
    }

    #[Test]
    #[InApplicationContext('Production')]
    public function noWarningIsLoggedForAnUnsetSecret(): void
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::never())->method('warning');
        $this->expectLogManagerReturning($logger);

        $this->subject()->isHeaderTriggered($this->request('anything'));
    }

    #[Test]
    #[InApplicationContext('Production')]
    public function noWarningIsLoggedForAValidSecret(): void
    {
        $secret = str_repeat('b', 40);
        putenv(self::SECRET_ENV_VAR.'='.$secret);

        $logger = $this->createMock(Logger::class);
        $logger->expects(self::never())->method('warning');
        $this->expectLogManagerReturning($logger);

        $this->subject()->isHeaderTriggered($this->request($secret));
    }

    #[Test]
    #[InApplicationContext('Development')]
    public function developmentIgnoresTheSecretAndNeverWarns(): void
    {
        putenv(self::SECRET_ENV_VAR.'=short');

        $logger = $this->createMock(Logger::class);
        $logger->expects(self::never())->method('warning');
        $this->expectLogManagerReturning($logger);

        self::assertTrue($this->subject()->isHeaderTriggered($this->request('1')));
    }

    private function subject(): ProfilerActivation
    {
        return new ProfilerActivation(new ProfilerStateService(), $this->logManager);
    }

    private function request(?string $headerValue = null): ServerRequest
    {
        $request = new ServerRequest('https://example.com/', 'GET');

        return null === $headerValue ? $request : $request->withHeader('Typo3-Profiler', $headerValue);
    }

    private function expectLogManagerReturning(Logger $logger): void
    {
        $this->logManager->method('getLogger')->willReturn($logger);
    }
}
