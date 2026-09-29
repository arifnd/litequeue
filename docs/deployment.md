# Deployment

How to run LiteQueue in production with a process manager (Supervisor) or Docker.

The `lq` binary is a long-running process. Run it under a supervisor so it restarts on crash and
shuts down cleanly on deploy.

- `lq work <connection>` — a plain worker (no Horizon visibility).
- `lq supervisor <connection>` — the same worker plus Horizon supervisor state on shared Redis.

## Prerequisites

- PHP `^8.2` with `ext-pcntl` (graceful signals) and `ext-redis` (or `predis/predis`).
- Installed dependencies without dev packages:

  ```bash
  composer install --no-dev --optimize-autoloader --classmap-authoritative
  ```

- Configuration via `config/litequeue.php` and/or environment variables. Standalone runs read
  **process environment variables**, so set them in Supervisor/Docker (a `.env` file is only
  loaded if a Laravel app bootstraps it).

Useful environment variables: `QUEUE_CONNECTION`, `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`,
`REDIS_DB`, `REDIS_PREFIX`, `REDIS_QUEUE`, `REDIS_QUEUE_RETRY_AFTER`, `APP_NAME`, `HORIZON_PREFIX`,
`LITEQUEUE_SUPERVISOR_NAME`. See [Configuration](configuration.md#connecting-to-redis) for details.

## Graceful shutdown

The worker listens for `SIGTERM`, `SIGINT` and `SIGQUIT`: on receipt it finishes the job in
flight, then exits. Always give the process manager enough time to wait for the current job —
set `stopwaitsecs` (Supervisor) or `stop_grace_period` (Docker Compose) to at least your longest
`--timeout`.

---

## Supervisor

Install Supervisor (`apt-get install supervisor`) and add a program file, e.g.
`/etc/supervisor/conf.d/litequeue.conf`.

### Worker pool (recommended)

```ini
[program:litequeue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/app/vendor/bin/lq work redis --queue=default --tries=3 --timeout=90 --sleep=3 --max-time=3600
directory=/var/www/app
autostart=true
autorestart=true
user=www-data
numprocs=4
stopsignal=TERM
stopasgroup=true
killasgroup=true
stopwaitsecs=120
redirect_stderr=true
stdout_logfile=/var/log/litequeue-worker.log
stdout_logfile_maxbytes=10MB
```

- `numprocs=4` scales horizontally; each process is an independent worker.
- `stopsignal=TERM` triggers the graceful path; `stopwaitsecs=120` should exceed `--timeout`.
- `--max-time=3600` restarts workers hourly to shed any memory growth (Laravel's `--max-time`
  equivalent).

### Horizon-visible supervisor

```ini
[program:litequeue-supervisor]
command=php /var/www/app/vendor/bin/lq supervisor redis --queue=default --name=litequeue
directory=/var/www/app
autostart=true
autorestart=true
user=www-data
numprocs=1
stopsignal=TERM
stopasgroup=true
killasgroup=true
stopwaitsecs=120
redirect_stderr=true
stdout_logfile=/var/log/litequeue-supervisor.log
```

Requires a shared Redis **and** the same Horizon prefix as the dashboard instance; see
[supervisor.md](supervisor.md). Each process uses a generated master name, so running several is
supported (the dashboard lists each as a separate master).

### Reload after a deploy

```bash
supervisorctl reread
supervisorctl update
supervisorctl restart litequeue-worker:*      # graceful; waits for stopwaitsecs
# or, for the Horizon-visible supervisor:
supervisorctl restart litequeue-supervisor
```

---

## Docker

### Multi-stage Dockerfile (standalone image)

```dockerfile
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

FROM php:8.3-cli-alpine AS app
RUN apk add --no-cache $PHPIZE_DEPS \
    && docker-php-ext-install pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative

# Default: a plain worker. Override `command` for the Horizon-visible supervisor.
CMD ["php", "vendor/bin/lq", "work", "redis", "--queue=default", "--tries=3", "--timeout=90"]
```

Build and run:

```bash
docker build -t litequeue .
docker run --rm \
  -e REDIS_HOST=redis -e REDIS_PORT=6379 -e REDIS_QUEUE=default \
  litequeue
```

### Docker Compose (Redis + worker)

```yaml
services:
  redis:
    image: redis:7-alpine
    restart: unless-stopped
    command: ["redis-server", "--appendonly", "yes"]
    volumes:
      - redis-data:/data

  worker:
    build: .
    command: ["php", "vendor/bin/lq", "work", "redis", "--queue=default,high", "--tries=3", "--timeout=90"]
    restart: unless-stopped
    stop_signal: SIGTERM
    stop_grace_period: 120s
    depends_on:
      - redis
    environment:
      QUEUE_CONNECTION: redis
      REDIS_HOST: redis
      REDIS_PORT: "6379"
      REDIS_QUEUE: default
    deploy:
      replicas: 4

  # Optional: make this worker visible in a Horizon dashboard (same Redis + prefix).
  supervisor:
    build: .
    command: ["php", "vendor/bin/lq", "supervisor", "redis", "--queue=default", "--name=litequeue"]
    restart: unless-stopped
    stop_signal: SIGTERM
    stop_grace_period: 120s
    depends_on:
      - redis
    environment:
      REDIS_HOST: redis
      HORIZON_PREFIX: laravel_horizon:
    deploy:
      replicas: 1

volumes:
  redis-data:
```

Horizon itself usually runs as a separate service/app (often its own Laravel image) pointed at
the same Redis:

```yaml
  horizon:
    build: ./laravel-app          # a full Laravel app with laravel/horizon installed
    command: ["php", "artisan", "horizon"]
    restart: unless-stopped
    depends_on:
      - redis
    environment:
      REDIS_HOST: redis
      HORIZON_PREFIX: laravel_horizon:
```

### Scaling

- `worker` — scale freely (`docker compose up --scale worker=4`); workers are stateless.
- `supervisor` — scale freely too; each process registers a distinct master name. If you prefer
  one entry, keep replicas at 1.
- Keep `stop_grace_period` / `stopwaitsecs` above your longest job `--timeout` so deploys don't
  kill in-flight jobs.

---

## Deploy checklist

1. `composer install --no-dev --optimize-autoloader --classmap-authoritative`.
2. Publish/ship `config/litequeue.php` and set environment variables.
3. Ensure Redis is reachable and (for Horizon) shares the prefix with the dashboard.
4. Start the worker pool (`lq work ...`) and/or `lq supervisor ...` under Supervisor/Compose.
5. On deploy, restart gracefully (`supervisorctl restart ...` or `docker compose up -d`), giving
   the processes time to finish the current job.
6. Verify: `supervisorctl status` / `docker compose ps`, and for Horizon the Supervisors tab.

## See also

- [worker.md](worker.md) — worker lifecycle, events and signals.
- [supervisor.md](supervisor.md) — Horizon supervisor state emulation.
- [configuration.md](configuration.md) — full config reference.
