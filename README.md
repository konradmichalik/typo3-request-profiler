<div align="center">

![Extension icon](Resources/Public/Icons/Extension.png)

# TYPO3 extension `typo3_request_profiler`

[![Packagist](https://img.shields.io/packagist/v/konradmichalik/typo3-request-profiler?label=version&logo=packagist)](https://packagist.org/packages/konradmichalik/typo3-request-profiler)
[![Packagist Downloads](https://img.shields.io/packagist/dt/konradmichalik/typo3-request-profiler?color=brightgreen)](https://packagist.org/packages/konradmichalik/typo3-request-profiler)
[![Supported PHP Versions](https://img.shields.io/packagist/dependency-v/konradmichalik/typo3-request-profiler/php?logo=php)](https://packagist.org/packages/konradmichalik/typo3-request-profiler)
[![CGL](https://img.shields.io/github/actions/workflow/status/konradmichalik/typo3-request-profiler/cgl.yml?label=cgl&logo=github)](https://github.com/konradmichalik/typo3-request-profiler/actions/workflows/cgl.yml)
[![Coverage](https://img.shields.io/coverallsCoverage/github/konradmichalik/typo3-request-profiler?logo=coveralls)](https://coveralls.io/github/konradmichalik/typo3-request-profiler)
[![Tests](https://img.shields.io/github/actions/workflow/status/konradmichalik/typo3-request-profiler/tests.yml?label=tests&logo=github)](https://github.com/konradmichalik/typo3-request-profiler/actions/workflows/tests.yml)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE.md)

</div>

A _dev-only_ TYPO3 frontend request profiler. It instruments live frontend requests and writes one compact JSON profile per request — SQL queries, N+1 patterns, cache state, and timing — to `var/log/profiles/{request_id}.json`.

> [!IMPORTANT]
> This extension is **active only in a Development context** (`Environment::getContext()->isDevelopment()`). It registers no middleware and collects no data in production.

The profiler is a thin, standalone collector with no external dependencies. It is inspired by the [Symfony Profiler](https://symfony.com/doc/current/profiler.html) — and by some of the metrics the [TYPO3 Admin Panel](https://docs.typo3.org/c/typo3/cms-adminpanel/main/en-us/) surfaces — but records them as compact, machine-readable JSON instead of an interactive panel.

> [!WARNING]
> This package is in early development stage and may change significantly in the future. I am working steadily to release a stable version as soon as possible.

**What it captures per request:**

- Wall-clock and SQL timing, peak memory usage, included PHP file count
- Full query count + top slow queries + N+1 duplicate detection
- Cache hit/miss state with disabled reasons
- Log activity per request (count by level + noisiest components)
- Optional call-site origin (`Class::method (file:line)`) for every flagged query

## 🔥 Installation

### Requirements

* TYPO3 13.4 LTS & 14.0+
* PHP 8.2+
* Doctrine DBAL 3.x or 4.x

### Supports

| **Version** | **TYPO3** | **PHP** |
|-------------|-----------|---------|
| 0.x         | 13-14     | 8.2-8.5 |

### Composer

[![Packagist](https://img.shields.io/packagist/v/konradmichalik/typo3-request-profiler?label=version&logo=packagist)](https://packagist.org/packages/konradmichalik/typo3-request-profiler)
[![Packagist Downloads](https://img.shields.io/packagist/dt/konradmichalik/typo3-request-profiler?color=brightgreen)](https://packagist.org/packages/konradmichalik/typo3-request-profiler)

```bash
composer require --dev konradmichalik/typo3-request-profiler
```

## ⚙️ Configuration

The profiler is controlled entirely via environment variables:

| Variable | Default | Effect |
|----------|---------|--------|
| `TYPO3_REQUEST_PROFILER` | (on) | Set to `0` to disable profiling for a request/process. |
| `TYPO3_REQUEST_PROFILER_MIN_MS` | `0` | Only persist requests whose total time exceeds this threshold (ms). |
| `TYPO3_REQUEST_PROFILER_KEEP` | `50` | Number of most-recent profiles to retain; older files are pruned automatically. |
| `TYPO3_REQUEST_PROFILER_TRACE` | (off) | Set to `1` to capture the calling `Class::method (file:line)` for each query (added as `origin` to `slow_queries`/`duplicate_queries`). |
| `TYPO3_REQUEST_PROFILER_EVENTS` | (off) | Set to `1` to time dispatched PSR-14 events and add an `events` section (count + the most expensive event classes). |

> [!TIP]
> `TYPO3_REQUEST_PROFILER_TRACE=1` uses `debug_backtrace` per query and is therefore opt-in for performance. No bound parameter values are ever captured — only the call site.

> [!TIP]
> `TYPO3_REQUEST_PROFILER_EVENTS=1` wraps the core PSR-14 dispatcher and measures every dispatched event. Dispatch happens very frequently, so the per-event timing is opt-in. When off, events are dispatched without any measurement and the `events` section is omitted.

## 💡 Profile Format

Each request produces one JSON file at `var/log/profiles/{request_id}.json`:

```json
{
  "token": "<RequestId>",
  "time": "2026-06-15T10:00:00+00:00",
  "method": "GET",
  "url": "https://example.ddev.site/",
  "status": 200,
  "page": { "id": 1, "type": 0 },
  "cache": { "hit": false, "cacheable": false, "disabled_reasons": ["&no_cache=1 query parameter was given"] },
  "timing": { "total_ms": 142.5 },
  "memory": { "peak_mb": 16.1 },
  "php": { "included_files": 432 },
  "queries": { "count": 101, "total_ms": 38.2 },
  "slow_queries": [
    { "sql": "SELECT * FROM pages WHERE slug = ? ORDER BY slug desc", "ms": 12.4 }
  ],
  "duplicate_queries": [
    { "sql": "SELECT COUNT(*) FROM tt_content WHERE pid = ? AND deleted = ?", "count": 100, "total_ms": 31.4 }
  ],
  "log": {
    "count": 3,
    "by_level": { "warning": 2, "notice": 1 },
    "top_components": [
      { "component": "TYPO3.CMS.Core.Authentication.BackendUserAuthentication", "count": 2 }
    ]
  },
  "events": {
    "count": 142,
    "total_ms": 12.3,
    "top": [
      { "event": "TYPO3\\CMS\\Core\\Cache\\Event\\CacheFlushEvent", "count": 100, "total_ms": 8.1 }
    ]
  }
}
```

> [!NOTE]
> The `log` section only appears when the request produced log entries. Only the level and component are recorded — never the message body — so no user data leaks into the profile.

> [!NOTE]
> The `events` section only appears when `TYPO3_REQUEST_PROFILER_EVENTS=1`.

## 🧑‍💻 Contributing

Please have a look at [`CONTRIBUTING.md`](CONTRIBUTING.md).

## ⭐ License

This project is licensed under [GNU General Public License 2.0 (or later)](LICENSE.md).
