# Configuration

The profiler is controlled entirely via environment variables. For the variables that control *whether* profiling is active at all (`TYPO3_REQUEST_PROFILER`, `_FORCE`, `_SECRET`), see [Activation](ACTIVATION.md) instead. This page covers *how* an already-active profiler behaves.

| Variable | Default | Effect |
|----------|---------|--------|
| `TYPO3_REQUEST_PROFILER_MIN_MS` | `0` | Only persist requests whose total time exceeds this threshold (ms). |
| `TYPO3_REQUEST_PROFILER_KEEP` | `50` | Number of most-recent profiles to retain; older files are pruned automatically. |
| `TYPO3_REQUEST_PROFILER_MAX_AGE_S` | (off) | Also prune profiles older than this many seconds, on top of `..._KEEP`. Disabled by default. |
| `TYPO3_REQUEST_PROFILER_TRACE` | (off) | Set to `1` to capture the calling `Class::method (file:line)` for each query (added as `origin` to `slow_queries`/`duplicate_queries`). |
| `TYPO3_REQUEST_PROFILER_EVENTS` | (off) | Set to `1` to time dispatched PSR-14 events and add an `events` section (count + the most expensive event classes). |

> [!TIP]
> `TYPO3_REQUEST_PROFILER_TRACE=1` uses `debug_backtrace` per query and is therefore opt-in for performance. No bound parameter values are ever captured, only the call site.

> [!TIP]
> `TYPO3_REQUEST_PROFILER_EVENTS=1` wraps the core PSR-14 dispatcher and measures every dispatched event. Dispatch happens very frequently, so the per-event timing is opt-in. When off, events are dispatched without any measurement and the `events` section is omitted. Event timing follows the same activation gate as the rest of the profiler, so it also works on staging together with `TYPO3_REQUEST_PROFILER_FORCE=1` (see [Activation](ACTIVATION.md)).

## See also

- [Activation](ACTIVATION.md): the four ways to turn profiling on
- [Profile format](PROFILE-FORMAT.md): the JSON schema these settings shape
