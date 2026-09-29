# AGENTS.md

Guidance for coding agents working in this repository.

## Project overview

Dev-only TYPO3 frontend request profiler (`konradmichalik/typo3-request-profiler`). It instruments live frontend requests and writes one compact JSON profile per request (SQL queries, N+1 patterns, cache state, timing) to `var/log/profiles/{request_id}.json`. Active by default only in the Development context, see `docs/activation.md`.

- Extension key: `typo3_request_profiler`
- Namespace: `KonradMichalik\Typo3RequestProfiler\`
- PHP: `~8.2 || ~8.3 || ~8.4 || ~8.5`
- TYPO3: `^13.4 || ^14.0`

## Structure

- `Classes/Activation/`: activation mode and state handling
- `Classes/Command/`: activate and deactivate console commands
- `Classes/Middleware/PerformanceProfilerMiddleware.php`: frontend middleware entry point
- `Classes/Profiling/`: profile reader and writer, URL sanitizing
- `Classes/Profiling/Collector/`, `Instrumentation/` (Doctrine, Http, Log), `Section/`: data collection and profile sections
- `Classes/Configuration.php`: extension configuration
- `Configuration/`: TYPO3 configuration
- `docs/`: `activation.md`, `configuration.md`, `profile-format.md`
- `Tests/Unit/`, `Tests/Functional/`: PHPUnit tests
- `Tests/Acceptance/Fixtures/`: fixtures, including a sitepackage with a deliberate N+1 demo page
- `Tests/CGL/`: isolated Composer project with the code style and analysis tooling

## Development commands

```bash
composer install

composer cgl install           # install the CGL tooling
composer cgl lint              # PHP CS Fixer, composer-normalize, EditorConfig
composer cgl fix               # auto-fix
composer cgl sca               # PHPStan
composer cgl migration         # Rector
```

Multi-version TYPO3 setup (13 and 14) runs in [DDEV](https://ddev.readthedocs.io/en/stable/) via the `konradmichalik/ddev-typo3-multi-version-extension` add-on, see `CONTRIBUTING.md`.

## Testing

```bash
composer test                  # unit and functional
composer test:unit             # PHPUnit, no coverage
ddev exec "composer test:functional"   # functional tests need a database
composer test:coverage         # merged unit and functional coverage (Xdebug)
```

CI runs PHPUnit through a reusable workflow on PHP 8.2 to 8.5, TYPO3 13.4 and 14.3, with highest and lowest dependencies. It also runs CGL, a security workflow and OpenSSF Scorecard.

## Code style and static analysis

- PHP CS Fixer with `konradmichalik/php-cs-fixer-preset`
- PHPStan with a baseline in `Tests/CGL/phpstan-baseline.neon`
- Rector, composer-dependency-analyser, composer-normalize, EditorConfig
- Add tests for every change and keep the suite green

## Git workflow

- Branch from `main`, open a pull request
- Commit format: `<type>: <description>` with type one of feat, fix, refactor, docs, test, chore, perf, ci
- No co-author trailers
