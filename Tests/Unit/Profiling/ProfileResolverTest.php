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

namespace KonradMichalik\Typo3RequestProfiler\Tests\Unit\Profiling;

use InvalidArgumentException;
use KonradMichalik\Ttt\Attribute\WithEnvironment;
use KonradMichalik\Typo3RequestProfiler\Profiling\ProfileResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ProfileResolverTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[WithEnvironment]
final class ProfileResolverTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir().'/rp_resolver_'.bin2hex(random_bytes(8)).'.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    #[Test]
    public function resolvesAProfileFromAFilePath(): void
    {
        file_put_contents($this->file, '{"schemaVersion": 1, "token": "abc"}');

        self::assertSame(['schemaVersion' => 1, 'token' => 'abc'], ProfileResolver::resolve($this->file));
    }

    #[Test]
    public function rejectsAFileThatIsNotJson(): void
    {
        file_put_contents($this->file, '{not json');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not valid JSON');

        ProfileResolver::resolve($this->file);
    }

    #[Test]
    public function rejectsJsonWithoutASchemaVersion(): void
    {
        file_put_contents($this->file, '{"name": "vendor/package"}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not a profile');

        ProfileResolver::resolve($this->file);
    }

    #[Test]
    public function rejectsAScalarJsonFile(): void
    {
        file_put_contents($this->file, '"text"');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not a profile');

        ProfileResolver::resolve($this->file);
    }

    #[Test]
    public function rejectsAnUnknownToken(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No profile found for "doesnotexist"');

        ProfileResolver::resolve('doesnotexist');
    }
}
