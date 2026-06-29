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
use KonradMichalik\Typo3RequestProfiler\Profiling\Section\{ProfileContext, ProfileSection, TimingSection};
use KonradMichalik\Typo3RequestProfiler\Tests\Functional\DevelopmentContextTrait;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
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
    use DevelopmentContextTrait;

    protected bool $initializeDatabase = false;

    protected function tearDown(): void
    {
        putenv('TYPO3_REQUEST_PROFILER');
        putenv('TYPO3_REQUEST_PROFILER_MIN_MS');
        parent::tearDown();
    }

    #[Test]
    public function passesThroughAndWritesNoProfileOutsideDevelopmentContext(): void
    {
        // The functional test runs in the "Testing" context, so the dev guard
        // must short-circuit: the handler response is returned unchanged and no
        // profile file is created.
        $requestId = new RequestId();
        $middleware = new PerformanceProfilerMiddleware($requestId, new ProfileWriter([]));

        $response = $middleware->process(new ServerRequest('https://example.com/', 'GET'), $this->handler());

        self::assertSame(200, $response->getStatusCode());
        self::assertFileDoesNotExist(
            Environment::getVarPath().'/log/profiles/'.$requestId.'.json',
        );
    }

    #[Test]
    public function writesProfileInDevelopmentContext(): void
    {
        $requestId = new RequestId();
        $middleware = new PerformanceProfilerMiddleware($requestId, new ProfileWriter([new TimingSection()]));

        $this->inDevelopmentContext(function () use ($middleware): void {
            $response = $middleware->process(new ServerRequest('https://example.com/', 'GET'), $this->handler());

            self::assertSame(200, $response->getStatusCode());
        });

        self::assertFileExists(Environment::getVarPath().'/log/profiles/'.$requestId.'.json');
    }

    #[Test]
    public function skipsProfilingWhenBelowMinimumDuration(): void
    {
        putenv('TYPO3_REQUEST_PROFILER_MIN_MS=600000');
        $requestId = new RequestId();
        $middleware = new PerformanceProfilerMiddleware($requestId, new ProfileWriter([new TimingSection()]));

        $this->inDevelopmentContext(function () use ($middleware): void {
            $middleware->process(new ServerRequest('https://example.com/', 'GET'), $this->handler());
        });

        self::assertFileDoesNotExist(Environment::getVarPath().'/log/profiles/'.$requestId.'.json');
    }

    #[Test]
    public function failsSafeAndReturnsResponseWhenProfileWritingThrows(): void
    {
        $requestId = new RequestId();
        $middleware = new PerformanceProfilerMiddleware($requestId, new ProfileWriter([$this->throwingSection()]));

        $this->inDevelopmentContext(function () use ($middleware): void {
            $response = $middleware->process(new ServerRequest('https://example.com/', 'GET'), $this->handler());

            self::assertSame(200, $response->getStatusCode());
        });

        self::assertFileDoesNotExist(Environment::getVarPath().'/log/profiles/'.$requestId.'.json');
    }

    private function throwingSection(): ProfileSection
    {
        return new class implements ProfileSection {
            public function name(): string
            {
                return 'boom';
            }

            public function priority(): int
            {
                return 0;
            }

            public function isEnabled(): bool
            {
                return true;
            }

            public function collect(ProfileContext $context): ?array
            {
                throw new RuntimeException('boom');
            }
        };
    }

    private function handler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response();
            }
        };
    }
}
