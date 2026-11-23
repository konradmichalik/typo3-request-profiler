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
use Symfony\Contracts\HttpClient\{ChunkInterface, ResponseInterface, ResponseStreamInterface};

/**
 * StubResponseStream.
 *
 * Yields one last chunk per response, like a client does once a body completed.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class StubResponseStream implements ResponseStreamInterface
{
    /**
     * @param ArrayIterator<int, ResponseInterface> $responses
     */
    public function __construct(private ArrayIterator $responses) {}

    public function key(): ResponseInterface
    {
        return $this->responses->current();
    }

    public function current(): ChunkInterface
    {
        $response = $this->responses->current();

        return $response instanceof StubResponse ? $response->chunk(true) : new StubChunk(true);
    }

    public function next(): void
    {
        $this->responses->next();
    }

    public function rewind(): void
    {
        $this->responses->rewind();
    }

    public function valid(): bool
    {
        return $this->responses->valid();
    }
}
