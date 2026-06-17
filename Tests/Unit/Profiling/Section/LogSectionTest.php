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

namespace KonradMichalik\Typo3RequestProfiler\Tests\Unit\Profiling\Section;

use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\LogCollector;
use KonradMichalik\Typo3RequestProfiler\Profiling\Section\{LogSection, ProfileContext};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Http\{Response, ServerRequest};
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * LogSectionTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class LogSectionTest extends TestCase
{
    private LogSection $subject;

    private LogCollector $collector;

    protected function setUp(): void
    {
        $this->subject = new LogSection();
        $this->collector = new LogCollector();
        GeneralUtility::setSingletonInstance(LogCollector::class, $this->collector);
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
    }

    #[Test]
    public function isNamedLogAndAlwaysEnabled(): void
    {
        self::assertSame('log', $this->subject->name());
        self::assertTrue($this->subject->isEnabled());
    }

    #[Test]
    public function collectReturnsNullWhenNoRecordsWereCollected(): void
    {
        self::assertNull($this->subject->collect($this->context()));
    }

    #[Test]
    public function collectCountsRecordsAndGroupsByLevel(): void
    {
        $this->collector->record('warning', 'A');
        $this->collector->record('warning', 'A');
        $this->collector->record('error', 'B');
        $this->collector->record('notice', 'C');

        $result = $this->subject->collect($this->context());

        self::assertNotNull($result);
        self::assertSame(4, $result['count']);
        self::assertSame(['error' => 1, 'warning' => 2, 'notice' => 1], $result['by_level']);
    }

    #[Test]
    public function collectOrdersLevelsBySeverityDescending(): void
    {
        $this->collector->record('debug', 'A');
        $this->collector->record('emergency', 'B');
        $this->collector->record('warning', 'C');

        $result = $this->subject->collect($this->context());

        self::assertNotNull($result);
        self::assertSame(['emergency', 'warning', 'debug'], array_keys($result['by_level']));
    }

    #[Test]
    public function collectReturnsTopComponentsSortedByCountDescending(): void
    {
        $this->collector->record('warning', 'Noisy');
        $this->collector->record('warning', 'Noisy');
        $this->collector->record('warning', 'Noisy');
        $this->collector->record('notice', 'Quiet');

        $result = $this->subject->collect($this->context());

        self::assertNotNull($result);
        self::assertSame(
            [
                ['component' => 'Noisy', 'count' => 3],
                ['component' => 'Quiet', 'count' => 1],
            ],
            $result['top_components'],
        );
    }

    #[Test]
    public function collectCapsTopComponentsAtTen(): void
    {
        for ($i = 0; $i < 15; ++$i) {
            $this->collector->record('notice', 'Component'.$i);
        }

        $result = $this->subject->collect($this->context());

        self::assertNotNull($result);
        self::assertSame(15, $result['count']);
        self::assertCount(10, $result['top_components']);
    }

    private function context(): ProfileContext
    {
        return new ProfileContext(new ServerRequest('https://example.com/', 'GET'), new Response(), 'tok', 1.0);
    }
}
