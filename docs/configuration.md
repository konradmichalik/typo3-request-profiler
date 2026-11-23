# Configuration

The profiler is controlled entirely via environment variables. For the variables that control *whether* profiling is active at all (`TYPO3_REQUEST_PROFILER`, `_FORCE`, `_SECRET`), see [Activation](activation.md) instead. This page covers *how* an already-active profiler behaves.

| Variable | Default | Effect |
|----------|---------|--------|
| `TYPO3_REQUEST_PROFILER_MIN_MS` | `0` | Only persist requests whose total time exceeds this threshold (ms). |
| `TYPO3_REQUEST_PROFILER_KEEP` | `50` | Number of most-recent profiles to retain; older files are pruned automatically. |
| `TYPO3_REQUEST_PROFILER_MAX_AGE_S` | (off) | Also prune profiles older than this many seconds, on top of `..._KEEP`. Disabled by default. |
| `TYPO3_REQUEST_PROFILER_TRACE` | (off) | Set to `1` to capture the calling `Class::method (file:line)` for each query (added as `origin` to `slow_queries`/`duplicate_queries`). |
| `TYPO3_REQUEST_PROFILER_EVENTS` | (off) | Set to `1` to time dispatched PSR-14 events and add an `events` section (count + the most expensive event classes). |
| `TYPO3_REQUEST_PROFILER_BUDGETS` | (unset) | Path to the JSON budget file read by `profiler:check`, absolute or relative to the TYPO3 project path. Unset means no budgets, see [Budgets](budgets.md). |

> [!TIP]
> `TYPO3_REQUEST_PROFILER_TRACE=1` uses `debug_backtrace` per query and is therefore opt-in for performance. No bound parameter values are ever captured, only the call site.

> [!TIP]
> `TYPO3_REQUEST_PROFILER_EVENTS=1` wraps the core PSR-14 dispatcher and measures every dispatched event. Dispatch happens very frequently, so the per-event timing is opt-in. When off, events are dispatched without any measurement and the `events` section is omitted. The flag is the only gate for the timing itself, so it also works outside Development with the temporary toggle, the header trigger or `TYPO3_REQUEST_PROFILER_FORCE=1` (see [Activation](activation.md)). Activation is decided per request in the middleware, after the first events are dispatched, so on an instance where the flag stays set every request pays for the timing, but only activated requests write a profile.

## See also

- [Activation](activation.md): the four ways to turn profiling on
- [Profile format](profile-format.md): the JSON schema these settings shape
- [Budgets](budgets.md): `profiler:check` and the budget file
