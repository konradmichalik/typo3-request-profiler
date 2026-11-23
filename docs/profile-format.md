# Profile Format

Each request produces one JSON file at `var/log/profiles/{request_id}.json`:

```json
{
  "schemaVersion": 1,
  "token": "<RequestId>",
  "time": "2026-06-15T10:00:00+00:00",
  "method": "GET",
  "url": "https://example.ddev.site/",
  "status": 200,
  "meta": {
    "activationMode": "context",
    "applicationContext": "Development",
    "typo3Version": "13.4.6",
    "extensionVersion": "0.4.0"
  },
  "page": { "id": 1, "type": 0 },
  "cache": { "hit": false, "cacheable": false, "disabled_reasons": ["&no_cache=1 query parameter was given"] },
  "timing": { "total_ms": 142.5 },
  "memory": { "peak_mb": 16.1, "limit_mb": 512, "peak_pct": 3.1 },
  "php": { "included_files": 432 },
  "queries": { "count": 101, "total_ms": 38.2 },
  "slow_queries": [
    { "sql": "SELECT * FROM pages WHERE slug = ? ORDER BY slug desc", "ms": 12.4 }
  ],
  "duplicate_queries": [
    { "sql": "SELECT COUNT(*) FROM tt_content WHERE pid = ? AND deleted = ?", "count": 100, "total_ms": 31.4 }
  ],
  "http": { "count": 3, "total_ms": 812.4 },
  "slow_http": [
    { "method": "GET", "url": "https://api.example.org/v1/products?page=?", "ms": 604.1, "status": 200 }
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
> The `log` section only appears when the request produced log entries. Only the level and component are recorded, never the message body, so no user data leaks into the profile.

> [!NOTE]
> The `events` section only appears when `TYPO3_REQUEST_PROFILER_EVENTS=1` (see [Configuration](configuration.md)).

## Profile schema

The artifact carries an explicit, versioned schema contract via the top-level
`schemaVersion` field. It is written first so it is immediately visible in every file.

**Top-level fields:**

| Field | Type | Description |
|-------|------|-------------|
| `schemaVersion` | int | Schema contract version of the artifact (currently `1`). |
| `token` | string | Request identifier; also the file name. |
| `time` | string | Request time as ISO 8601 (`date('c')`). |
| `method` | string | HTTP request method. |
| `url` | string | Request URI with masked query values (`?q=?&page=?`): parameter names are kept, values are never persisted (they regularly carry search terms, e-mail addresses or one-time tokens). Query parts without `=` are masked as `?`, and userinfo (`user:password@`) is dropped. |
| `status` | int | HTTP response status code. Absent when the request threw before a response existed; see the `exception` section below. |
| `meta` | object | Provenance: `activationMode` (`context`/`stateFile`/`header`), `applicationContext`, `typo3Version`, `extensionVersion`. Lets a consumer tell "page-cache hit" apart from "wrong mode/context" without guessing. Note `activationMode` reflects why profiling was active, not whether the HTTP header trigger's correlation header was also sent; see [Activation](activation.md). |

> [!NOTE]
> Adding the `meta` block is an additive change and does not bump `schemaVersion`. Existing top-level keys are unchanged. Future additive changes (new optional fields/sections) follow the same rule; only a breaking change (renamed/removed/restructured field) bumps `schemaVersion`.

**Section keys** (key = `Section::name()`; each appears only when the section is enabled and produced data):

| Key | Shape |
|-----|-------|
| `page` | `{ id, type }` |
| `cache` | `{ hit, cacheable, disabled_reasons[] }` |
| `timing` | `{ total_ms }` |
| `memory` | `{ peak_mb, limit_mb, peak_pct? }` — `limit_mb` is `null` and `peak_pct` is omitted when `memory_limit` is unlimited (`-1`) |
| `php` | `{ included_files }` |
| `queries` | `{ count, total_ms, truncated? }` |
| `slow_queries` | `[{ sql, ms, origin? }]` |
| `duplicate_queries` | `[{ sql, count, total_ms, origin? }]` |
| `http` | `{ count, total_ms, truncated? }` |
| `slow_http` | `[{ method, url, ms, status? }]` — the 5 slowest outgoing HTTP calls, sorted by duration descending |
| `log` | `{ count, by_level{}, top_components[{ component, count }] }` |
| `events` | `{ count, total_ms, top[{ event, count, total_ms }] }` |
| `exception` | `{ class, file, line, code? }` |

> [!NOTE]
> The `exception` section's `file` is relative to the TYPO3 project path, or just the file name when the file lies outside the project, so no absolute path is persisted. The `exception` section appears only when the request threw before a response was produced (uncaught in the frontend request handler). It never carries the exception message: messages regularly contain user input, record data, or absolute paths. The artifact's `status` field is absent in this case, and the exception still propagates to TYPO3's regular error handling unchanged; the profiler only observes it.

> [!NOTE]
> `truncated: true` appears in `queries` and `http` only when the collector cap of 10,000 recorded entries was exceeded, as happens in long-running processes. `count` and `total_ms` stay exact. `slow_queries`, `duplicate_queries` and `slow_http` are then derived from the first 10,000 entries only. Below the cap the key is absent.

> The `http`/`slow_http` sections cover outgoing calls made through TYPO3's Guzzle client factory (e.g. extensions calling external APIs) and, when wired up as described in [Symfony HttpClient](#symfony-httpclient), calls made with Symfony HttpClient. `status` is absent on a call that never received a response, such as a connect timeout. URLs go through the same masking as the top-level `url` field (masked query values, value-only query parts and dropped userinfo). Secrets inside path segments are not masked.

> [!NOTE]
> Every `sql` string in `slow_queries` and `duplicate_queries` is normalised: comments are dropped, string, hex and numeric literals and `:dcValueN` placeholders become `?`, `IN (...)` lists collapse to `IN (?)`, and the result is capped at 1,000 characters (cut with a trailing `...`). Known limitation: double-quoted text is not masked, because it is an identifier quote on PostgreSQL and SQLite. A value written as `"max@example.org"` (MySQL without `ANSI_QUOTES`) therefore stays in clear text.

> [!NOTE]
> `schemaVersion` is incremented only when field names or shapes change in a breaking way. Additive changes keep the same version.

## Reading profiles

`KonradMichalik\Typo3RequestProfiler\Profiling\ProfileReader` is the supported, framework-agnostic read API for these artifacts. External consumers should use it instead of re-implementing the `glob`/sort/`json_decode` logic:

| Method | Returns |
|--------|---------|
| `all()` | All profiles, newest first. |
| `latest(int $limit = 10)` | The `$limit` newest profiles, newest first. |
| `byToken(string $token)` | A single profile by its token, or `null` if unknown. |

The reader is directory-based and carries no framework dependency. Its constructor takes the profiles directory (`new ProfileReader($directory)`). On the TYPO3 side, that directory is `ProfileWriter::defaultDirectory()` (the same source the writer persists to). Its public signature is kept stable as a contract for consumers.

## See also

- [Activation](activation.md): the four ways to turn profiling on, and what populates `meta.activationMode`
- [Configuration](configuration.md): sampling, retention, tracing, and event timing

## Symfony HttpClient

Extensions that use `symfony/http-client` instead of Guzzle are not seen by default. The `ProfilingHttpClient` decorator records their calls into the same `http` and `slow_http` sections. It needs `symfony/http-client-contracts`, which TYPO3 core does not require, so install it (any `symfony/http-client` pulls it in) before using the decorator. Without it the extension works as before and the decorator stays unregistered.

- **Decorate a container service:** when an extension or your project registers `Symfony\Contracts\HttpClient\HttpClientInterface` as a service, it is decorated automatically and needs no configuration. Nothing happens when no such service exists.
- **Wrap a self-built client:** for extensions that create the client themselves, wrap it:

```php
use KonradMichalik\Typo3RequestProfiler\Profiling\Collector\HttpCollector;
use KonradMichalik\Typo3RequestProfiler\Profiling\Instrumentation\Http\ProfilingHttpClient;
use Symfony\Component\HttpClient\HttpClient;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$client = new ProfilingHttpClient(
    HttpClient::create(),
    GeneralUtility::makeInstance(HttpCollector::class),
);
```

Responses are lazy, so a call is recorded once its body was read (`getContent()`, `toArray()`), the response was canceled or, as the fallback, when the response object is destroyed. Reading only the status or the headers does not record it yet. `withOptions()` returns a decorated client again. Headers and bodies are never recorded, and the URL is masked as described above. A response that is still unfinished when it is destroyed reports the `total_time` reached by then.
