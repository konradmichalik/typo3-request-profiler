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
> The `log` section only appears when the request produced log entries. Only the level and component are recorded, never the message body, so no user data leaks into the profile.

> [!NOTE]
> The `events` section only appears when `TYPO3_REQUEST_PROFILER_EVENTS=1` (see [Configuration](CONFIGURATION.md)).

## Profile schema

The artifact carries an explicit, versioned schema contract via the top-level
`schemaVersion` field. It is written first so it is immediately visible in every file.

**Top-level fields** (always present):

| Field | Type | Description |
|-------|------|-------------|
| `schemaVersion` | int | Schema contract version of the artifact (currently `1`). |
| `token` | string | Request identifier; also the file name. |
| `time` | string | Request time as ISO 8601 (`date('c')`). |
| `method` | string | HTTP request method. |
| `url` | string | Request URI with masked query values (`?q=?&page=?`): parameter names are kept, values are never persisted (they regularly carry search terms, e-mail addresses or one-time tokens). |
| `status` | int | HTTP response status code. |
| `meta` | object | Provenance: `activationMode` (`context`/`stateFile`/`header`), `applicationContext`, `typo3Version`, `extensionVersion`. Lets a consumer tell "page-cache hit" apart from "wrong mode/context" without guessing. Note `activationMode` reflects why profiling was active, not whether the HTTP header trigger's correlation header was also sent; see [Activation](ACTIVATION.md). |

> [!NOTE]
> Adding the `meta` block is an additive change and does not bump `schemaVersion`. Existing top-level keys are unchanged. Future additive changes (new optional fields/sections) follow the same rule; only a breaking change (renamed/removed/restructured field) bumps `schemaVersion`.

**Section keys** (key = `Section::name()`; each appears only when the section is enabled and produced data):

| Key | Shape |
|-----|-------|
| `page` | `{ id, type }` |
| `cache` | `{ hit, cacheable, disabled_reasons[] }` |
| `timing` | `{ total_ms }` |
| `memory` | `{ peak_mb }` |
| `php` | `{ included_files }` |
| `queries` | `{ count, total_ms }` |
| `slow_queries` | `[{ sql, ms, origin? }]` |
| `duplicate_queries` | `[{ sql, count, total_ms, origin? }]` |
| `log` | `{ count, by_level{}, top_components[{ component, count }] }` |
| `events` | `{ count, total_ms, top[{ event, count, total_ms }] }` |

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

- [Activation](ACTIVATION.md): the four ways to turn profiling on, and what populates `meta.activationMode`
- [Configuration](CONFIGURATION.md): sampling, retention, tracing, and event timing
