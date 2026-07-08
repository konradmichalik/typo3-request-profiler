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

namespace KonradMichalik\Typo3RequestProfiler\Tests\Unit;

use KonradMichalik\Typo3RequestProfiler\Configuration;
use KonradMichalik\Typo3RequestProfiler\Profiling\Instrumentation\Doctrine\ProfilingDriverMiddleware;
use KonradMichalik\Typo3RequestProfiler\Profiling\Instrumentation\Log\ProfilingLogWriter;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use TYPO3\CMS\Core\Core\{ApplicationContext, Environment};
use TYPO3\CMS\Core\Log\{LogManager, Logger};
use TYPO3\CMS\Core\Utility\{ArrayUtility, GeneralUtility};

use function is_array;

/**
 * ConfigurationTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ConfigurationTest extends TestCase
{
    /**
     * @var array<mixed>
     */
    private array $backup = [];

    protected function setUp(): void
    {
        $existing = $GLOBALS['TYPO3_CONF_VARS'] ?? [];
        $this->backup = is_array($existing) ? $existing : [];
        $GLOBALS['TYPO3_CONF_VARS'] = [];
    }

    protected function tearDown(): void
    {
        $GLOBALS['TYPO3_CONF_VARS'] = $this->backup;
        putenv('TYPO3_REQUEST_PROFILER_FORCE');
        putenv('TYPO3_REQUEST_PROFILER_TEST_FLAG');
        GeneralUtility::purgeInstances();
    }

    #[Test]
    #[DataProvider('envFlagProvider')]
    public function isEnvFlagEnabledTreatsAnyNonEmptyNonZeroValueAsEnabled(string|false $value, bool $expected): void
    {
        if (false === $value) {
            putenv('TYPO3_REQUEST_PROFILER_TEST_FLAG');
        } else {
            putenv('TYPO3_REQUEST_PROFILER_TEST_FLAG='.$value);
        }

        self::assertSame($expected, Configuration::isEnvFlagEnabled('TYPO3_REQUEST_PROFILER_TEST_FLAG'));
    }

    /**
     * @return iterable<string, array{string|false, bool}>
     */
    public static function envFlagProvider(): iterable
    {
        yield 'unset' => [false, false];
        yield 'zero' => ['0', false];
        yield 'empty' => ['', false];
        yield 'one' => ['1', true];
        yield 'truthy' => ['yes', true];
    }

    #[Test]
    public function isProfilingActiveIsTrueInDevelopmentContext(): void
    {
        $this->reinitialiseContext('Development');

        self::assertTrue(Configuration::isProfilingActive());
    }

    #[Test]
    public function isProfilingActiveIsFalseOutsideDevelopmentWithoutForce(): void
    {
        $this->reinitialiseContext('Production');

        self::assertFalse(Configuration::isProfilingActive());
    }

    #[Test]
    public function isProfilingActiveIsTrueOutsideDevelopmentWhenForced(): void
    {
        $this->reinitialiseContext('Production');
        putenv('TYPO3_REQUEST_PROFILER_FORCE=1');

        self::assertTrue(Configuration::isProfilingActive());
    }

    #[Test]
    public function isProfilingActiveIsFalseOutsideDevelopmentWhenForceIsNotExactlyOne(): void
    {
        $this->reinitialiseContext('Production');
        putenv('TYPO3_REQUEST_PROFILER_FORCE=true');

        self::assertFalse(Configuration::isProfilingActive());
    }

    #[Test]
    public function warnIfForcedOutsideDevelopmentDoesNothingInDevelopmentContext(): void
    {
        $this->reinitialiseContext('Development');
        putenv('TYPO3_REQUEST_PROFILER_FORCE=1');

        $logger = $this->createMock(Logger::class);
        $logger->expects(self::never())->method('warning');
        GeneralUtility::setSingletonInstance(LogManager::class, $this->logManagerReturning($logger));

        Configuration::warnIfForcedOutsideDevelopment();
    }

    #[Test]
    public function warnIfForcedOutsideDevelopmentDoesNothingWhenNotForced(): void
    {
        $this->reinitialiseContext('Production');

        $logger = $this->createMock(Logger::class);
        $logger->expects(self::never())->method('warning');
        GeneralUtility::setSingletonInstance(LogManager::class, $this->logManagerReturning($logger));

        Configuration::warnIfForcedOutsideDevelopment();
    }

    #[Test]
    public function warnIfForcedOutsideDevelopmentLogsWarningWhenForcedInProduction(): void
    {
        $this->reinitialiseContext('Production');
        putenv('TYPO3_REQUEST_PROFILER_FORCE=1');

        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(self::stringContains('TYPO3_REQUEST_PROFILER_FORCE'));
        GeneralUtility::setSingletonInstance(LogManager::class, $this->logManagerReturning($logger));

        Configuration::warnIfForcedOutsideDevelopment();
    }

    #[Test]
    public function registerProfilingDriverMiddlewareKeepsTheSlashedIdentifierIntact(): void
    {
        Configuration::registerProfilingDriverMiddleware();

        $middlewares = $this->confVarsValue(['DB', 'Connections', 'Default', 'driverMiddlewares']);

        self::assertArrayHasKey(Configuration::EXT_KEY.'/profiling', $middlewares);
        $entry = $middlewares[Configuration::EXT_KEY.'/profiling'];
        self::assertIsArray($entry);
        self::assertSame(ProfilingDriverMiddleware::class, $entry['target']);
    }

    #[Test]
    public function registerProfilingDriverMiddlewarePreservesExistingMiddlewares(): void
    {
        $GLOBALS['TYPO3_CONF_VARS'] = ArrayUtility::setValueByPath(
            $this->backup,
            ['DB', 'Connections', 'Default', 'driverMiddlewares', 'vendor/existing'],
            ['target' => 'X'],
        );

        Configuration::registerProfilingDriverMiddleware();

        $middlewares = $this->confVarsValue(['DB', 'Connections', 'Default', 'driverMiddlewares']);

        self::assertArrayHasKey('vendor/existing', $middlewares);
        self::assertArrayHasKey(Configuration::EXT_KEY.'/profiling', $middlewares);
    }

    #[Test]
    public function registerProfilingLogWriterAddsWriterAtDebugLevel(): void
    {
        Configuration::registerProfilingLogWriter();

        $writers = $this->confVarsValue(['LOG', 'writerConfiguration', LogLevel::DEBUG]);

        self::assertArrayHasKey(ProfilingLogWriter::class, $writers);
    }

    #[Test]
    public function registrationIsANoOpWhenConfVarsIsNotAnArray(): void
    {
        $GLOBALS['TYPO3_CONF_VARS'] = 'not-an-array';

        Configuration::registerProfilingLogWriter();

        self::assertSame('not-an-array', $GLOBALS['TYPO3_CONF_VARS']);
    }

    /**
     * The unit bootstrap does not initialise the Environment, so set up the
     * requested application context with throwaway paths.
     */
    private function reinitialiseContext(string $context): void
    {
        Environment::initialize(
            new ApplicationContext($context),
            true,
            true,
            '/tmp',
            '/tmp',
            '/tmp',
            '/tmp',
            '/tmp/cli',
            'UNIX',
        );
    }

    private function logManagerReturning(Logger $logger): LogManager
    {
        $logManager = $this->createMock(LogManager::class);
        $logManager->method('getLogger')->willReturn($logger);

        return $logManager;
    }

    /**
     * @param list<string> $path
     *
     * @return array<mixed>
     */
    private function confVarsValue(array $path): array
    {
        $confVars = $GLOBALS['TYPO3_CONF_VARS'] ?? [];
        self::assertIsArray($confVars);

        $value = ArrayUtility::getValueByPath($confVars, $path);
        self::assertIsArray($value);

        return $value;
    }
}
