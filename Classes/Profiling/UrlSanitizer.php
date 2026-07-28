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

namespace KonradMichalik\Typo3RequestProfiler\Profiling;

use Psr\Http\Message\UriInterface;

use function str_contains;

/**
 * UrlSanitizer.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class UrlSanitizer
{
    /**
     * Mask query parameter values while keeping the parameter names, so the
     * profiled URL stays diagnostic (which parameters were involved) without
     * persisting user data — query values regularly carry search terms,
     * e-mail addresses or one-time tokens.
     */
    public static function maskQueryValues(UriInterface $uri): string
    {
        $url = (string) $uri;
        $query = $uri->getQuery();
        if ('' === $query) {
            return $url;
        }

        $masked = implode('&', array_map(
            static fn (string $pair): string => str_contains($pair, '=')
                ? strstr($pair, '=', true).'=?'
                : $pair,
            explode('&', $query),
        ));

        // The first "?" in a URI is always the query delimiter; server requests
        // carry no fragment, so everything after it is the query itself.
        return strstr($url, '?', true).'?'.$masked;
    }
}
