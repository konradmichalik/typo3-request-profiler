<?php

declare(strict_types=1);

namespace Test\Sitepackage\ContentObject;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Deliberately N+1: one COUNT query per page. Used as a USER_INT object so the
 * page stays uncached and the profiler records the repeated query on every FE
 * request. Drives the "slow page" acceptance scenario.
 */
final class NplusOneDemoRenderer
{
    /**
     * @param array<string, mixed> $conf
     */
    public function render(string $content, array $conf): string
    {
        $pool = GeneralUtility::makeInstance(ConnectionPool::class);

        $pages = $pool->getConnectionForTable('pages')
            ->executeQuery('SELECT uid, title FROM pages WHERE deleted = 0 AND hidden = 0 ORDER BY uid')
            ->fetchAllAssociative();

        $items = [];
        foreach ($pages as $page) {
            // N+1: this query runs once per page instead of a single JOIN/GROUP BY.
            $count = $pool->getConnectionForTable('tt_content')
                ->executeQuery('SELECT COUNT(*) FROM tt_content WHERE pid = ' . (int)$page['uid'] . ' AND deleted = 0')
                ->fetchOne();

            $items[] = sprintf(
                '<li>%s: %d content elements</li>',
                htmlspecialchars((string)$page['title']),
                (int)$count,
            );
        }

        return '<ul class="nplusone-demo">' . implode('', $items) . '</ul>';
    }
}
