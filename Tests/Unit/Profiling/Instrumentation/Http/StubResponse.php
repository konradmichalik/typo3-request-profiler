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

namespace KonradMichalik\Typo3RequestProfiler\Tests\Unit\Profiling\Instrumentation\Http;

use Symfony\Contracts\HttpClient\{ResponseInterface};
use Throwable;

use function is_int;

/**
 * StubResponse.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class StubResponse implements ResponseInterface
{
    public bool $canceled = false;

    /**
     * @param array<string, mixed> $info
     */
    public function __construct(
        private readonly array $info = [],
        private readonly ?Throwable $failure = null,
    ) {}

    public function getStatusCode(): int
    {
        $this->failIfConfigured();

        $code = $this->info['http_code'] ?? 0;

        return is_int($code) ? $code : 0;
    }

    public function getHeaders(bool $throw = true): array
    {
        $this->failIfConfigured();

        return [];
    }

    public function getContent(bool $throw = true): string
    {
        $this->failIfConfigured();

        return '';
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(bool $throw = true): array
    {
        $this->failIfConfigured();

        return [];
    }

    public function cancel(): void
    {
        $this->canceled = true;
    }

    public function getInfo(?string $type = null): mixed
    {
        return null === $type ? $this->info : ($this->info[$type] ?? null);
    }

    public function chunk(bool $last): StubChunk
    {
        return new StubChunk($last);
    }

    private function failIfConfigured(): void
    {
        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }
    }
}
