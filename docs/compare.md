# Compare

`profiler:compare` answers "did my change make it better or worse?" by diffing two profiles and printing only what changed.

```bash
vendor/bin/typo3 profiler:compare <base> <head>
vendor/bin/typo3 profiler:compare <base> <head> --format=json
```

Each argument is a profile token (the file name under `var/log/profiles/`, without `.json`) or the path of a profile JSON file. An existing file wins over a token. A token can only ever name a file inside the profile directory.

> [!TIP]
> Profiles are pruned after `TYPO3_REQUEST_PROFILER_KEEP` (see [Configuration](configuration.md)). Copy the baseline profile somewhere else and pass its path to keep it for a longer test run. With the HTTP header trigger every response carries `Typo3-Profiler-Artifact: <token>`, see [Activation](activation.md).

## What is compared

| Part | Fields |
|------|--------|
| Aggregates | `timing.total_ms`, `queries.count`, `queries.total_ms`, `memory.peak_mb`, `php.included_files`, `http.count`, `http.total_ms`, `log.count`, `events.count`, `events.total_ms` |
| Top lists | `duplicate_queries` (by `count`) and `slow_queries` (by `ms`), matched by normalized `sql` |

An aggregate is compared when both profiles carry it. The text output lists only metrics whose value changed.

`duplicate_queries` and `slow_queries` are top lists, not complete lists. The diff therefore reports:

- `added`: in the head list, not in the base list. A new N+1 pattern shows up here.
- `left_top_list`: in the base list, not in the head list. This does not mean the query disappeared, only that it is no longer among the top entries.
- `changed`: in both lists with a different `count` or `ms`.

A top list that exists in one profile only is not diffed, it is reported as a warning.

## Warnings

A comparison never fails because of its content. Problems are reported in `warnings`, and `comparable` turns `false` when the numbers cannot be compared meaningfully.

| Warning | `comparable` |
|---------|--------------|
| `cache.hit` differs (a page-cache hit profile is nearly empty by design, see [Activation](activation.md)) | `false` |
| `url` or `page.id` differs | `false` |
| `schemaVersion` differs | `false` |
| A section exists in one profile only (for example `events` was enabled for one run) | `true` |
| `queries` or `http` is `truncated` in one profile | `true` |

## Output

Text:

```
Comparing base-token → head-token
Warning: section 'events' is only present in the head profile
queries.count        101 → 135  +34 (+33.7%)
queries.total_ms     38.2 → 52.9  +14.7 (+38.5%)
timing.total_ms      142.5 → 171  +28.5 (+20%)
duplicate_queries    new: SELECT * FROM sys_category_record_mm WHERE uid_foreign = ? (×24)
slow_queries         left the top list: SELECT * FROM pages WHERE slug = ? (12.4 ms)
```

JSON (`--format=json`):

```json
{
  "schemaVersion": 1,
  "base": "base-token",
  "head": "head-token",
  "comparable": true,
  "warnings": [],
  "metrics": {
    "queries.count": { "base": 101, "head": 135, "delta": 34, "delta_pct": 33.7 },
    "timing.total_ms": { "base": 142.5, "head": 171.0, "delta": 28.5, "delta_pct": 20.0 }
  },
  "duplicate_queries": {
    "added": [{ "sql": "SELECT * FROM sys_category_record_mm WHERE uid_foreign = ?", "count": 24 }],
    "left_top_list": [],
    "changed": [{ "sql": "SELECT COUNT(*) FROM tt_content WHERE pid = ?", "base": 100, "head": 120, "delta": 20 }]
  },
  "slow_queries": {
    "added": [{ "sql": "SELECT * FROM be_users", "ms": 9.0 }],
    "left_top_list": [],
    "changed": []
  }
}
```

| Field | Description |
|-------|-------------|
| `schemaVersion` | Version of this output format (currently `1`). Independent of the profile `schemaVersion`. |
| `base`, `head` | The arguments as given. |
| `comparable` | `false` when a warning says the numbers are not comparable, see above. |
| `warnings` | List of strings. |
| `metrics` | Object keyed by `section.field`, only for aggregates present in both profiles. `delta_pct` is `null` when the base value is `0`. |
| `duplicate_queries`, `slow_queries` | `added` and `left_top_list` entries carry `sql` plus `count` or `ms`. `changed` entries carry `sql`, `base`, `head` and `delta`. |

The same comparison is available in PHP as `KonradMichalik\Typo3RequestProfiler\Profiling\ProfileComparer::compare($base, $head, $baseLabel, $headLabel)`, which takes two decoded profiles and returns this structure as an array.

## Exit codes

| Code | Meaning |
|------|---------|
| `0` | The comparison ran, including `comparable: false`. |
| `1` | An argument is neither a known token nor a readable file with a profile (valid JSON with a `schemaVersion`). |
| `2` | Unknown `--format`. |

## See also

- [Profile format](profile-format.md): the persisted fields this command compares
- [Activation](activation.md): how to get a token per request
- [Configuration](configuration.md): profile retention
