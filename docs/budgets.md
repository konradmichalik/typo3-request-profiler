# Budgets

`profiler:check` answers "is this page still cheap enough?" by checking profiles against declarative budgets, and exits non-zero on a violation. Run it after an E2E suite and a new N+1 pattern fails the pipeline the day it is introduced.

```bash
vendor/bin/typo3 profiler:check <token>
vendor/bin/typo3 profiler:check <token1> <token2> <token3>
vendor/bin/typo3 profiler:check <token> --format=json
```

Each argument is a profile token (the file name under `var/log/profiles/`, without `.json`) or the path of a profile JSON file, resolved like in [Compare](compare.md). An existing file wins over a token.

## Budget file

The budget file is JSON. Point `TYPO3_REQUEST_PROFILER_BUDGETS` at it (see [Configuration](configuration.md)): an absolute path, or a path relative to the TYPO3 project path.

```json
{
  "defaults": {
    "queries.count": 100,
    "duplicate_queries.max_count": 5,
    "http.count": 3,
    "timing.total_ms": 300
  },
  "pages": [
    { "match": { "path": "/news" }, "budgets": { "queries.count": 140 } },
    { "match": { "page_id": 1 }, "budgets": { "timing.total_ms": 200 } }
  ]
}
```

A value passes when it does not exceed its limit. Limits are non-negative numbers.

| Budget key | Persisted field | Notes |
|------------|-----------------|-------|
| `timing.total_ms` | `timing.total_ms` | |
| `queries.count` | `queries.count` | |
| `queries.total_ms` | `queries.total_ms` | |
| `duplicate_queries.max_count` | highest `count` in `duplicate_queries` | `0` when there are none. A violation prints the offending SQL. |
| `http.count` | `http.count` | `0` when the request made no outgoing calls. |
| `http.total_ms` | `http.total_ms` | |
| `memory.peak_mb` | `memory.peak_mb` | |
| `memory.peak_pct` | `memory.peak_pct` | Absent when `memory_limit` is unlimited. |
| `log.by_level.error` | `log.by_level.error` | `0` when nothing was logged at `error`. Other levels are not budgeted. |

A key that the profile cannot measure (`timing`, `memory.peak_mb` or `memory.peak_pct` missing) is reported as `n/a` and never counts as met.

### Matching pages

`defaults` apply to every profile. Each entry in `pages` has a `match` and its own `budgets`, which override the defaults for that key.

- `path` is the URL path of the profile, compared exactly (`/news` does not match `/news/`). Query values are masked in the artifact anyway, so the query string is not part of the match.
- `page_id` is `page.id` of the profile. It is only as stable across environments as the database is.
- A `match` with both `path` and `page_id` needs both to fit.
- Several entries can match one profile. They apply in file order, so a later entry wins.

### Invalid files

A typo must never silently disable a budget. An unknown budget key, an unknown key at the top level or in a `match`, a limit that is not a non-negative number or an unreadable file ends the run with exit code `2`. The message for an unknown budget key lists the valid keys.

## Output

Text:

```
✗ /news (token 6f1c8a2e…)
    queries.count                  135 ≤ 140      ok
    duplicate_queries.max_count    24 > 5         FAIL  SELECT * FROM sys_category_record_mm WHERE uid_foreign = ?
✓ / (token 9a2e1b7c…)   all budgets met
- /about (token 3d94f0aa…) skipped
  Notice: page cache hit, not checked: a cached response is nearly empty by design
1 of 3 profiles over budget, 1 skipped
```

JSON (`--format=json`):

```json
{
  "schemaVersion": 1,
  "summary": { "profiles": 2, "within_budget": 1, "over_budget": 1, "skipped": 0 },
  "profiles": [
    {
      "reference": "6f1c8a2e",
      "token": "6f1c8a2e",
      "path": "/news",
      "status": "violation",
      "notices": [],
      "checks": [
        { "key": "queries.count", "actual": 135, "limit": 140, "status": "ok" },
        { "key": "duplicate_queries.max_count", "actual": 24, "limit": 5, "status": "fail", "sql": "SELECT * FROM sys_category_record_mm WHERE uid_foreign = ?" }
      ]
    }
  ]
}
```

