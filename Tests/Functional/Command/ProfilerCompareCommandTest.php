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

namespace KonradMichalik\Typo3RequestProfiler\Tests\Functional\Command;

use KonradMichalik\Typo3RequestProfiler\Command\ProfilerCompareCommand;
use KonradMichalik\Typo3RequestProfiler\Profiling\{ProfileComparer, ProfileWriter};
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Console\CommandRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

use function dirname;

/**
 * ProfilerCompareCommandTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ProfilerCompareCommandTest extends FunctionalTestCase
{
    protected bool $initializeDatabase = false;

    // The registration test asserts on the compiled DI container, which only
    // knows this extension's services once it is loaded as a package.
    protected array $testExtensionsToLoad = ['typo3_request_profiler'];

    #[Test]
    public function isRegisteredWithTheConsoleCommandRegistry(): void
    {
        self::assertTrue(GeneralUtility::makeInstance(CommandRegistry::class)->has('profiler:compare'));
    }

    #[Test]
    public function comparesTwoTokensAsJson(): void
    {
        $this->writeProfile('basetoken', $this->profile());
        $head = $this->profile();
        $head['queries']['count'] = 135;
        $head['duplicate_queries'][] = ['sql' => 'SELECT * FROM sys_category_record_mm WHERE uid_foreign = ?', 'count' => 24, 'total_ms' => 5.0];
        $this->writeProfile('head-token_2', $head);

        $tester = new CommandTester(new ProfilerCompareCommand(new ProfileComparer()));
        $exitCode = $tester->execute(['base' => 'basetoken', 'head' => 'head-token_2', '--format' => 'json']);

        self::assertSame(0, $exitCode);
        $result = json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(1, $result['schemaVersion']);
        self::assertSame('basetoken', $result['base']);
        self::assertSame('head-token_2', $result['head']);
        self::assertTrue($result['comparable']);
        self::assertSame([], $result['warnings']);
        self::assertSame(34, $result['metrics']['queries.count']['delta']);
        self::assertSame('SELECT * FROM sys_category_record_mm WHERE uid_foreign = ?', $result['duplicate_queries']['added'][0]['sql']);
    }

    #[Test]
    public function acceptsAFilePathInsteadOfAToken(): void
    {
        $this->writeProfile('basetoken', $this->profile());
        $file = dirname(ProfileWriter::defaultDirectory()).'/copied-baseline.json';
        file_put_contents($file, json_encode($this->profile(), \JSON_THROW_ON_ERROR));

        $tester = new CommandTester(new ProfilerCompareCommand(new ProfileComparer()));
        $exitCode = $tester->execute(['base' => $file, 'head' => 'basetoken', '--format' => 'json']);

        self::assertSame(0, $exitCode);
        $result = json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($file, $result['base']);
        self::assertSame(0, $result['metrics']['queries.count']['delta']);
    }

    #[Test]
    public function textOutputListsChangesWordsTopListsCarefullyAndWarns(): void
    {
        $this->writeProfile('basetoken', $this->profile());
        $head = $this->profile();
        $head['timing']['total_ms'] = 171.0;
        $head['cache']['hit'] = true;
        $head['duplicate_queries'] = [];
        $this->writeProfile('headtoken', $head);

        $tester = new CommandTester(new ProfilerCompareCommand(new ProfileComparer()));
        $exitCode = $tester->execute(['base' => 'basetoken', 'head' => 'headtoken']);

        $display = $tester->getDisplay();
        self::assertSame(0, $exitCode);
        self::assertStringContainsString('timing.total_ms', $display);
        self::assertStringContainsString('142.5 → 171', $display);
        self::assertStringNotContainsString('queries.count', $display);
        self::assertStringContainsString('left the top list', $display);
        self::assertStringContainsString('cache.hit', $display);
    }

    #[Test]
    public function identicalProfilesReportNoChanges(): void
    {
        $this->writeProfile('basetoken', $this->profile());

        $tester = new CommandTester(new ProfilerCompareCommand(new ProfileComparer()));
        $exitCode = $tester->execute(['base' => 'basetoken', 'head' => 'basetoken']);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('No changes', $tester->getDisplay());
    }

    #[Test]
    public function failsForAnUnknownToken(): void
    {
        $this->writeProfile('basetoken', $this->profile());

        $tester = new CommandTester(new ProfilerCompareCommand(new ProfileComparer()));
        $exitCode = $tester->execute(['base' => 'basetoken', 'head' => 'doesnotexist', '--format' => 'json']);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('doesnotexist', $tester->getDisplay());
        self::assertStringNotContainsString('schemaVersion', $tester->getDisplay());
    }

    #[Test]
    public function aTokenCanNeverEscapeTheProfileDirectory(): void
    {
        $this->writeProfile('basetoken', $this->profile());
        file_put_contents(dirname(ProfileWriter::defaultDirectory()).'/outside.json', json_encode($this->profile(), \JSON_THROW_ON_ERROR));

        $tester = new CommandTester(new ProfilerCompareCommand(new ProfileComparer()));

        // Contains a slash, so it is no token, and no such file exists relative to the cwd.
        self::assertSame(1, $tester->execute(['base' => 'basetoken', 'head' => '../outside']));
    }

    #[Test]
    public function failsForAFileThatIsNotJson(): void
    {
        $this->writeProfile('basetoken', $this->profile());
        $file = dirname(ProfileWriter::defaultDirectory()).'/broken.json';
        file_put_contents($file, '{not json');

        $tester = new CommandTester(new ProfilerCompareCommand(new ProfileComparer()));

        self::assertSame(1, $tester->execute(['base' => 'basetoken', 'head' => $file]));
        self::assertStringContainsString('not valid JSON', $tester->getDisplay());
    }

    #[Test]
    public function failsForJsonThatIsNoProfile(): void
    {
        $this->writeProfile('basetoken', $this->profile());
        $file = dirname(ProfileWriter::defaultDirectory()).'/other.json';
        file_put_contents($file, '{"name": "vendor/package"}');

        $tester = new CommandTester(new ProfilerCompareCommand(new ProfileComparer()));

        self::assertSame(1, $tester->execute(['base' => 'basetoken', 'head' => $file]));
        self::assertStringContainsString('not a profile', $tester->getDisplay());
    }

    #[Test]
    public function rejectsAnUnknownFormat(): void
    {
        $tester = new CommandTester(new ProfilerCompareCommand(new ProfileComparer()));

        self::assertSame(2, $tester->execute(['base' => 'a', 'head' => 'b', '--format' => 'xml']));
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function writeProfile(string $token, array $profile): void
    {
        $directory = ProfileWriter::defaultDirectory();
        GeneralUtility::mkdir_deep($directory);
        file_put_contents($directory.'/'.$token.'.json', json_encode($profile, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(): array
    {
        return [
            'schemaVersion' => 1,
            'token' => 'abc',
            'url' => 'https://example.ddev.site/',
            'page' => ['id' => 1, 'type' => 0],
            'cache' => ['hit' => false, 'cacheable' => false, 'disabled_reasons' => []],
            'timing' => ['total_ms' => 142.5],
            'memory' => ['peak_mb' => 16.1, 'limit_mb' => 512, 'peak_pct' => 3.1],
            'php' => ['included_files' => 432],
            'queries' => ['count' => 101, 'total_ms' => 38.2],
            'slow_queries' => [['sql' => 'SELECT * FROM pages WHERE slug = ?', 'ms' => 12.4]],
            'duplicate_queries' => [['sql' => 'SELECT COUNT(*) FROM tt_content WHERE pid = ?', 'count' => 100, 'total_ms' => 31.4]],
        ];
    }
}
