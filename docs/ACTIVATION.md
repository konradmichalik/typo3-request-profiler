# Activation

The profiler has four ways to turn on, checked cheapest-first per request. `TYPO3_REQUEST_PROFILER=0` is a kill switch that wins over all of them.

| # | Mechanism | Scope | Needs a cache flush? |
|---|-----------|-------|-----------------------|
| 1 | [Development context](#1-development-context) | Every request, always | No |
| 2 | [Force via env var](#2-force-outside-development) | Every request on that instance | No |
| 3 | [Temporary toggle](#3-temporary-toggle-cli) | Every request, until expiry | No |
| 4 | [HTTP header trigger](#4-http-header-trigger-per-request) | A single request | No |

> [!NOTE]
> Query/log instrumentation is wired up unconditionally at bootstrap (a cheap in-memory append per request) precisely so that none of the four mechanisms below ever need a cache flush to take effect.

## 1. Development context

Active by default whenever `Environment::getContext()->isDevelopment()`, no configuration needed. This is the primary, frictionless use case: local development.

## 2. Force outside Development

| Variable | Default | Effect |
|----------|---------|--------|
| `TYPO3_REQUEST_PROFILER` | (on) | Set to `0` to disable profiling for a request/process. This is the kill switch: it wins over every other mechanism below. |
| `TYPO3_REQUEST_PROFILER_FORCE` | (off) | Set to `1` to enable profiling outside the Development context (e.g. staging). Must be set deliberately, never in real production. |

## 3. Temporary toggle (CLI)

An xdebug-style toggle, useful for a quick investigation on an environment that otherwise stays off:

```bash
vendor/bin/typo3 profiler:activate                # 15 minutes (default)
vendor/bin/typo3 profiler:activate --duration=1h  # custom duration: Ns, Nm, or Nh (max 7 days)
vendor/bin/typo3 profiler:deactivate               # turn it back off immediately
```

The toggle writes a small state file (with an expiry timestamp) under `var/log/`; an expired, missing, or unreadable state file always counts as inactive.

> [!NOTE]
> The state-file mechanism follows `var/log`'s own topology: if it's node-local, `profiler:activate` only affects the node it runs on. If `var/` is a shared directory across a multi-node setup, activation applies to every node reading that shared state file, so plan the toggle's scope accordingly.

## 4. HTTP header trigger (per-request)

For automated tooling (Playwright, curl, LLM agents) that needs to know exactly which artifact belongs to which request ("the newest profile" is ambiguous once requests run concurrently), send the `Typo3-Profiler` header:

```bash
# Development: no token needed
curl -H "Typo3-Profiler: 1" https://example.ddev.site/

# Outside Development: token must match TYPO3_REQUEST_PROFILER_SECRET
curl -H "Typo3-Profiler: $TYPO3_REQUEST_PROFILER_SECRET" https://staging.example.org/
```

| Variable | Default | Effect |
|----------|---------|--------|
| `TYPO3_REQUEST_PROFILER_SECRET` | (unset) | Shared secret for the `Typo3-Profiler` header outside Development. Provision via environment variable, never persisted extension configuration. |

A valid trigger adds a response header with the artifact's identity, and marks the response `Cache-Control: no-store` so the header never ends up in a CDN/Varnish cache:

```
Typo3-Profiler-Artifact: <token>
```

Resolve `<token>.json` under `var/log/profiles/` (or via the CLI/MCP tooling) to get the artifact. The trigger works independently of whatever already made this request profiled (Development context, the state-file toggle). Even if profiling was already active, sending the header still gets you the correlation header and bypasses `TYPO3_REQUEST_PROFILER_MIN_MS` sampling for that one request.

> [!IMPORTANT]
> Outside Development, the trigger is **hard-disabled** unless `TYPO3_REQUEST_PROFILER_SECRET` is configured (minimum 32 random bytes recommended, e.g. `openssl rand -hex 32`). An invalid or missing token is indistinguishable from not sending the header at all: no error, no hint, no response change. This means the endpoint can't be used to probe whether the feature or a valid token exists. Accept the trigger only over TLS in that case; behind a reverse proxy this depends on correct `reverseProxySSL`/trusted-proxy configuration.

> [!NOTE]
> A profile of a full page-cache hit is nearly empty. This is expected, not a bug; use the [`meta.activationMode`](PROFILE-FORMAT.md) field together with the section keys present to tell that apart from "wrong mode/context". Bypassing the cache would change the very behavior being profiled, so profile a deliberately warmed or cleared cache instead of relying on `no_cache` (discouraged in modern TYPO3 anyway).

## See also

- [Configuration](CONFIGURATION.md): the rest of the profiler's behavior (sampling, retention, tracing, events)
- [Profile format](PROFILE-FORMAT.md): the JSON schema, including the `meta` block these mechanisms populate