| Field | Description |
|-------|-------------|
| `schemaVersion` | Version of this output format (currently `1`). Independent of the profile `schemaVersion`. |
| `summary` | Counts of checked profiles by outcome. |
| `profiles[].reference` | The argument as given. |
| `profiles[].status` | `ok`, `violation` or `skipped`. |
| `profiles[].notices` | List of strings, see below. |
| `profiles[].checks[]` | One entry per applicable budget. `status` is `ok`, `fail` or `n/a` (`actual` is `null`). `sql` only on a failed `duplicate_queries.max_count`. |

The same evaluation is available in PHP as `KonradMichalik\Typo3RequestProfiler\Profiling\Budget\BudgetEvaluator::report($profiles, $budgets)`, with `BudgetLoader::load($file)` for the budget file.

## Skipped profiles and notices

- **Page cache hits are skipped, never passed.** A cached response is nearly empty and would pass any budget, which would hide real problems. Check requests that render the page, for example the first request after a cache flush. A skipped profile does not change the exit code.
- **Truncated queries** (`queries.truncated`, see [Profile format](profile-format.md)) add a notice but are still evaluated. `queries.count` and `queries.total_ms` stay exact, only `duplicate_queries.max_count` covers the first recorded queries and can be too low.
- A profile for which no budget applies is reported as `no budgets apply` and counts as within budget.

## Exit codes

| Code | Meaning |
|------|---------|
| `0` | Everything is within budget, or only skipped. Also when no budgets are configured: the command prints `No budgets configured` and does not look at the profiles. |
| `1` | At least one profile is over budget. |
| `2` | A token or file could not be resolved, the budget file is invalid or unreadable, or the `--format` is unknown. |

> [!TIP]
> Gate on count budgets (`queries.count`, `duplicate_queries.max_count`, `http.count`, `log.by_level.error`). They are deterministic for a page and a data set. Millisecond budgets (`*_ms`) depend on the machine and fluctuate in CI, so use generous limits or leave them out of a gating run.

## Example: Playwright

With the HTTP header trigger every response carries `Typo3-Profiler-Artifact: <token>`, see [Activation](activation.md). Collect the tokens during the run and check them at the end. The command exits non-zero on a violation, which fails the run.

```ts
import { test } from '@playwright/test';
import { execFileSync } from 'node:child_process';

const tokens = new Set<string>();

test.beforeEach(async ({ page }) => {
  await page.setExtraHTTPHeaders({ 'Typo3-Profiler': '1' });
  page.on('response', (response) => {
    const token = response.headers()['typo3-profiler-artifact'];
    if (token) {
      tokens.add(token);
    }
  });
});

test.afterAll(() => {
  if (tokens.size > 0) {
    execFileSync('ddev', ['exec', 'vendor/bin/typo3', 'profiler:check', ...tokens], { stdio: 'inherit' });
  }
});
```

Make the budget file known to the container, for example in `.ddev/config.yaml`:

```yaml
web_environment:
  - TYPO3_REQUEST_PROFILER_BUDGETS=config/profiler-budgets.json
```

> [!NOTE]
> Profiles are pruned after `TYPO3_REQUEST_PROFILER_KEEP` (see [Configuration](configuration.md)). Raise it when a run produces more profiles than that, or tokens at the start of the run are gone when the check runs.

## See also

- [Profile format](profile-format.md): the persisted fields the budgets read
- [Activation](activation.md): the header trigger that returns a token per request
- [Compare](compare.md): diff two profiles instead of checking absolute limits
- [Configuration](configuration.md): the `TYPO3_REQUEST_PROFILER_BUDGETS` variable
