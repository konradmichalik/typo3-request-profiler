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

use KonradMichalik\Typo3RequestProfiler\Command\ProfilerCheckCommand;
use KonradMichalik\Typo3RequestProfiler\Profiling\Budget\{BudgetEvaluator, BudgetLoader};
use KonradMichalik\Typo3RequestProfiler\Profiling\ProfileWriter;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Console\CommandRegistry;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

use function dirname;

/**
 * ProfilerCheckCommandTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class ProfilerCheckCommandTest extends FunctionalTestCase
{
    protected bool $initializeDatabase = false;

    // The registration test asserts on the compiled DI container, which only
    // knows this extension's services once it is loaded as a package.
    protected array $testExtensionsToLoad = ['typo3_request_profiler'];

    protected function tearDown(): void
    {
        putenv('TYPO3_REQUEST_PROFILER_BUDGETS');
        parent::tearDown();
    }

    #[Test]
    public function isRegisteredWithTheConsoleCommandRegistry(): void
    {
        self::assertTrue(GeneralUtility::makeInstance(CommandRegistry::class)->has('profiler:check'));
    }

    #[Test]
    public function exitsZeroWhenEverythingIsWithinBudget(): void
    {
        $this->useBudgets(['defaults' => ['queries.count' => 200]]);
        $this->writeProfile('okaytoken', $this->profile());

        $tester = $this->tester();
        $exitCode = $tester->execute(['profiles' => ['okaytoken']]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('✓ / (token okaytoke…)', $tester->getDisplay());
        self::assertStringContainsString('all budgets met', $tester->getDisplay());
        self::assertStringContainsString('0 of 1 profiles over budget', $tester->getDisplay());
    }

    #[Test]
    public function exitsOneAndPrintsTheOffendingSqlOnAViolation(): void
    {
        $this->useBudgets(['defaults' => ['queries.count' => 200, 'duplicate_queries.max_count' => 5]]);
        $ok = $this->profile();
        $ok['duplicate_queries'][0]['count'] = 3;
        $this->writeProfile('okaytoken', $ok);
        $over = $this->profile();
        $over['duplicate_queries'] = [['sql' => 'SELECT * FROM x WHERE a <> ?', 'count' => 24, 'total_ms' => 5.0]];
        $this->writeProfile('overtoken', $over);

        $tester = $this->tester();
        $exitCode = $tester->execute(['profiles' => ['overtoken', 'okaytoken']]);

        $display = $tester->getDisplay();
        self::assertSame(1, $exitCode);
        self::assertStringContainsString('✗ / (token overtoke…)', $display);
        self::assertStringContainsString('queries.count', $display);
        self::assertStringContainsString('24 > 5', $display);
        self::assertStringContainsString('FAIL', $display);
        self::assertStringContainsString('SELECT * FROM x WHERE a <> ?', $display);
        self::assertStringContainsString('1 of 2 profiles over budget', $display);
    }

    #[Test]
    public function aPageOverrideBeatsTheDefaults(): void
    {
        $this->useBudgets([
            'defaults' => ['queries.count' => 50],
            'pages' => [['match' => ['path' => '/'], 'budgets' => ['queries.count' => 150]]],
        ]);
        $this->writeProfile('okaytoken', $this->profile());

        self::assertSame(0, $this->tester()->execute(['profiles' => ['okaytoken']]));
    }

    #[Test]
    public function exitsTwoForAnUnknownToken(): void
    {
        $this->useBudgets(['defaults' => ['queries.count' => 200]]);
        $this->writeProfile('okaytoken', $this->profile());

        $tester = $this->tester();
        $exitCode = $tester->execute(['profiles' => ['okaytoken', 'doesnotexist']]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('doesnotexist', $tester->getDisplay());
        self::assertStringNotContainsString('profiles over budget', $tester->getDisplay());
    }

    #[Test]
    public function acceptsAProfileFilePath(): void
    {
        $this->useBudgets(['defaults' => ['queries.count' => 200]]);
        $file = dirname(ProfileWriter::defaultDirectory()).'/copied.json';
        file_put_contents($file, json_encode($this->profile(), \JSON_THROW_ON_ERROR));

        self::assertSame(0, $this->tester()->execute(['profiles' => [$file]]));
    }

    #[Test]
    public function exitsTwoAndListsTheValidKeysForATypoInTheBudgetFile(): void
    {
        $this->useBudgets(['defaults' => ['queries.cnt' => 200]]);
        $this->writeProfile('okaytoken', $this->profile());

        $tester = $this->tester();
        $exitCode = $tester->execute(['profiles' => ['okaytoken']]);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('queries.cnt', $tester->getDisplay());
        self::assertStringContainsString('Valid keys: timing.total_ms, queries.count', $tester->getDisplay());
    }

    #[Test]
    public function exitsTwoWhenTheBudgetFileIsMissing(): void
    {
        putenv('TYPO3_REQUEST_PROFILER_BUDGETS=/does/not/exist.json');
        $this->writeProfile('okaytoken', $this->profile());

        $tester = $this->tester();

        self::assertSame(2, $tester->execute(['profiles' => ['okaytoken']]));
        self::assertStringContainsString('is not readable', $tester->getDisplay());
    }

    #[Test]
    public function reportsNoBudgetsAndExitsZeroWithoutTheVariable(): void
    {
        putenv('TYPO3_REQUEST_PROFILER_BUDGETS');

        $tester = $this->tester();

        self::assertSame(0, $tester->execute(['profiles' => ['doesnotexist']]));
        self::assertStringContainsString('No budgets configured', $tester->getDisplay());
    }

    #[Test]
    public function reportsNoBudgetsForAFileWithoutAny(): void
    {
        $this->useBudgets([]);

        $tester = $this->tester();

        self::assertSame(0, $tester->execute(['profiles' => ['doesnotexist']]));
        self::assertStringContainsString('No budgets configured', $tester->getDisplay());
    }

    #[Test]
    public function skipsACacheHitProfileWithANoticeInsteadOfPassingIt(): void
    {
        $this->useBudgets(['defaults' => ['queries.count' => 1]]);
        $hit = $this->profile();
        $hit['cache']['hit'] = true;
        $this->writeProfile('cachedtoken', $hit);

        $tester = $this->tester();
        $exitCode = $tester->execute(['profiles' => ['cachedtoken']]);

        $display = $tester->getDisplay();
        self::assertSame(0, $exitCode);
        self::assertStringContainsString('skipped', $display);
        self::assertStringContainsString('page cache hit', $display);
        self::assertStringNotContainsString('✓', $display);
        self::assertStringContainsString('0 of 1 profiles over budget, 1 skipped', $display);
    }

    #[Test]
    public function jsonOutputCarriesItsOwnSchemaVersionAndTheChecks(): void
    {
        $this->useBudgets(['defaults' => ['duplicate_queries.max_count' => 5]]);
        $this->writeProfile('overtoken', $this->profile());

        $tester = $this->tester();
        $exitCode = $tester->execute(['profiles' => ['overtoken'], '--format' => 'json']);

        self::assertSame(1, $exitCode);
        $result = json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(1, $result['schemaVersion']);
        self::assertSame(['profiles' => 1, 'within_budget' => 0, 'over_budget' => 1, 'skipped' => 0], $result['summary']);
        self::assertSame('overtoken', $result['profiles'][0]['reference']);
        self::assertSame('violation', $result['profiles'][0]['status']);
        self::assertSame('SELECT COUNT(*) FROM tt_content WHERE pid = ?', $result['profiles'][0]['checks'][0]['sql']);
    }

    #[Test]
    public function jsonOutputWithoutBudgetsIsAnEmptyReport(): void
    {
        putenv('TYPO3_REQUEST_PROFILER_BUDGETS');

        $tester = $this->tester();
        $exitCode = $tester->execute(['profiles' => ['x'], '--format' => 'json']);

        self::assertSame(0, $exitCode);
        $result = json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(0, $result['summary']['profiles']);
    }

    #[Test]
    public function rejectsAnUnknownFormat(): void
    {
        self::assertSame(2, $this->tester()->execute(['profiles' => ['a'], '--format' => 'xml']));
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new ProfilerCheckCommand(new BudgetLoader(), new BudgetEvaluator()));
    }

    /**
     * @param array<string, mixed> $budgets
     */
    private function useBudgets(array $budgets): void
    {
        $file = dirname(ProfileWriter::defaultDirectory()).'/budgets.json';
        GeneralUtility::mkdir_deep(dirname($file));
        file_put_contents($file, json_encode((object) $budgets, \JSON_THROW_ON_ERROR));
        putenv('TYPO3_REQUEST_PROFILER_BUDGETS='.$file);
    }

    /**
     * @param array<string, mixed> $profile
     */
    private function writeProfile(string $token, array $profile): void
    {
        $directory = ProfileWriter::defaultDirectory();
        GeneralUtility::mkdir_deep($directory);
        file_put_contents($directory.'/'.$token.'.json', json_encode([...$profile, 'token' => $token], \JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(): array
    {
        return [
            'schemaVersion' => 1,
            'url' => 'https://example.ddev.site/',
            'page' => ['id' => 1, 'type' => 0],
            'cache' => ['hit' => false, 'cacheable' => false, 'disabled_reasons' => []],
            'timing' => ['total_ms' => 142.5],
            'queries' => ['count' => 101, 'total_ms' => 38.2],
            'duplicate_queries' => [['sql' => 'SELECT COUNT(*) FROM tt_content WHERE pid = ?', 'count' => 100, 'total_ms' => 31.4]],
        ];
    }
}
