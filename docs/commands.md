# Commands

All commands run through the standalone `lq` binary (no `php artisan`).

| Command | Description |
| --- | --- |
| `lq make:job {name}` | Create a job class (`--sync`, `--once`, `--test`, `--pest`, `--force`, `--path`) |
| `lq work {connection?}` | Process jobs (`--once`, `--queue`, `--tries`, `--timeout`, `--max-jobs`, …) |
| `lq supervisor {connection?}` | Run as a Horizon-compatible supervisor (`--dry-run`, `--max-jobs`, …) |
| `lq install` | Publish `config/litequeue.php` (`--force`) |
| `lq failed` | List failed jobs |
| `lq retry {id\|all}` | Retry a failed job |
| `lq forget {id}` | Remove a failed job |
| `lq flush` | Flush failed jobs (`--hours`) |

## Examples

```bash
lq make:job ProcessPodcast --once --test
lq work redis --queue=high,default --tries=3
lq supervisor redis --queue=default
lq failed
lq retry all
```
