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

use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\HttpCollector;
use KonradMichalik\Typo3RequestProfiler\Profiling\Instrumentation\Http\ProfilingHttpClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function Symfony\Component\DependencyInjection\Loader\Configurator\{inline_service, service};

return static function (ContainerConfigurator $containerConfigurator): void {
    // symfony/http-client-contracts is optional. The decorator implements one of
    // its interfaces, so it must not reach the container (or its class scan)
    // without the package. Without a registered HttpClientInterface service the
    // decoration is skipped as well.
    if (!interface_exists(HttpClientInterface::class)) {
        return;
    }

    $containerConfigurator->services()
        ->set(ProfilingHttpClient::class)
        ->decorate(HttpClientInterface::class, null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
        ->args([
            service('.inner'),
            // The collector is a singleton shared via makeInstance(), not a container service.
            inline_service(HttpCollector::class)
                ->factory([GeneralUtility::class, 'makeInstance'])
                ->args([HttpCollector::class]),
        ])
    ;
};
