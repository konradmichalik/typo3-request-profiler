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

use Composer\Autoload;
use ShipMonk\ComposerDependencyAnalyser;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$rootPath = dirname(__DIR__, 2);

/** @var Autoload\ClassLoader $loader */
$loader = require $rootPath.'/vendor/autoload.php';
$loader->register();

$configuration = new ComposerDependencyAnalyser\Config\Configuration();
$configuration
    ->addPathToScan($rootPath.'/Configuration', false)
    // Optional on purpose: only ProfilingHttpClient needs it, and
    // Configuration/Services.php registers that class only when it is installed.
    ->ignoreErrorsOnPackage('symfony/http-client-contracts', [ErrorType::DEV_DEPENDENCY_IN_PROD])
    // Plain functions declared next to the class in the container configurator,
    // outside composer's autoload map, so the analyser cannot resolve them.
    ->ignoreUnknownFunctions([
        'Symfony\Component\DependencyInjection\Loader\Configurator\service',
        'Symfony\Component\DependencyInjection\Loader\Configurator\inline_service',
    ])
    // Provided by typo3/cms-core, which fixes the PSR-7 implementation for every install.
    ->ignoreErrorsOnPackageAndPath('guzzlehttp/psr7', $rootPath.'/Tests', [ErrorType::SHADOW_DEPENDENCY])
    ->addPathsToExclude([
        $rootPath.'/Tests/CGL',
    ])
;

return $configuration;
