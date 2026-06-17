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

namespace KonradMichalik\Typo3RequestProfiler\Tests\Functional\Middleware;

use KonradMichalik\Typo3RequestProfiler\Middleware\PerformanceProfilerMiddleware;
use KonradMichalik\Typo3RequestProfiler\Profiling\ProfileWriter;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Core\{Environment, RequestId};
use TYPO3\CMS\Core\Http\{Response, ServerRequest};
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * PerformanceProfilerMiddlewareTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class PerformanceProfilerMiddlewareTest extends FunctionalTestCase
{
    protected bool $initializeDatabase = false;

    #[Test]
    public function passesThroughAndWritesNoProfileOutsideDevelopmentContext(): void
    {
        // The functional test runs in the "Testing" context, so the dev guard
        // must short-circuit: the handler response is returned unchanged and no
        // profile file is created.
        $requestId = new RequestId();
        $middleware = new PerformanceProfilerMiddleware($requestId, new ProfileWriter());

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response();
            }
        };

        $response = $middleware->process(new ServerRequest('https://example.com/', 'GET'), $handler);

        self::assertSame(200, $response->getStatusCode());
        self::assertFileDoesNotExist(
            Environment::getVarPath().'/log/profiles/'.$requestId.'.json',
        );
    }
}
