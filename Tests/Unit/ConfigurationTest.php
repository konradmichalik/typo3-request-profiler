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

use KonradMichalik\Ttt\Attribute\{InApplicationContext, WithEnvironment};
use KonradMichalik\Typo3RequestProfiler\Configuration;
use KonradMichalik\Typo3RequestProfiler\Profiling\Instrumentation\Doctrine\ProfilingDriverMiddleware;
use KonradMichalik\Typo3RequestProfiler\Profiling\Instrumentation\Log\ProfilingLogWriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use TYPO3\CMS\Core\Log\{LogManager, Logger};
use TYPO3\CMS\Core\Utility\{ArrayUtility, GeneralUtility};

use function is_array;

/**
 * ConfigurationTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[WithEnvironment]
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
        GeneralUtility::purgeInstances();
    }

    #[Test]
    #[InApplicationContext('Development')]
    public function isProfilingActiveIsTrueInDevelopmentContext(): void
    {
        self::assertTrue(Configuration::isProfilingActive());
    }

    #[Test]
    #[InApplicationContext('Production')]
    public function isProfilingActiveIsFalseOutsideDevelopmentWithoutForce(): void
    {
        self::assertFalse(Configuration::isProfilingActive());
    }

    #[Test]
    #[InApplicationContext('Production')]
    public function isProfilingActiveIsTrueOutsideDevelopmentWhenForced(): void
    {
        putenv('TYPO3_REQUEST_PROFILER_FORCE=1');

        self::assertTrue(Configuration::isProfilingActive());
    }

    #[Test]
    #[InApplicationContext('Production')]
    public function isProfilingActiveIsFalseOutsideDevelopmentWhenForceIsNotExactlyOne(): void
    {
        putenv('TYPO3_REQUEST_PROFILER_FORCE=true');

        self::assertFalse(Configuration::isProfilingActive());
    }

    #[Test]
    #[InApplicationContext('Development')]
    public function warnIfForcedOutsideDevelopmentDoesNothingInDevelopmentContext(): void
    {
        putenv('TYPO3_REQUEST_PROFILER_FORCE=1');

        $logger = $this->createMock(Logger::class);
        $logger->expects(self::never())->method('warning');
        GeneralUtility::setSingletonInstance(LogManager::class, $this->logManagerReturning($logger));

        Configuration::warnIfForcedOutsideDevelopment();
    }

    #[Test]
    #[InApplicationContext('Production')]
    public function warnIfForcedOutsideDevelopmentDoesNothingWhenNotForced(): void
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::never())->method('warning');
        GeneralUtility::setSingletonInstance(LogManager::class, $this->logManagerReturning($logger));

        Configuration::warnIfForcedOutsideDevelopment();
    }

    #[Test]
    #[InApplicationContext('Production')]
    public function warnIfForcedOutsideDevelopmentLogsWarningWhenForcedInProduction(): void
    {
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
