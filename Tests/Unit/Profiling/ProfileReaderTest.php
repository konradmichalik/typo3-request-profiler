<?php

declare(strict_types=1);

/*
 * This file is part of the "typo3_request_profiler" TYPO3 CMS extension.
 *
 * (c) 2026 Konrad Michalik <km@move-elevator.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KonradMichalik\Typo3RequestProfiler\Tests\Unit\Profiling;

use KonradMichalik\Typo3RequestProfiler\Profiling\ProfileReader;
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;


/**
 * ProfileReaderTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 */

final class ProfileReaderTest extends TestCase
{
    private string $directory;
    private ProfileReader $subject;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/rp_'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0o777, true);
        $this->subject = new ProfileReader($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->directory);
    }

    #[Test]
    public function allReturnsProfilesNewestFirst(): void
    {
        $this->writeProfile('aaa', 1000);
        $this->writeProfile('bbb', 3000);
        $this->writeProfile('ccc', 2000);

        $result = $this->subject->all();

        self::assertCount(3, $result);
        self::assertSame(['bbb', 'ccc', 'aaa'], array_column($result, 'token'));
    }

    #[Test]
    public function latestRespectsLimitAndOrder(): void
    {
        $this->writeProfile('aaa', 1000);
        $this->writeProfile('bbb', 3000);
        $this->writeProfile('ccc', 2000);

        $result = $this->subject->latest(2);

        self::assertSame(['bbb', 'ccc'], array_column($result, 'token'));
    }

    #[Test]
    public function latestWithNonPositiveLimitReturnsEmpty(): void
    {
        $this->writeProfile('aaa', 1000);

        self::assertSame([], $this->subject->latest(0));
    }

    #[Test]
    public function byTokenReturnsMatchingProfile(): void
    {
        $this->writeProfile('abc123', 1000);

        $result = $this->subject->byToken('abc123');

        self::assertNotNull($result);
        self::assertSame('abc123', $result['token']);
    }

    #[Test]
    public function byTokenReturnsNullForUnknownToken(): void
    {
        self::assertNull($this->subject->byToken('doesnotexist'));
    }

    #[Test]
    #[DataProvider('unsafeTokenProvider')]
    public function byTokenRejectsUnsafeTokens(string $token): void
    {
        self::assertNull($this->subject->byToken($token));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeTokenProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'path traversal' => ['../../etc/passwd'];
        yield 'contains dot' => ['foo.bar'];
        yield 'contains slash' => ['a/b'];
    }

    #[Test]
    public function ignoresInvalidJsonFiles(): void
    {
        file_put_contents($this->directory.'/broken.json', 'not valid json');
        $this->writeProfile('ok', 1000);

        $result = $this->subject->all();

        self::assertSame(['ok'], array_column($result, 'token'));
    }

    private function writeProfile(string $token, int $mtime): void
    {
        $file = $this->directory.'/'.$token.'.json';
        file_put_contents($file, (string) json_encode(['token' => $token]));
        touch($file, $mtime);
    }
}
