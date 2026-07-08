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

use KonradMichalik\Typo3RequestProfiler\Profiling\Section\{ProfileContext, ProfileSection};
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Traversable;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function array_slice;
use function count;
use function iterator_to_array;

/**
 * ProfileWriter.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class ProfileWriter
{
    /**
     * Schema version of the written profile artifact. Increment whenever
     * top-level field or section names/shapes change in a breaking way.
     */
    public const SCHEMA_VERSION = 1;

    private const DEFAULT_MAX_PROFILES = 50;

    /**
     * @param iterable<ProfileSection> $sections
     */
    public function __construct(
        private iterable $sections,
    ) {}

    /**
     * The single source of truth for where profiles live: the directory the
     * writer persists to and that TYPO3-side {@see ProfileReader} callers read
     * from. Keeps the (TYPO3-specific) path resolution on the writer side so
     * the reader itself stays framework-agnostic.
     */
    public static function defaultDirectory(): string
    {
        return Environment::getVarPath().'/log/profiles';
    }

    public function write(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $token,
        float $totalMs,
    ): void {
        $directory = self::defaultDirectory();
        GeneralUtility::mkdir_deep($directory);

        $context = new ProfileContext($request, $response, $token, $totalMs);

        $profile = [
            'schemaVersion' => self::SCHEMA_VERSION,
            'token' => $token,
            'time' => date('c'),
            'method' => $request->getMethod(),
            'url' => UrlSanitizer::maskQueryValues($request->getUri()),
            'status' => $response->getStatusCode(),
        ];

        foreach ($this->sortedSections() as $section) {
            if (!$section->isEnabled()) {
                continue;
            }
            $payload = $section->collect($context);
            if (null !== $payload) {
                $profile[$section->name()] = $payload;
            }
        }

        $json = json_encode($profile, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        if (false !== $json) {
            $this->writeAtomically($directory.'/'.$token.'.json', $json);
        }

        $this->prune($directory);
    }

    /**
     * Write to a sibling temp file and atomically rename it into place, so a
     * concurrent {@see ProfileReader} never observes a half-written profile.
     * fixPermissions() applies TYPO3's configured fileCreateMask; the rename
     * carries those permissions over to the final file.
     */
    private function writeAtomically(string $target, string $json): void
    {
        // The token is a unique request id, so the temp name never collides
        // with a concurrent request writing its own profile.
        $temp = $target.'.tmp';
        if (false === @file_put_contents($temp, $json)) {
            return;
        }

        GeneralUtility::fixPermissions($temp);
        if (!@rename($temp, $target)) {
            @unlink($temp);
        }
    }

    /**
     * @return list<ProfileSection>
     */
    private function sortedSections(): array
    {
        $sections = $this->sections instanceof Traversable
            ? iterator_to_array($this->sections, false)
            : array_values($this->sections);

        usort($sections, static fn (ProfileSection $a, ProfileSection $b): int => $a->priority() <=> $b->priority());

        return $sections;
    }

    private function prune(string $directory): void
    {
        $keep = $this->maxProfiles();
        $files = glob($directory.'/*.json') ?: [];
        if (count($files) <= $keep) {
            return;
        }

        usort($files, static fn (string $a, string $b): int => filemtime($a) <=> filemtime($b));
        foreach (array_slice($files, 0, count($files) - $keep) as $stale) {
            @unlink($stale);
        }
    }

    /**
     * Number of profiles to retain. Configurable via TYPO3_REQUEST_PROFILER_KEEP
     * (defaults to 50); invalid or non-positive values fall back to the default.
     */
    private function maxProfiles(): int
    {
        $configured = (int) getenv('TYPO3_REQUEST_PROFILER_KEEP');

        return $configured > 0 ? $configured : self::DEFAULT_MAX_PROFILES;
    }
}
