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

use KonradMichalik\Ttt\Http\Requests;
use KonradMichalik\Typo3RequestProfiler\Profiling\Section\{MemorySection, ProfileContext};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Http\Response;

use function ini_get;

/**
 * MemorySectionTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class MemorySectionTest extends TestCase
{
    private MemorySection $subject;
    private string $originalMemoryLimit;

    protected function setUp(): void
    {
        $this->subject = new MemorySection();
        $this->originalMemoryLimit = ini_get('memory_limit');
    }

    protected function tearDown(): void
    {
        ini_set('memory_limit', $this->originalMemoryLimit);
    }

    #[Test]
    public function isNamedMemory(): void
    {
        self::assertSame('memory', $this->subject->name());
    }

    #[Test]
    public function collectReportsPeakInMegabytes(): void
    {
        $result = $this->subject->collect($this->context());

        self::assertGreaterThan(0, $result['peak_mb']);
    }

    #[Test]
    public function collectReportsNullLimitAndOmitsPeakPctWhenUnlimited(): void
    {
        ini_set('memory_limit', '-1');

        $result = $this->subject->collect($this->context());

        self::assertNull($result['limit_mb']);
        self::assertArrayNotHasKey('peak_pct', $result);
    }

    #[Test]
    public function collectReportsLimitAndPeakPctForFiniteLimit(): void
    {
        ini_set('memory_limit', '256M');

        $result = $this->subject->collect($this->context());

        self::assertSame(256.0, $result['limit_mb']);
        self::assertArrayHasKey('peak_pct', $result);
        self::assertSame(round($result['peak_mb'] / 256 * 100, 1), $result['peak_pct']);
    }

    #[Test]
    public function collectParsesGigabyteShorthand(): void
    {
        ini_set('memory_limit', '1G');

        $result = $this->subject->collect($this->context());

        self::assertSame(1024.0, $result['limit_mb']);
    }

    #[Test]
    public function collectParsesKilobyteShorthand(): void
    {
        ini_set('memory_limit', '262144K');

        $result = $this->subject->collect($this->context());

        self::assertSame(256.0, $result['limit_mb']);
    }

    #[Test]
    public function collectParsesLowercaseShorthandSuffix(): void
    {
        ini_set('memory_limit', '512m');

        $result = $this->subject->collect($this->context());

        self::assertSame(512.0, $result['limit_mb']);
    }

    #[Test]
    public function collectParsesPlainByteValueWithoutSuffix(): void
    {
        ini_set('memory_limit', '134217728');

        $result = $this->subject->collect($this->context());

        self::assertSame(128.0, $result['limit_mb']);
    }

    private function context(): ProfileContext
    {
        return new ProfileContext(Requests::get('https://example.com/')->build(), new Response(), 'tok', 1.0);
    }
}
