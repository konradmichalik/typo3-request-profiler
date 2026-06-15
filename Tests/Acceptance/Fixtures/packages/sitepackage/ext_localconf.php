<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// Minimal uncached page that triggers an N+1 query pattern for profiler testing.
ExtensionManagementUtility::addTypoScriptSetup(<<<'TYPOSCRIPT'
page = PAGE
page {
    typeNum = 0
    10 = USER_INT
    10.userFunc = Test\Sitepackage\ContentObject\NplusOneDemoRenderer->render
}
TYPOSCRIPT);
