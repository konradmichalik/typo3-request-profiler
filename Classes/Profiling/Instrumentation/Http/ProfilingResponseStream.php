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

namespace KonradMichalik\Typo3RequestProfiler\Profiling\Instrumentation\Http;

use Symfony\Contracts\HttpClient\{ChunkInterface, ResponseInterface, ResponseStreamInterface};

use function spl_object_id;

/**
 * ProfilingResponseStream.
 *
 * Hands out the decorated responses as keys, so a caller can still match them
 * against the responses it passed to stream().
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class ProfilingResponseStream implements ResponseStreamInterface
{
    /**
     * @param array<int, ResponseInterface> $decorated decorated responses by the object id of their inner response
     */
    public function __construct(
        private ResponseStreamInterface $inner,
        private array $decorated,
    ) {}

    public function key(): ResponseInterface
    {
        $response = $this->inner->key();

        return $this->decorated[spl_object_id($response)] ?? $response;
    }

    public function current(): ChunkInterface
    {
        return $this->inner->current();
    }

    public function next(): void
    {
        $this->inner->next();
    }

    public function rewind(): void
    {
        $this->inner->rewind();
    }

    public function valid(): bool
    {
        return $this->inner->valid();
    }
}
