# Worker

The worker pops jobs, executes them, and handles retries, releases and failures.

## Running

```bash
lq work redis                 # daemon
lq work redis --once          # process a single job
lq work redis --queue=high,default --tries=3 --timeout=60
lq work redis --stop-when-empty --max-jobs=100 --max-time=3600
```

Options: `--queue`, `--name`, `--once`, `--stop-when-empty`, `--delay`, `--backoff`,
`--max-jobs`, `--max-time`, `--force`, `--memory`, `--sleep`, `--tries`, `--timeout`, `--rest`.

## Lifecycle

1. `JobPopping` / `JobPopped`
2. `JobProcessing`
3. `JobProcessed` on success, or `JobExceptionOccurred` on error
4. `JobReleasedAfterException` when retried, `JobFailed` when exhausted
5. `JobAttempted` after every attempt
6. `WorkerStopping` on shutdown

## Signals

When `ext-pcntl` is available, `SIGTERM` / `SIGINT` / `SIGQUIT` stop the worker gracefully after
the current job; `SIGUSR2` pauses it.

## Restart

Set the `illuminate:queue:restart` cache flag (e.g. `lq restart`) to make long-running workers
exit between jobs.
