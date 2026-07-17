# Shared Development Infrastructure

Shared Docker services for local development across multiple projects. **Every service is opt-in** via [Compose profiles](#compose-profiles) (`COMPOSE_PROFILES` in `.env`).

## Compose profiles

Docker Compose starts **only** services whose profile name appears in **`COMPOSE_PROFILES`** (comma-separated, no spaces). Set this in `.env`; Compose loads it automatically.

| Profile | Service | Container name |
|---------|---------|----------------|
| `mysql` | MySQL 8 | `shared-mysql` |
| `pgsql` | PostgreSQL 17 (pgvector) | `shared-pgsql` |
| `redis` | Redis | `shared-redis` |
| `otel-collector` | OpenTelemetry Collector | `otel-collector` |
| `ngrok` | ngrok | `shared-ngrok` |
| `nginx-proxy` | nginx-proxy-manager | `shared-nginx-proxy` |
| `meilisearch` | Meilisearch | `shared-meilisearch` |
| `mailpit` | Mailpit (SMTP + UI) | `shared-mailpit` |
| `selenium` | Selenium (Chromium) | `selenium` (WebDriver at `selenium:4444` on the network; no host port) |
| `rustfs` | RustFS (S3-compatible storage) | `shared-rustfs` |
| `reverb` | Laravel Reverb (shared WebSockets) | `shared-reverb` |
| `confluent` | Kafka / Schema Registry / ksqlDB stack | `zookeeper`, `broker`, etc. |

If **`COMPOSE_PROFILES` is empty or unset, no application containers start.** A typical dev stack:

```env
COMPOSE_PROFILES=mysql,pgsql,redis,otel-collector,ngrok
```

See `.env.example` for all environment variables (ports, credentials, `NGROK_AUTHTOKEN`, RustFS keys, **Reverb** `REVERB_HOST_APP_KEY`, etc.). Reverb also needs **`reverb-host/apps.json`** — see [`reverb-host/README.md`](reverb-host/README.md).

## Available Services (ports on host)

| Service | Version | Port (Host) | Profile |
|---------|---------|---------------|---------|
| MySQL | 8.0 | 3306 | `mysql` |
| PostgreSQL | 17 (pgvector) | 5432 | `pgsql` |
| Redis | Alpine | 6379 | `redis` |
| OpenTelemetry | Latest | 4318 (HTTP OTLP), 4317 (gRPC) | `otel-collector` |
| nginx-proxy-manager | Latest | 80, 443, 81 (admin) | `nginx-proxy` |
| ngrok | Latest | 4040 (dashboard) | `ngrok` |
| Meilisearch | Latest | 7700 | `meilisearch` |
| Mailpit | Latest | 1025 (SMTP), 8025 (dashboard) | `mailpit` |
| Selenium | standalone-chromium | WebDriver `selenium:4444` on `shared-development` only (no host port) | `selenium` |
| RustFS | Latest | 9000 (S3 API), 9001 (console) | `rustfs` |
| Laravel Reverb | 1.x (Laravel 11 host) | `${FORWARD_REVERB_PORT:-8080}` (WebSocket / Pusher protocol) | `reverb` |
| Confluent stack | 7.3.x | 2181, 9092, 8081, 8083, 8088, 9021, … | `confluent` |

Host ports can be overridden per service via `.env` (e.g. `FORWARD_DB_PORT`, `FORWARD_RUSTFS_API_PORT`, `FORWARD_REVERB_PORT`).

## First-time setup

**Prerequisites:** [Docker Desktop](https://www.docker.com/products/docker-desktop/) (or Docker Engine + Compose v2) and Git.

1. **Clone and enter the repo**

   ```bash
   git clone git@github.com:alecgarcia/docker-shared-development.git
   cd docker-shared-development
   ```

2. **Create `.env` from the template**

   ```bash
   cp .env.example .env
   ```

3. **Choose which services to run** — edit **`COMPOSE_PROFILES`** in `.env` (comma-separated, **no spaces**). Every service is opt-in; if this is empty, nothing starts. A common baseline:

   ```env
   COMPOSE_PROFILES=mysql,pgsql,redis,otel-collector
   ```

   Add other profile names from the [Compose profiles](#compose-profiles) table as you need them (`ngrok`, `reverb`, `mailpit`, etc.).

4. **Start the stack** (from the repo root — not from subfolders like `reverb-host/`):

   ```bash
   docker compose up -d
   ```

5. **Confirm containers are up**

   ```bash
   docker compose ps
   ```

   Healthy services show `(healthy)` where a healthcheck is defined.

### Optional: ngrok

Only if you need public HTTPS URLs to services on your Mac (webhooks, mobile testing, etc.).

1. Add **`ngrok`** to **`COMPOSE_PROFILES`**.
2. Set **`NGROK_AUTHTOKEN`** in `.env` ([get a token](https://dashboard.ngrok.com/get-started/your-authtoken)).
3. Copy the tunnel config and edit domains / backend ports:

   ```bash
   cp ngrok.yml.example ngrok.yml
   ```

4. Run `docker compose up -d` again. Inspect tunnels at **http://127.0.0.1:4040**.

See [Customization → ngrok](#customization) and [Laravel Reverb over ngrok](#laravel-reverb-shared-websockets) if you tunnel WebSockets.

### Optional: Laravel Reverb (shared WebSockets)

See [Laravel Reverb (shared WebSockets)](#laravel-reverb-shared-websockets) below — extra steps (`apps.json`, `REVERB_HOST_APP_KEY`, `docker compose build reverb`) are required before Reverb will start.

## Quick Start (day to day)

After [first-time setup](#first-time-setup), from the repo root:

```bash
docker compose up -d

# Stop and remove containers for this project
docker compose down

# Logs (use compose service name: mysql, pgsql, redis, ngrok, …)
docker compose logs -f mysql
```

## Database Connection Patterns

Choose the right connection pattern based on where your code is running:

### Pattern A: Non-Docker Projects (CLI, Local Servers)

**When to use**: Running code directly on your Mac (Laravel via Herd, Node.js locally, Go binaries, etc.)

**Prerequisite**: The matching profile must be enabled (e.g. `mysql` in `COMPOSE_PROFILES`) and the container must be running (`docker compose ps`).

**Connection string**: `127.0.0.1:port`

**Example (.env file)** — use the same usernames and passwords as in your root `.env` / [Default Credentials](#default-credentials):

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USERNAME=root
DB_PASSWORD=password

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### Pattern B: Docker Projects on Shared Network (Recommended)

**When to use**: Docker projects that need to communicate with shared services efficiently.

**Prerequisite**: The shared service’s profile is enabled and its container is running; your app’s compose file attaches to the **`shared-development`** network (see [Connecting your project](#connecting-your-project-to-the-shared-network)).

**Connection string**: `container-name:port` — use the fixed hostnames (e.g. `shared-mysql`, `shared-pgsql`, `shared-redis`). The Docker network name is **`shared-development`**.

**Example (.env file)** — PostgreSQL defaults use password **`secret`** unless you changed **`PSQL_DB_PASSWORD`**:

```env
DB_HOST=shared-mysql
DB_PORT=3306
DB_USERNAME=root
DB_PASSWORD=password

# PostgreSQL (when using pgsql profile)
# DB_HOST=shared-pgsql
# DB_PORT=5432
# DB_USERNAME=root
# DB_PASSWORD=secret

REDIS_HOST=shared-redis
REDIS_PORT=6379
```

### Connecting your project to the shared network

Either include the fragment (Compose v2.24+) so the network is defined once:

```yaml
include:
  - path: /path/to/shared-development/docker-compose.shared-network.yml

services:
  app:
    # ... your service config
    networks:
      - shared-development
```

Or copy the `networks` block from `docker-compose.shared-network.yml` into your compose and add `networks: - shared-development` to the services that need the shared DB, Redis, etc.

### Pattern C: Docker Projects WITHOUT Shared Network

**When to use**: Quick Docker runs without network configuration, or when you can't modify docker-compose

**Connection string**: `host.docker.internal:port`

**Example (.env file)**:
```env
DB_HOST=host.docker.internal
DB_PORT=3306
DB_USERNAME=root
DB_PASSWORD=password

REDIS_HOST=host.docker.internal
REDIS_PORT=6379
```

**Note**: Slightly slower than Pattern B due to extra network hop through host.

## Service Connection Reference

| Service | Pattern A (Host) | Pattern B (Docker + Network) | Pattern C (Docker Only) |
|---------|------------------|------------------------------|-------------------------|
| MySQL | `127.0.0.1:3306` | `shared-mysql:3306` | `host.docker.internal:3306` |
| PostgreSQL | `127.0.0.1:5432` | `shared-pgsql:5432` | `host.docker.internal:5432` |
| Redis | `127.0.0.1:6379` | `shared-redis:6379` | `host.docker.internal:6379` |
| nginx-proxy (HTTP) | `127.0.0.1:80` | N/A | N/A |
| nginx-proxy (HTTPS) | `127.0.0.1:443` | N/A | N/A |
| nginx-proxy (Admin) | `127.0.0.1:81` | N/A | N/A |
| RustFS (S3 API) | `127.0.0.1:9000` | `shared-rustfs:9000` | `host.docker.internal:9000` |
| Laravel Reverb (WebSocket) | `127.0.0.1:${FORWARD_REVERB_PORT:-8080}` | `shared-reverb:8080` | `host.docker.internal:${FORWARD_REVERB_PORT:-8080}` |
| Meilisearch | `http://127.0.0.1:${FORWARD_MEILISEARCH_PORT:-7700}` | `http://shared-meilisearch:7700` | `http://host.docker.internal:${FORWARD_MEILISEARCH_PORT:-7700}` |
| Mailpit (SMTP) | `127.0.0.1:${FORWARD_MAILPIT_PORT:-1025}` | `shared-mailpit:1025` | `host.docker.internal:${FORWARD_MAILPIT_PORT:-1025}` |
| Mailpit (web UI) | `http://127.0.0.1:${FORWARD_MAILPIT_DASHBOARD_PORT:-8025}` | `http://shared-mailpit:8025` | `http://host.docker.internal:${FORWARD_MAILPIT_DASHBOARD_PORT:-8025}` |
| OpenTelemetry (OTLP HTTP) | `http://127.0.0.1:4318` | `http://otel-collector:4318` | `http://host.docker.internal:4318` |
| Selenium WebDriver | *(no host port)* | `http://selenium:4444` | *Use Pattern B* (no host port published by default) |

Use the port variables from `.env` when you override defaults. Reverb’s internal container port is always **8080**; the host uses **`FORWARD_REVERB_PORT`**.

## Domain Routing with nginx-proxy-manager

### nginx-proxy-manager **or** Laravel Herd (not both)

nginx-proxy-manager and Laravel Herd **both use ports 80 and 443**. Run one or the other:

- **Use Laravel Herd**: Keep Herd running for Laravel projects; do **not** add `nginx-proxy` to `COMPOSE_PROFILES` in `.env` (so nginx-proxy is not started).
- **Use nginx-proxy-manager**: Add `nginx-proxy` to `COMPOSE_PROFILES` in `.env`, stop Herd (`herd stop`), then `docker compose up -d`. nginx-proxy will bind to 80 (HTTP), 443 (HTTPS), and 81 (Admin).

### Accessing Projects

#### With Laravel Herd (default)
- **Herd-managed Laravel projects**: `project.test` (port 80/443)

#### With nginx-proxy (Herd stopped)
- **Admin panel**: http://127.0.0.1:81 — configure proxy hosts here.
- **Sites**: `http://project.test` (port 80) and `https://project.test` (port 443) once proxy hosts are set up.

### Setting Up nginx-proxy-manager

1. **Access admin panel**: http://127.0.0.1:81
   - Default credentials (first login):
     - Email: `admin@example.com`
     - Password: `changeme`

2. **Add a Proxy Host**:
   - **Domain Names**: `coppercustom.test`
   - **Scheme**: `http://`
   - **Forward Hostname/IP**: `coppercustom_web` (container name)
   - **Forward Port**: `80` (container's internal port)
   - **Cache Assets**: ✓ (optional)
   - **Block Common Exploits**: ✓ (recommended)
   - **Websockets Support**: ✓ (if needed)

3. **Access your site**: `http://coppercustom.test` (port 80) or `https://coppercustom.test` (port 443)

### Example Project Setup

**For `coppercustom` OpenCart Site:**

1. Ensure container is on shared network (already configured)
2. Add proxy host in nginx-proxy admin (Forward: `coppercustom_web:80`)
3. Access via `http://coppercustom.test` or `https://coppercustom.test`

## Default Credentials

### MySQL
```
Username: root
Password: password
Database: shared-database
```

### PostgreSQL
```
Username: root
Password: secret (default; set via PSQL_DB_PASSWORD in .env)
```

### Redis
```
No password (default)
```

## Customization

Copy `.env.example` to `.env`, then edit `.env` to customize:

- **`COMPOSE_PROFILES`** (required) — Comma-separated list of services to run (no spaces). **Every** service is opt-in, including databases. Profile names match the service: `mysql`, `pgsql`, `redis`, `otel-collector`, `ngrok`, `nginx-proxy`, `meilisearch`, `mailpit`, `selenium`, `rustfs`, `reverb`, `confluent`. Example typical stack: `COMPOSE_PROFILES=mysql,pgsql,redis,otel-collector,ngrok`. If **`COMPOSE_PROFILES` is empty or unset, no containers start.** After changing profiles, run `docker compose up -d`. If you upgrade from an older `.env` that only listed optional profiles, add `mysql,pgsql,redis,otel-collector` (and any others you need).
- Port mappings (`FORWARD_DB_PORT`, `PSQL_DB_PORT`, `FORWARD_REDIS_PORT`, etc.)
- Database credentials (MySQL, PostgreSQL)
- **ngrok**: Set `NGROK_AUTHTOKEN` in `.env` and include profile `ngrok` in `COMPOSE_PROFILES`. Copy `ngrok.yml.example` to `ngrok.yml` (gitignored) and define your tunnels there. For **Laravel Reverb**, use an `http` tunnel to `http://host.docker.internal:${FORWARD_REVERB_PORT:-8080}` (see `ngrok.yml.example` → `shared-reverb` and [`reverb-host/README.md`](reverb-host/README.md#ngrok-public-websocket-url)).
- **Laravel Reverb** (profile `reverb`): Set `REVERB_HOST_APP_KEY` in root `.env` (generate with `cd reverb-host && php artisan key:generate --show`). Copy **`reverb-host/apps.json.example`** to **`reverb-host/apps.json`** and add one entry per Laravel project (`app_id`, `key`, `secret`). First time: **`docker compose build reverb`**, then `docker compose up -d`. See [Laravel Reverb (shared WebSockets)](#laravel-reverb-shared-websockets) and [`reverb-host/README.md`](reverb-host/README.md).

### RustFS, Mailpit, and Meilisearch

Enable the profiles and run `docker compose up -d`:

```env
COMPOSE_PROFILES=mysql,pgsql,redis,otel-collector,meilisearch,mailpit,rustfs
```

(Adjust the list; add `ngrok` or others if you need them.)

| Service | Profile | From your machine (Pattern A) | On `shared-development` (Pattern B) |
|---------|---------|--------------------------------|----------------------------------------|
| **Meilisearch** | `meilisearch` | Base URL `http://127.0.0.1:7700` (or the port from `FORWARD_MEILISEARCH_PORT`). Configure Scout / client to that host | Host `shared-meilisearch`, port `7700` |
| **Mailpit** | `mailpit` | SMTP `127.0.0.1:${FORWARD_MAILPIT_PORT:-1025}`; web UI `http://127.0.0.1:${FORWARD_MAILPIT_DASHBOARD_PORT:-8025}`. Optional UI auth: `MP_UI_AUTH` in `.env` | SMTP `shared-mailpit:1025`; same ports inside the network |
| **RustFS** | `rustfs` | S3 API `http://127.0.0.1:${FORWARD_RUSTFS_API_PORT:-9000}`; console `http://127.0.0.1:${FORWARD_RUSTFS_CONSOLE_PORT:-9001}`. Keys: `RUSTFS_ACCESS_KEY` / `RUSTFS_SECRET_KEY` | Endpoint `http://shared-rustfs:9000` (use the same keys as MinIO-style access/secret) |

- **Laravel**: Meilisearch — `MEILISEARCH_HOST=http://127.0.0.1:7700` and `MEILISEARCH_KEY=` (include the port; from containers use `http://shared-meilisearch:7700`). Mail — `MAIL_MAILER=smtp`, `MAIL_HOST=127.0.0.1`, `MAIL_PORT` = `FORWARD_MAILPIT_PORT`. Filesystems using S3 — `AWS_ENDPOINT=http://127.0.0.1:9000`, `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` = RustFS keys, `AWS_USE_PATH_STYLE_ENDPOINT=true`, `AWS_URL` / bucket name as usual. Traces — point your OTLP exporter at `http://127.0.0.1:4318` (host) or `http://otel-collector:4318` (containers); see [`otel-collector/README.md`](otel-collector/README.md).
- More detail: [`rustfs/README.md`](rustfs/README.md).

### Laravel Reverb (shared WebSockets)

One **shared-reverb** container serves WebSockets for **multiple** Laravel apps. Each app has its own credentials in **`reverb-host/apps.json`** (not committed).

**Enable the stack**

1. Add **`reverb`** to **`COMPOSE_PROFILES`** (add **`redis`** too if you enable horizontal scaling).
2. Copy and edit app credentials:

   ```bash
   cp reverb-host/apps.json.example reverb-host/apps.json
   ```

3. Generate the host app key and set it in **root** `.env`:

   ```bash
   cd reverb-host && php artisan key:generate --show
   # → paste as REVERB_HOST_APP_KEY=base64:... in ../.env
   ```

4. Build and start (from repo root):

   ```bash
   docker compose build reverb
   docker compose up -d
   ```

5. Verify: `docker compose ps reverb` shows **healthy**; `curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8080/` should return **404** (normal for Reverb).

**Wire a Laravel consumer app** — use the same `app_id`, `key`, and `secret` as one row in `apps.json`. Example for local Vite/Echo:

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=app-one
REVERB_APP_KEY=change-me-app-one-key
REVERB_APP_SECRET=change-me-app-one-secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

**Public WebSockets (ngrok)** — add profile **`ngrok`**, configure an `http` tunnel to `http://host.docker.internal:${FORWARD_REVERB_PORT:-8080}` in `ngrok.yml` (see `ngrok.yml.example` → `shared-reverb`), then update `apps.json` **`options`** and consumer **`VITE_REVERB_*`** for `https` / port **443**. Full steps: [`reverb-host/README.md`](reverb-host/README.md).

**Publishing from Docker** — apps on `shared-development` can reach **`http://shared-reverb:8080`**; browsers still use `127.0.0.1` or ngrok unless you configure otherwise.

## Troubleshooting

### Reverb container exits or compose fails on `REVERB_HOST_APP_KEY`

**Issue**: `docker compose up` errors about **`REVERB_HOST_APP_KEY`**, or **`shared-reverb`** keeps restarting.

**Solution**:
1. Set **`REVERB_HOST_APP_KEY`** in root `.env` (`cd reverb-host && php artisan key:generate --show`).
2. Ensure **`reverb-host/apps.json`** exists (`cp reverb-host/apps.json.example reverb-host/apps.json`).
3. Run **`docker compose build reverb`** from the **repo root**, then **`docker compose up -d reverb`**.

### Container can't connect to database

**Issue**: Getting "connection refused" errors

**Solution**:
1. Confirm the database profile is in **`COMPOSE_PROFILES`** (e.g. `mysql` or `pgsql`) and the container is up: `docker compose ps`.
2. Pick the right connection pattern:
   - Code on host → Pattern A (`127.0.0.1`)
   - App in Docker on `shared-development` → Pattern B (e.g. `shared-mysql`)
   - App in Docker without the shared network → Pattern C (`host.docker.internal`)

### Port conflicts with Laravel Herd

**Issue**: nginx-proxy won't start when Herd is running (or vice versa)

**Solution**: nginx-proxy and Herd both use ports 80 and 443. Use one at a time:
- **To use nginx-proxy**: Stop Herd (`herd stop`), then start nginx-proxy.
- **To use Herd**: Remove `nginx-proxy` from `COMPOSE_PROFILES` and run `docker compose up -d`, or run `docker compose stop nginx-proxy`, then start Herd.

### Container not on shared-development network

**Issue**: Docker project can't reach shared services using container names

**Solution**: Attach your project to the shared network. Either include `docker-compose.shared-network.yml` (see Pattern B above) or add to your project's `docker-compose.yml`:
```yaml
networks:
  shared-development:
    external: true
```

Then add to your service:
```yaml
services:
  your-service:
    networks:
      - shared-development
```

Restart your containers: `docker compose down && docker compose up -d`

### Check network connectivity

```bash
# List all containers on the shared network
docker network inspect shared-development

# Test connection from a container
docker exec -it your-container-name ping shared-mysql
```

## Service Management

### Enable or disable services

Edit **`COMPOSE_PROFILES`** in `.env`, then:

```bash
docker compose up -d
```

You can also pass profiles once on the CLI (overrides `.env` for that command):

```bash
COMPOSE_PROFILES=mysql,pgsql,redis docker compose up -d
```

### Start or recreate specific services

After profiles are set (in `.env` or env), you can target services by name:

```bash
docker compose up -d mysql redis
```

### View service status

```bash
docker compose ps
```

## Network Architecture

Services that define `networks: - shared-development` join the **`shared-development`** bridge network (fixed name in the root `docker-compose.yml`). Other projects use `docker-compose.shared-network.yml` (or an equivalent `external: true` block) to attach to the same network.

**Fixed DNS names** (when the corresponding profile is running):

- `shared-mysql`, `shared-pgsql`, `shared-redis`, `shared-ngrok`, `shared-nginx-proxy`, `shared-meilisearch`, `shared-mailpit`, `shared-rustfs`, `shared-reverb`, `otel-collector`, `selenium` (Compose service name; WebDriver port **4444**)

The Confluent stack uses its own container names (`broker`, `zookeeper`, …) on the same network when profile `confluent` is enabled.

Published ports are bound to **`127.0.0.1`** unless noted otherwise, so services are not exposed on your LAN by default.

## Files in this repo

| File | Purpose |
|------|---------|
| `docker-compose.yml` | Root compose: `include`s all service fragments + `shared-development` network |
| `docker-compose.shared-network.yml` | Fragment for **other** projects: declares `shared-development` as `external: true` |
| `.env.example` | Template for `.env` (`COMPOSE_PROFILES`, DB/redis ports, ngrok, RustFS, Reverb, …) |
| `ngrok.yml.example` | Template for `ngrok.yml` (copy to `ngrok.yml`; gitignored) |
| `reverb-host/README.md` | Shared Laravel Reverb: `apps.json`, consumer `.env`, ngrok, scaling |
| `rustfs/README.md` | RustFS-specific notes (S3 client, credentials) |
| `otel-collector/README.md` | OTLP endpoints and local tracing |
