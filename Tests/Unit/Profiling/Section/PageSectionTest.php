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

namespace KonradMichalik\Typo3RequestProfiler\Tests\Unit\Profiling\Section;

use KonradMichalik\Typo3RequestProfiler\Profiling\Section\{PageSection, ProfileContext};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Http\{Response, ServerRequest};
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Frontend\Page\PageInformation;

/**
 * PageSectionTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class PageSectionTest extends TestCase
{
    private PageSection $subject;

    protected function setUp(): void
    {
        $this->subject = new PageSection();
    }

    #[Test]
    public function isNamedPage(): void
    {
        self::assertSame('page', $this->subject->name());
    }

    #[Test]
    public function collectReturnsNullWithoutPageInformation(): void
    {
        self::assertNull($this->subject->collect($this->context(new ServerRequest('https://example.com/', 'GET'))));
    }

    #[Test]
    public function collectReturnsPageIdWithDefaultTypeWhenRoutingAbsent(): void
    {
        $pageInformation = new PageInformation();
        $pageInformation->setId(42);
        $request = (new ServerRequest('https://example.com/', 'GET'))
            ->withAttribute('frontend.page.information', $pageInformation);

        self::assertSame(['id' => 42, 'type' => 0], $this->subject->collect($this->context($request)));
    }

    #[Test]
    public function collectReadsPageTypeFromRoutingArguments(): void
    {
        $pageInformation = new PageInformation();
        $pageInformation->setId(7);
        $request = (new ServerRequest('https://example.com/', 'GET'))
            ->withAttribute('frontend.page.information', $pageInformation)
            ->withAttribute('routing', new PageArguments(7, '98', []));

        self::assertSame(['id' => 7, 'type' => 98], $this->subject->collect($this->context($request)));
    }

    private function context(ServerRequest $request): ProfileContext
    {
        return new ProfileContext($request, new Response(), 'tok', 1.0);
    }
}
