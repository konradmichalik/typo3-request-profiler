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

namespace KonradMichalik\Typo3RequestProfiler\Profiling\Budget;

use InvalidArgumentException;
use JsonException;

use function array_key_exists;
use function array_keys;
use function implode;
use function is_array;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;

/**
 * BudgetLoader.
 *
 * Reads and validates a budget file. A typo in a key must never silently
 * disable a budget, so everything unknown is rejected.
 *
 * @phpstan-type Match array{path?: string, page_id?: int}
 * @phpstan-type Budgets array{defaults: array<string, int|float>, pages: list<array{match: Match, budgets: array<string, int|float>}>}
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class BudgetLoader
{
    /**
     * @return Budgets
     *
     * @throws InvalidArgumentException when the file is unreadable or invalid
     */
    public function load(string $file): array
    {
        $document = $this->decode($file);
        foreach (array_keys($document) as $key) {
            if ('defaults' !== $key && 'pages' !== $key) {
                throw new InvalidArgumentException(sprintf('Unknown top-level key "%s", use "defaults" and "pages".', $key), 1736210001);
            }
        }

        return [
            'defaults' => $this->limits($document['defaults'] ?? [], 'defaults'),
            'pages' => $this->pages($document['pages'] ?? []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $file): array
    {
        $json = is_readable($file) ? file_get_contents($file) : false;
        if (false === $json) {
            throw new InvalidArgumentException(sprintf('Budget file "%s" is not readable.', $file), 1736210002);
        }

        try {
            $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException(sprintf('Budget file "%s" is not valid JSON.', $file), 1736210003);
        }

        if (!is_array($decoded) || ([] !== $decoded && array_is_list($decoded))) {
            throw new InvalidArgumentException(sprintf('Budget file "%s" must be a JSON object.', $file), 1736210004);
        }

        $document = [];
        foreach ($decoded as $key => $value) {
            $document[(string) $key] = $value;
        }

        return $document;
    }

    /**
     * @return array<string, int|float>
     */
    private function limits(mixed $value, string $where): array
    {
        if (!is_array($value) || ([] !== $value && array_is_list($value))) {
            throw new InvalidArgumentException(sprintf('"%s" must be a JSON object of budget keys and limits.', $where), 1736210005);
        }

        $limits = [];
        foreach ($value as $key => $limit) {
            $key = (string) $key;
            if (!array_key_exists($key, BudgetEvaluator::KEYS)) {
                throw new InvalidArgumentException(sprintf('Unknown budget key "%s" in "%s". Valid keys: %s.', $key, $where, implode(', ', array_keys(BudgetEvaluator::KEYS))), 1736210006);
            }
            if ((!is_int($limit) && !is_float($limit)) || $limit < 0) {
                throw new InvalidArgumentException(sprintf('The limit of "%s" in "%s" must be a non-negative number.', $key, $where), 1736210007);
            }
            $limits[$key] = $limit;
        }

        return $limits;
    }

    /**
     * @return list<array{match: Match, budgets: array<string, int|float>}>
     */
    private function pages(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidArgumentException('"pages" must be a list of page entries.', 1736210008);
        }

        $pages = [];
        foreach ($value as $index => $page) {
            $where = sprintf('pages[%d]', $index);
            if (!is_array($page)) {
                throw new InvalidArgumentException(sprintf('"%s" must be an object with "match" and "budgets".', $where), 1736210009);
            }
            $pages[] = [
                'match' => $this->match($page['match'] ?? null, $where),
                'budgets' => $this->limits($this->required($page, 'budgets', $where), $where.'.budgets'),
            ];
        }

        return $pages;
    }

    /**
     * @param array<mixed> $page
     */
    private function required(array $page, string $key, string $where): mixed
    {
        if (!array_key_exists($key, $page)) {
            throw new InvalidArgumentException(sprintf('"%s" needs a "%s" object.', $where, $key), 1736210010);
        }

        return $page[$key];
    }

    /**
     * @return Match
     */
    private function match(mixed $value, string $where): array
    {
        if (!is_array($value) || ([] !== $value && array_is_list($value))) {
            throw new InvalidArgumentException(sprintf('"%s" needs a "match" object.', $where), 1736210010);
        }
        if ([] === $value) {
            throw new InvalidArgumentException(sprintf('"%s.match" needs path or page_id.', $where), 1736210011);
        }

        $match = [];
        foreach ($value as $key => $criterion) {
            $match = [...$match, ...$this->criterion((string) $key, $criterion, $where)];
        }

        return $match;
    }

    /**
     * @return Match
     */
    private function criterion(string $key, mixed $value, string $where): array
    {
        if ('path' === $key && is_string($value) && str_starts_with($value, '/')) {
            return ['path' => $value];
        }
        if ('page_id' === $key && is_int($value)) {
            return ['page_id' => $value];
        }

        throw new InvalidArgumentException($this->matchError($key, $where), 1736210012);
    }

    private function matchError(string $key, string $where): string
    {
        return match ($key) {
            'path' => sprintf('"%s.match": path must start with "/".', $where),
            'page_id' => sprintf('"%s.match": page_id must be an integer.', $where),
            default => sprintf('"%s.match": Unknown match key "%s", use path or page_id.', $where, $key),
        };
    }
}
