# TYPO3 Request Profiler

[![Tests](https://github.com/konradmichalik/typo3-request-profiler/actions/workflows/tests.yml/badge.svg)](https://github.com/konradmichalik/typo3-request-profiler/actions/workflows/tests.yml)
[![CGL](https://github.com/konradmichalik/typo3-request-profiler/actions/workflows/cgl.yml/badge.svg)](https://github.com/konradmichalik/typo3-request-profiler/actions/workflows/cgl.yml)
[![License: GPL-2.0-or-later](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE.md)

A **dev-only** TYPO3 frontend request profiler. It instruments live frontend
requests and writes one compact JSON profile per request — SQL queries, N+1
patterns, cache state and timing — to `var/log/profiles/{request_id}.json`.

The profiles are designed to be consumed by AI coding assistants (via
[`konradmichalik/typo3-ai-mate`](https://github.com/konradmichalik/typo3-ai-mate)
and `symfony/ai-mate`), so an assistant can answer *"this page is slow, find the
performance problem"* from facts instead of guessing across source files. The
profiler itself has **no** ai-mate dependency and is usable standalone.

## Requirements

- TYPO3 **v13.4** or **v14.0** (one codebase; v12 is not supported)
- PHP **8.2+**, Composer mode
- Doctrine DBAL 3.x or 4.x
- Active **only** in a Development context (`Environment::getContext()->isDevelopment()`)

## Installation

```bash
composer require --dev konradmichalik/typo3-request-profiler
```

No further configuration is needed. In a Development context the profiler
registers a Doctrine driver middleware and a frontend PSR-15 middleware
automatically.

## How it works

| Component | Role |
|-----------|------|
| `Profiling/Doctrine/*` | Doctrine driver-middleware chain that times every `query`/`exec`/prepared-statement execution and feeds the collector. |
| `QueryCollector` | Request-scoped singleton holding the collected queries and the cache-generation flag. |
| `CachePersistListener` | Listens for `AfterCachedPageIsPersistedEvent` (a cache miss = page was generated). |
| `ProfileWriter` | Aggregates queries (SQL normalisation + N+1 detection) and writes the JSON profile. |
| `PerformanceProfilerMiddleware` | Frontend PSR-15 middleware; wall-clock timing, fail-safe profile writing. |

### Profile format

```json
{
  "token": "<RequestId>",
  "time": "2026-06-15T10:00:00+00:00",
  "method": "GET",
  "url": "https://example.ddev.site/",
  "status": 200,
  "page": { "id": 1, "type": 0 },
  "cache": { "hit": false, "cacheable": false },
  "timing": { "total_ms": 142.5 },
  "queries": { "count": 101, "total_ms": 38.2 },
  "duplicate_queries": [
    { "sql": "SELECT COUNT(*) FROM tt_content WHERE pid = ? AND deleted = ?", "count": 100, "total_ms": 31.4 }
  ]
}
```

- `token` equals `TYPO3\CMS\Core\Core\RequestId` and correlates with the
  `request="…"` value in the TYPO3 logs.
- `cache.hit` is true only when the page is cacheable **and** was not (re)generated
  this request; `cache.cacheable` comes from the `frontend.cache.instruction`
  request attribute and disambiguates `no_cache`/`USER_INT` pages.
- `duplicate_queries` lists the top 20 normalised queries with `count > 1`,
  sorted by count descending. SQL normalisation replaces literals with `?`,
  collapses whitespace and reduces `IN (…)` lists to `IN (?)`.

## Configuration

The profiler is dev-only and opt-out:

```bash
# Disable profiling for a single request / process
TYPO3_REQUEST_PROFILER=0
```

Only the last 50 profiles are kept; older files are pruned automatically.

## Development

```bash
git clone git@github.com:konradmichalik/typo3-request-profiler.git
cd typo3-request-profiler
composer install

# Multi-version test environment (TYPO3 13 + 14)
ddev add-on get konradmichalik/ddev-typo3-multi-version-extension
# Limit TYPO3_VERSIONS to "13,14" in .ddev/docker-compose.typo3-setup.yaml
ddev restart && ddev install all
ddev launch 13   # or: ddev launch 14

# Tests & coding standards
composer test
composer cgl install && composer cgl lint && composer cgl sca
```

The `Tests/Acceptance/Fixtures/packages/sitepackage` package contains a
deliberate N+1 demo page for verifying the profiler end to end.

## License

[GPL-2.0-or-later](LICENSE.md)
