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

use ArrayIterator;
use Symfony\Contracts\HttpClient\{HttpClientInterface, ResponseInterface, ResponseStreamInterface};

/**
 * StubHttpClient.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class StubHttpClient implements HttpClientInterface
{
    /**
     * @var list<array{string, string}>
     */
    public array $calls = [];

    /**
     * @var array<array-key, mixed>
     */
    public array $options = [];

    public function __construct(private readonly ?ResponseInterface $response = null) {}

    /**
     * @param array<array-key, mixed> $options
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $this->calls[] = [$method, $url];

        return $this->response ?? new StubResponse();
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        $responses = $responses instanceof ResponseInterface ? [$responses] : $responses;

        return new StubResponseStream(new ArrayIterator([...$responses]));
    }

    /**
     * @param array<array-key, mixed> $options
     */
    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->options = $options;

        return $clone;
    }
}
