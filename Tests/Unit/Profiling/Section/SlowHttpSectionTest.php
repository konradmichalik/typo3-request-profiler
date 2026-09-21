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

use KonradMichalik\Ttt\Attribute\WithSingleton;
use KonradMichalik\Ttt\Http\Requests;
use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\HttpCollector;
use KonradMichalik\Typo3RequestProfiler\Profiling\Section\{ProfileContext, SlowHttpSection};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * SlowHttpSectionTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[WithSingleton(HttpCollector::class, HttpCollector::class)]
final class SlowHttpSectionTest extends TestCase
{
    private SlowHttpSection $subject;

    protected function setUp(): void
    {
        $this->subject = new SlowHttpSection();
    }

    #[Test]
    public function isNamedSlowHttp(): void
    {
        self::assertSame('slow_http', $this->subject->name());
    }

    #[Test]
    public function collectReturnsNullWhenNoRequestsWereCollected(): void
    {
        self::assertNull($this->subject->collect($this->context()));
    }

    #[Test]
    public function collectSortsByDurationDescendingAndOmitsStatusWhenAbsent(): void
    {
        $this->collector()->addRequest('GET', 'https://api.example.org/fast', 5.0, 200);
        $this->collector()->addRequest('GET', 'https://api.example.org/slow', 600.0, 200);
        $this->collector()->addRequest('GET', 'https://api.example.org/failed', 300.0, null);

        $result = $this->subject->collect($this->context());

        self::assertNotNull($result);
        self::assertSame(
            ['https://api.example.org/slow', 'https://api.example.org/failed', 'https://api.example.org/fast'],
            array_column($result, 'url'),
        );
        self::assertArrayHasKey('status', $result[0]);
        self::assertSame(200, $result[0]['status']);
        self::assertArrayNotHasKey('status', $result[1]);
    }

    #[Test]
    public function collectCapsResultsAtFive(): void
    {
        for ($i = 0; $i < 8; ++$i) {
            $this->collector()->addRequest('GET', 'https://api.example.org/'.$i, (float) $i, 200);
        }

        $result = $this->subject->collect($this->context());

        self::assertNotNull($result);
        self::assertCount(5, $result);
        self::assertSame('https://api.example.org/7', $result[0]['url']);
    }

    private function context(): ProfileContext
    {
        return new ProfileContext(Requests::get('https://example.com/')->build(), new Response(), 'tok', 1.0);
    }

    private function collector(): HttpCollector
    {
        return GeneralUtility::makeInstance(HttpCollector::class);
    }
}
