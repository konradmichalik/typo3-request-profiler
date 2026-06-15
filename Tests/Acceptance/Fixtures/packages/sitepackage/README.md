# Sitepackage (N+1 demo)

Demo sitepackage used to exercise `typo3-request-profiler` in the multi-version
DDEV test environment.

The frontend page is rendered through a `USER_INT` object
(`NplusOneDemoRenderer`) that runs one `COUNT` query **per page** — a classic
N+1 pattern. Because the object is `USER_INT`, the page is uncached and the
profiler records the repeated query on every request.

Open any frontend page in a Development context, then inspect the generated
profile under `var/log/profiles/{request_id}.json`: the per-page query should
appear in `duplicate_queries` with `count > 1`, and `cache.cacheable` should be
`false`.
