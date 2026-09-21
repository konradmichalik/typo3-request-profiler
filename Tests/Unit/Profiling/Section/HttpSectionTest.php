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
use KonradMichalik\Typo3RequestProfiler\Profiling\Section\{HttpSection, ProfileContext};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * HttpSectionTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[WithSingleton(HttpCollector::class, HttpCollector::class)]
final class HttpSectionTest extends TestCase
{
    private HttpSection $subject;

    protected function setUp(): void
    {
        $this->subject = new HttpSection();
    }

    #[Test]
    public function isNamedHttp(): void
    {
        self::assertSame('http', $this->subject->name());
    }

    #[Test]
    public function collectReturnsNullWhenNoRequestsWereCollected(): void
    {
        self::assertNull($this->subject->collect($this->context()));
    }

    #[Test]
    public function collectSumsCountAndTotalTime(): void
    {
        $this->collector()->addRequest('GET', 'https://api.example.org/', 10.0, 200);
        $this->collector()->addRequest('POST', 'https://api.example.org/', 5.5, 201);

        $result = $this->subject->collect($this->context());

        self::assertSame(['count' => 2, 'total_ms' => 15.5], $result);
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
