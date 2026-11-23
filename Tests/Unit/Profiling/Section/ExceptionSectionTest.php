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

use InvalidArgumentException;
use KonradMichalik\Ttt\Attribute\WithEnvironment;
use KonradMichalik\Ttt\Http\Requests;
use KonradMichalik\Typo3RequestProfiler\Profiling\Section\{ExceptionSection, ProfileContext};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Throwable;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\Response;

/**
 * ExceptionSectionTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[WithEnvironment]
final class ExceptionSectionTest extends TestCase
{
    private ExceptionSection $subject;

    protected function setUp(): void
    {
        $this->subject = new ExceptionSection();
    }

    #[Test]
    public function isNamedException(): void
    {
        self::assertSame('exception', $this->subject->name());
    }

    #[Test]
    public function collectReturnsNullWhenNoExceptionOccurred(): void
    {
        self::assertNull($this->subject->collect($this->context()));
    }

    #[Test]
    public function collectReportsClassFileAndLineWithoutTheMessage(): void
    {
        $exception = new RuntimeException('contains a secret token, never persist this');
        $line = __LINE__ - 1;

        $result = $this->subject->collect($this->context($exception));

        self::assertNotNull($result);
        self::assertSame(RuntimeException::class, $result['class']);
        self::assertSame(basename(__FILE__), $result['file']);
        self::assertSame($line, $result['line']);
        self::assertArrayNotHasKey('message', $result);
    }

    #[Test]
    #[DataProvider('filePathProvider')]
    public function collectWritesFileRelativeToProjectOrBasenameOutsideIt(string $fileTemplate, string $expected): void
    {
        $projectPath = Environment::getProjectPath();
        $exception = new RuntimeException('x');
        (new ReflectionProperty(RuntimeException::class, 'file'))
            ->setValue($exception, str_replace('{project}', $projectPath, $fileTemplate));

        $result = $this->subject->collect($this->context($exception));

        self::assertNotNull($result);
        self::assertSame($expected, $result['file']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function filePathProvider(): iterable
    {
        yield 'inside the project' => ['{project}/Classes/Foo.php', 'Classes/Foo.php'];
        yield 'directly in the project root' => ['{project}/index.php', 'index.php'];
        yield 'outside the project' => ['/somewhere/else/Foo.php', 'Foo.php'];
        yield 'sibling directory sharing the project path as prefix' => ['{project}-other/x.php', 'x.php'];
    }

    #[Test]
    public function collectOmitsCodeWhenZero(): void
    {
        $result = $this->subject->collect($this->context(new RuntimeException('x', 0)));

        self::assertNotNull($result);
        self::assertArrayNotHasKey('code', $result);
    }

    #[Test]
    public function collectIncludesNonZeroCode(): void
    {
        $result = $this->subject->collect($this->context(new InvalidArgumentException('x', 42)));

        self::assertNotNull($result);
        self::assertArrayHasKey('code', $result);
        self::assertSame(42, $result['code']);
    }

    private function context(?Throwable $exception = null): ProfileContext
    {
        return new ProfileContext(Requests::get('https://example.com/')->build(), new Response(), 'tok', 1.0, $exception);
    }
}
