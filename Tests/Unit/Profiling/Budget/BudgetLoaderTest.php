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

namespace KonradMichalik\Typo3RequestProfiler\Tests\Unit\Profiling\Budget;

use InvalidArgumentException;
use KonradMichalik\Typo3RequestProfiler\Profiling\Budget\{BudgetEvaluator, BudgetLoader};
use PHPUnit\Framework\Attributes\{DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * BudgetLoaderTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class BudgetLoaderTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir().'/rp_budgets_'.bin2hex(random_bytes(8)).'.json';
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    #[Test]
    public function loadsDefaultsAndPageOverrides(): void
    {
        $this->write([
            'defaults' => ['queries.count' => 100, 'timing.total_ms' => 300.5],
            'pages' => [
                ['match' => ['path' => '/news'], 'budgets' => ['queries.count' => 140]],
                ['match' => ['page_id' => 1, 'path' => '/'], 'budgets' => ['http.count' => 0]],
            ],
        ]);

        self::assertSame([
            'defaults' => ['queries.count' => 100, 'timing.total_ms' => 300.5],
            'pages' => [
                ['match' => ['path' => '/news'], 'budgets' => ['queries.count' => 140]],
                ['match' => ['page_id' => 1, 'path' => '/'], 'budgets' => ['http.count' => 0]],
            ],
        ], (new BudgetLoader())->load($this->file));
    }

    #[Test]
    public function anEmptyObjectMeansNoBudgets(): void
    {
        file_put_contents($this->file, '{}');

        self::assertSame(['defaults' => [], 'pages' => []], (new BudgetLoader())->load($this->file));
    }

    #[Test]
    public function everyDocumentedKeyIsAccepted(): void
    {
        $keys = array_keys(BudgetEvaluator::KEYS);
        $this->write(['defaults' => array_fill_keys($keys, 1)]);

        self::assertSame([
            'timing.total_ms', 'queries.count', 'queries.total_ms', 'duplicate_queries.max_count',
            'http.count', 'http.total_ms', 'memory.peak_mb', 'memory.peak_pct', 'log.by_level.error',
        ], array_keys((new BudgetLoader())->load($this->file)['defaults']));
    }

    #[Test]
    public function aTypoInADefaultKeyFailsAndListsTheValidKeys(): void
    {
        $this->write(['defaults' => ['queries.cnt' => 100]]);

        $message = $this->failureMessage();

        self::assertStringContainsString('"queries.cnt"', $message);
        self::assertStringContainsString('queries.count, queries.total_ms', $message);
        self::assertStringContainsString('log.by_level.error', $message);
    }

    #[Test]
    public function aTypoInAPageKeyFails(): void
    {
        $this->write(['pages' => [['match' => ['path' => '/'], 'budgets' => ['timing.ms' => 1]]]]);

        self::assertStringContainsString('"timing.ms"', $this->failureMessage());
    }

    #[Test]
    #[DataProvider('invalidDocuments')]
    public function rejectsAnInvalidDocument(string $json, string $expected): void
    {
        file_put_contents($this->file, $json);

        self::assertStringContainsString($expected, $this->failureMessage());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidDocuments(): iterable
    {
        yield 'not json' => ['{nope', 'not valid JSON'];
        yield 'list at top level' => ['[1]', 'must be a JSON object'];
        yield 'unknown top level key' => ['{"default": {}}', 'Unknown top-level key "default"'];
        yield 'string limit' => ['{"defaults": {"queries.count": "100"}}', 'must be a non-negative number'];
        yield 'negative limit' => ['{"defaults": {"queries.count": -1}}', 'must be a non-negative number'];
        yield 'bool limit' => ['{"defaults": {"queries.count": true}}', 'must be a non-negative number'];
        yield 'defaults is a list' => ['{"defaults": [1]}', '"defaults" must be a JSON object'];
        yield 'pages is an object' => ['{"pages": {"a": 1}}', '"pages" must be a list'];
        yield 'page without match' => ['{"pages": [{"budgets": {"queries.count": 1}}]}', 'needs a "match" object'];
        yield 'match without criteria' => ['{"pages": [{"match": {}, "budgets": {"queries.count": 1}}]}', 'path or page_id'];
        yield 'unknown match key' => ['{"pages": [{"match": {"url": "/"}, "budgets": {"queries.count": 1}}]}', 'Unknown match key "url"'];
        yield 'non-integer page id' => ['{"pages": [{"match": {"page_id": "1"}, "budgets": {"queries.count": 1}}]}', 'page_id must be an integer'];
        yield 'path without leading slash' => ['{"pages": [{"match": {"path": "news"}, "budgets": {"queries.count": 1}}]}', 'path must start with "/"'];
        yield 'page without budgets' => ['{"pages": [{"match": {"path": "/"}}]}', 'needs a "budgets" object'];
    }

    #[Test]
    public function failsForAMissingFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not readable');

        (new BudgetLoader())->load($this->file);
    }

    /**
     * @param array<string, mixed> $document
     */
    private function write(array $document): void
    {
        file_put_contents($this->file, json_encode($document, \JSON_THROW_ON_ERROR));
    }

    private function failureMessage(): string
    {
        try {
            (new BudgetLoader())->load($this->file);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        self::fail('Expected an InvalidArgumentException.');
    }
}
