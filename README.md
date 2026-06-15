<div align="center">

# TYPO3 extension `typo3_request_profiler`

[![Packagist](https://img.shields.io/packagist/v/konradmichalik/typo3-request-profiler?label=version&logo=packagist)](https://packagist.org/packages/konradmichalik/typo3-request-profiler)
[![Packagist Downloads](https://img.shields.io/packagist/dt/konradmichalik/typo3-request-profiler?color=brightgreen)](https://packagist.org/packages/konradmichalik/typo3-request-profiler)
[![Supported PHP Versions](https://img.shields.io/packagist/dependency-v/konradmichalik/typo3-request-profiler/php?logo=php)](https://packagist.org/packages/konradmichalik/typo3-request-profiler)
[![CGL](https://img.shields.io/github/actions/workflow/status/konradmichalik/typo3-request-profiler/cgl.yml?label=cgl&logo=github)](https://github.com/konradmichalik/typo3-request-profiler/actions/workflows/cgl.yml)
[![Tests](https://img.shields.io/github/actions/workflow/status/konradmichalik/typo3-request-profiler/tests.yml?label=tests&logo=github)](https://github.com/konradmichalik/typo3-request-profiler/actions/workflows/tests.yml)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE.md)

</div>

A dev-only TYPO3 frontend request profiler. It instruments live frontend requests and writes one compact JSON profile per request — SQL queries, N+1 patterns, cache state, and timing — to `var/log/profiles/{request_id}.json`.

> [!IMPORTANT]
> This extension is **active only in a Development context** (`Environment::getContext()->isDevelopment()`). It registers no middleware and collects no data in production.

**Key design:** the profiler is a thin, standalone collector with no external dependencies. The JSON profiles are designed to be consumed by AI coding assistants via [`konradmichalik/typo3-ai-mate`](https://github.com/konradmichalik/typo3-ai-mate) and its `typo3_performance` MCP tool, correlated by the shared `token`/`request_id`. Collect here — expose there.

> [!WARNING]
> This package is in early development stage and may change significantly in the future. I am working steadily to release a stable version as soon as possible.

**What it captures per request:**

- Wall-clock and SQL timing, peak memory usage
- Full query count + top slow queries + N+1 duplicate detection
- Cache hit/miss state with disabled reasons
- Optional call-site origin (`Class::method (file:line)`) for every flagged query

## 🔥 Installation

```bash
composer require --dev konradmichalik/typo3-request-profiler
```

No further configuration is needed. In a Development context the profiler registers a Doctrine driver middleware and a frontend PSR-15 middleware automatically.

## ✨ Requirements

- TYPO3 **v13.4** or **v14.3**
- PHP **8.2+**, Composer mode
- Doctrine DBAL 3.x or 4.x

## ⚙️ Configuration

The profiler is controlled entirely via environment variables:

| Variable | Default | Effect |
|----------|---------|--------|
| `TYPO3_REQUEST_PROFILER` | (on) | Set to `0` to disable profiling for a request/process. |
| `TYPO3_REQUEST_PROFILER_MIN_MS` | `0` | Only persist requests whose total time exceeds this threshold (ms). |
| `TYPO3_REQUEST_PROFILER_KEEP` | `50` | Number of most-recent profiles to retain; older files are pruned automatically. |
| `TYPO3_REQUEST_PROFILER_TRACE` | (off) | Set to `1` to capture the calling `Class::method (file:line)` for each query (added as `origin` to `slow_queries`/`duplicate_queries`). |

> [!TIP]
> `TYPO3_REQUEST_PROFILER_TRACE=1` uses `debug_backtrace` per query and is therefore opt-in for performance. No bound parameter values are ever captured — only the call site.

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
  "queries": { "count": 101, "total_ms": 38.2 },
  "slow_queries": [
    { "sql": "SELECT * FROM pages WHERE slug = ? ORDER BY slug desc", "ms": 12.4 }
  ],
  "duplicate_queries": [
    { "sql": "SELECT COUNT(*) FROM tt_content WHERE pid = ? AND deleted = ?", "count": 100, "total_ms": 31.4 }
  ]
}
```

**Field reference:**

- `token` — equals `TYPO3\CMS\Core\Core\RequestId`; correlates with the `request="…"` value in the TYPO3 logs and with the `typo3_performance` MCP tool in `typo3-ai-mate`.
- `cache.hit` — true only when the page is cacheable and was not regenerated this request. `cache.cacheable` comes from the `frontend.cache.instruction` request attribute and disambiguates `no_cache`/`USER_INT` pages. `cache.disabled_reasons` (present only when uncached) lists why caching was disabled — the most common cause of slow pages.
- `memory.peak_mb` — request peak memory via `memory_get_peak_usage`.
- `slow_queries` — top 5 single executions by wall-clock time; surfaces one expensive query even without an N+1.
- `duplicate_queries` — top 20 normalised queries with `count > 1`, sorted by count descending. SQL normalisation replaces literals with `?`, collapses whitespace, and reduces `IN (…)` lists to `IN (?)`.
- `origin` (optional) — present on `slow_queries`/`duplicate_queries` entries when `TYPO3_REQUEST_PROFILER_TRACE=1`; points straight at the code that issued the query.

> [!NOTE]
> Dev-only connection and schema introspection queries (`information_schema`, `SELECT DATABASE()`, `SHOW …`) are automatically filtered out so application queries remain in focus.

## 🧑‍💻 Contributing

Please have a look at [`CONTRIBUTING.md`](CONTRIBUTING.md).

## ⭐ License

This project is licensed under [GNU General Public License 2.0 (or later)](LICENSE.md).
