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

namespace KonradMichalik\Typo3RequestProfiler\Profiling\Section;

use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};

/**
 * ProfileContext.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class ProfileContext
{
    public function __construct(
        public ServerRequestInterface $request,
        public ResponseInterface $response,
        public string $token,
        public float $totalMs,
    ) {}
}
