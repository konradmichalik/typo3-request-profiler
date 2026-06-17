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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use TYPO3\CMS\Core\Utility\ArrayUtility;

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
