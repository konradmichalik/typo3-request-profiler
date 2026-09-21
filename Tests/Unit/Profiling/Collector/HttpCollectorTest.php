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

namespace KonradMichalik\Typo3RequestProfiler\Tests\Unit\Profiling\Collector;

use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\HttpCollector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * HttpCollectorTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class HttpCollectorTest extends TestCase
{
    private HttpCollector $subject;

    protected function setUp(): void
    {
        $this->subject = new HttpCollector();
    }

    #[Test]
    public function startsEmpty(): void
    {
        self::assertSame([], $this->subject->getRequests());
    }

    #[Test]
    public function addRequestStoresMethodUrlTimingAndStatus(): void
    {
        $this->subject->addRequest('GET', 'https://api.example.org/', 12.5, 200);
        $this->subject->addRequest('POST', 'https://api.example.org/', 5.0, null);

        $requests = $this->subject->getRequests();

        self::assertCount(2, $requests);
        self::assertSame(['method' => 'GET', 'url' => 'https://api.example.org/', 'ms' => 12.5, 'status' => 200], $requests[0]);
        self::assertSame(['method' => 'POST', 'url' => 'https://api.example.org/', 'ms' => 5.0, 'status' => null], $requests[1]);
    }
}
