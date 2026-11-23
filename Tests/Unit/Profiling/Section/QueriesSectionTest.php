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
use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\QueryCollector;
use KonradMichalik\Typo3RequestProfiler\Profiling\Section\{ProfileContext, QueriesSection, QueryAggregator};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * QueriesSectionTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[WithSingleton(QueryCollector::class, QueryCollector::class)]
final class QueriesSectionTest extends TestCase
{
    #[Test]
    public function collectOmitsTruncatedFlagBelowTheCap(): void
    {
        $this->collector()->addQuery('SELECT 1', 1.5);

        self::assertSame(['count' => 1, 'total_ms' => 1.5], $this->collect());
    }

    #[Test]
    public function collectFlagsTruncationOnceTheCapIsExceeded(): void
    {
        $total = QueryCollector::MAX_ENTRIES + 1;
        for ($i = 0; $i < $total; ++$i) {
            $this->collector()->addQuery('SELECT 1', 1.0);
        }

        self::assertSame(['count' => $total, 'total_ms' => (float) $total, 'truncated' => true], $this->collect());
    }

    /**
     * @return array<string, mixed>
     */
    private function collect(): array
    {
        $context = new ProfileContext(Requests::get('https://example.com/')->build(), new Response(), 'tok', 1.0, null);

        return (new QueriesSection(new QueryAggregator()))->collect($context);
    }

    private function collector(): QueryCollector
    {
        return GeneralUtility::makeInstance(QueryCollector::class);
    }
}
