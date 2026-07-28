<div align="center">

![Extension icon](Resources/Public/Icons/Extension.png)

# TYPO3 extension `typo3_request_profiler`

![TYPO3](https://img.shields.io/badge/TYPO3-13.4%20%7C%2014.3-orange.svg)
[![Supported PHP Versions](https://img.shields.io/packagist/dependency-v/konradmichalik/typo3-request-profiler/php?logo=php)](https://packagist.org/packages/konradmichalik/typo3-request-profiler)
[![CGL](https://img.shields.io/github/actions/workflow/status/konradmichalik/typo3-request-profiler/cgl.yml?label=cgl&logo=github)](https://github.com/konradmichalik/typo3-request-profiler/actions/workflows/cgl.yml)
[![Coverage](https://img.shields.io/coverallsCoverage/github/konradmichalik/typo3-request-profiler?logo=coveralls)](https://coveralls.io/github/konradmichalik/typo3-request-profiler)
[![Tests](https://img.shields.io/github/actions/workflow/status/konradmichalik/typo3-request-profiler/tests.yml?label=tests&logo=github)](https://github.com/konradmichalik/typo3-request-profiler/actions/workflows/tests.yml)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](LICENSE.md)

</div>

A _dev-only_ TYPO3 frontend request profiler. It instruments live frontend requests and writes one compact JSON profile per request (SQL queries, N+1 patterns, cache state, and timing) to `var/log/profiles/{request_id}.json`.

> [!IMPORTANT]
> This extension is **active by default only in a Development context** (`Environment::getContext()->isDevelopment()`). Outside Development it stays off and collects no data unless explicitly opted in; see [Activation](docs/ACTIVATION.md).

The profiler is a thin, standalone collector with no external dependencies. It is inspired by the [Symfony Profiler](https://symfony.com/doc/current/profiler.html) and by some of the metrics the [TYPO3 Admin Panel](https://docs.typo3.org/c/typo3/cms-adminpanel/main/en-us/) surfaces, but records them as compact, machine-readable JSON instead of an interactive panel.

**What it captures per request:**

- Wall-clock and SQL timing, peak memory usage, included PHP file count
- Full query count + top slow queries + N+1 duplicate detection
- Cache hit/miss state with disabled reasons
- Log activity per request (count by level + noisiest components)
- Optional call-site origin (`Class::method (file:line)`) for every flagged query
- Optional PSR-14 event timing (count + the most expensive event classes)

## 🔥 Installation

### Requirements

* TYPO3 13.4 LTS & 14.0+
* PHP 8.2+
* Doctrine DBAL 3.x or 4.x

### Composer

[![Packagist](https://img.shields.io/packagist/v/konradmichalik/typo3-request-profiler?label=version&logo=packagist)](https://packagist.org/packages/konradmichalik/typo3-request-profiler)
[![Packagist Downloads](https://img.shields.io/packagist/dt/konradmichalik/typo3-request-profiler?color=brightgreen)](https://packagist.org/packages/konradmichalik/typo3-request-profiler)

```bash
composer require --dev konradmichalik/typo3-request-profiler
```

### TER

[![TER version](https://typo3-badges.dev/badge/typo3_request_profiler/version/shields.svg)](https://extensions.typo3.org/extension/typo3_request_profiler)
[![TER downloads](https://typo3-badges.dev/badge/typo3_request_profiler/downloads/shields.svg)](https://extensions.typo3.org/extension/typo3_request_profiler)

Download the zip file from [TYPO3 extension repository (TER)](https://extensions.typo3.org/extension/typo3_request_profiler).

## 💡 Example

Start it up in a `Development` context (no configuration needed) and every request writes a compact JSON profile to `var/log/profiles/{request_id}.json`:

```json
{
  "schemaVersion": 1,
  "token": "<RequestId>",
  "url": "https://example.ddev.site/",
  "status": 200,
  "meta": { "activationMode": "context" },
  "timing": { "total_ms": 142.5 },
  "queries": { "count": 101, "total_ms": 38.2 },
  "slow_queries": [
    { "sql": "SELECT * FROM pages WHERE slug = ? ORDER BY slug desc", "ms": 12.4 }
  ]
}
```

That's the gist. The full artifact also carries cache state, memory usage, PHP include count, N+1 duplicate-query detection, log activity, and optional PSR-14 event timing. See [Profile Format](docs/PROFILE-FORMAT.md) for the complete schema.

## 📚 Documentation

| Topic | What's inside |
|-------|---------------|
| [Activation](docs/ACTIVATION.md) | Development context, forcing it elsewhere via an env var, the `profiler:activate` CLI toggle, and the HTTP header trigger for per-request correlation |
| [Configuration](docs/CONFIGURATION.md) | Sampling threshold, retention, query tracing, and PSR-14 event timing |
| [Profile Format](docs/PROFILE-FORMAT.md) | The full JSON schema, provenance metadata, and the `ProfileReader` read API |

## 🧑‍💻 Contributing

Please have a look at [`CONTRIBUTING.md`](CONTRIBUTING.md).

## ⭐ License

This project is licensed under [GNU General Public License 2.0 (or later)](LICENSE.md).
