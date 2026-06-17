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

use KonradMichalik\Typo3RequestProfiler\Profiling\Section\{MemorySection, PhpSection, ProfileContext, TimingSection};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Http\{Response, ServerRequest};

/**
 * SimpleSectionsTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class SimpleSectionsTest extends TestCase
{
    #[Test]
    public function timingSectionRoundsTotalMs(): void
    {
        $section = new TimingSection();

        self::assertSame('timing', $section->name());
        self::assertSame(['total_ms' => 12.35], $section->collect($this->context(12.3456)));
    }

    #[Test]
    public function memorySectionReportsPeakInMegabytes(): void
    {
        $section = new MemorySection();

        $result = $section->collect($this->context());

        self::assertSame('memory', $section->name());
        self::assertGreaterThan(0, $result['peak_mb']);
    }

    #[Test]
    public function phpSectionReportsIncludedFileCount(): void
    {
        $section = new PhpSection();

        $result = $section->collect($this->context());

        self::assertSame('php', $section->name());
        self::assertGreaterThan(0, $result['included_files']);
    }

    private function context(float $totalMs = 1.0): ProfileContext
    {
        return new ProfileContext(new ServerRequest('https://example.com/', 'GET'), new Response(), 'tok', $totalMs);
    }
}
