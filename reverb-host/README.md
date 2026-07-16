# Shared Laravel Reverb host

Minimal Laravel application that runs **[Laravel Reverb](https://laravel.com/docs/reverb)** for **all** your local Laravel projects. Each project keeps its own `app_id`, `key`, and `secret`; this container loads them from **`apps.json`**.

**Where to run Docker Compose:** from the **repo root**, e.g. `docker compose build reverb`, so Compose loads root `.env` (`REVERB_HOST_APP_KEY`) and the merged stack (including the `shared-development` network). If your cwd is `reverb-host/`, run `docker compose -f ../docker-compose.yml build reverb` instead — same stack, same `.env` resolution. Using only `compose.yml` in this directory is invalid (external network is defined at the root).

## Quick setup

1. **Copy credentials file**

   ```bash
   cp apps.json.example apps.json
   ```

   Edit `apps.json`: one object per Laravel app. Each must have unique `app_id`, `key`, and `secret`. Tighten `allowed_origins` beyond `*` when you can (browser security).

2. **Set `REVERB_HOST_APP_KEY` in the repo root `.env`**

   Laravel needs an `APP_KEY` for the host app:

   ```bash
   cd reverb-host && php artisan key:generate --show
   ```

   Paste the value into root `.env` as `REVERB_HOST_APP_KEY=base64:...`.

3. **Enable the profile** in root `.env`:

   ```env
   COMPOSE_PROFILES=mysql,pgsql,redis,otel-collector,reverb
   ```

4. **Start the stack** (from shared-development root):

   ```bash
   docker compose up -d
   ```

Reverb listens on **`127.0.0.1:${FORWARD_REVERB_PORT:-8080}`** on the host and as **`shared-reverb:8080`** on the `shared-development` Docker network.

## ngrok (public WebSocket URL)

Expose the same host port Reverb uses so browsers outside `localhost` can connect (phones, remote teammates, webhooks that open a socket).

1. **Root `.env`:** add **`ngrok`** to **`COMPOSE_PROFILES`** (alongside **`reverb`**) and set **`NGROK_AUTHTOKEN`** ([dashboard token](https://dashboard.ngrok.com/get-started/your-authtoken)).
2. **Root `ngrok.yml`:** add an **`http`** tunnel whose **`addr`** is **`http://host.docker.internal:${FORWARD_REVERB_PORT:-8080}`** (must match the host port mapped for Reverb). See **`ngrok.yml.example`** entry **`shared-reverb`**. On Linux, if `host.docker.internal` fails from the ngrok container, add to **`ngrok/compose.yml`** under the ngrok service: `extra_hosts: ["host.docker.internal:host-gateway"]`, then `docker compose up -d ngrok`.
3. **`docker compose up -d`** from the repo root, then open **`http://127.0.0.1:4040`** for the ngrok inspector and your tunnel’s public **`https://…`** URL.

**`apps.json`** for each app that should use the tunnel — set **`options`** so Echo matches the ngrok host (no `https://` prefix in **`host`**; use **`443`**, **`https`**, **`true`** for TLS):

```json
"options": {
  "host": "your-subdomain.ngrok.app",
  "port": 443,
  "scheme": "https",
  "useTLS": true
}
```

**Consumer Laravel `.env`** (and matching **`VITE_REVERB_*`** for the browser):

```env
REVERB_HOST=your-subdomain.ngrok.app
REVERB_PORT=443
REVERB_SCHEME=https
```

`allowed_origins` in `apps.json` should include your Vite / app origins (for example `https://your-subdomain.ngrok.app` is usually wrong for the *frontend* origin; include the URL where the SPA is actually served).

## `apps.json` shape

Each entry matches Reverb’s application config (see `config/reverb.php`). Required fields: **`app_id`**, **`key`**, **`secret`**. Optional fields are merged with defaults (ping interval, rate limiting, etc.).

See **`apps.json.example`** for two sample apps. **`options`** (`host`, `port`, `scheme`, `useTLS`) describe what **Echo in the browser** should use; for local dev these are often `localhost`, `8080`, `http`, `false` when Vite/Echo connects directly to Reverb.

## Consumer Laravel project `.env`

Use the **same** `key`, `secret`, and `app_id` as one row in `apps.json`.

**From the host** (browser + Vite on `localhost`):

```env
BROADCAST_DRIVER=reverb
REVERB_APP_ID=app-one
REVERB_APP_KEY=change-me-app-one-key
REVERB_APP_SECRET=change-me-app-one-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_APP_NAME="${APP_NAME}"
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

**From another Docker container** on `shared-development`, PHP can publish to **`http://shared-reverb:8080`** if you configure the Reverb broadcasting connection accordingly; the **browser** still typically uses `localhost` or your `.test` host for WebSockets unless you terminate TLS on a proxy.

After `install:broadcasting` / Reverb in the consumer app, align env names with Laravel’s published `config/broadcasting.php` and `config/reverb.php` for that Laravel version.

## Horizontal scaling (optional)

Set in **root** `.env`:

```env
REVERB_SCALING_ENABLED=true
```

Ensure profile **`redis`** is enabled (`shared-redis` must be running). Tune `REVERB_REDIS_*` vars if needed. Single local Reverb process does **not** need scaling.

## Local development without Docker

```bash
cp apps.json.example apps.json
composer install
php artisan reverb:start
```

Uses `REVERB_SERVER_*` from `reverb-host/.env` if set.

## Files

| File | Purpose |
|------|---------|
| `apps.json` | Live multi-app credentials (not committed; copy from example) |
| `apps.json.example` | Template |
| `config/reverb.php` | Loads `apps.json` into Reverb |
| `compose.yml` | Fragment included by repo root `docker-compose.yml` |
| `Dockerfile` | PHP 8.3 CLI image with `pcntl`, `sockets` |
