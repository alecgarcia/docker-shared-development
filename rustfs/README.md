# RustFS (S3-compatible storage)

[RustFS](https://rustfs.com) is an open-source, S3-compatible object storage service (Apache 2.0). Enable it by adding **`rustfs`** to **`COMPOSE_PROFILES`** in the repo root `.env`, then run `docker compose up -d`.

## Ports (defaults)

- **9000** – S3 API (`http://127.0.0.1:9000`)
- **9001** – Web console (`http://127.0.0.1:9001`)

Override with `FORWARD_RUSTFS_API_PORT` and `FORWARD_RUSTFS_CONSOLE_PORT` in `.env`.

## Credentials

Set in root `.env`:

- `RUSTFS_ACCESS_KEY`
- `RUSTFS_SECRET_KEY`

Defaults match `.env.example` (`rustfsadmin` / `rustfsadmin`). Change these for anything beyond local dev.

## Connecting from other projects

- **Host:** `127.0.0.1:9000` with the access/secret keys above.
- **Docker (shared network):** `http://shared-rustfs:9000` (attach to `shared-development`).

Use any S3 client with path-style or virtual-hosted style per [RustFS S3 compatibility](https://docs.rustfs.com/features/s3-compatibility).

## Data

Persistent data lives in the Docker volume `rustfs-data` (see `compose.yml`).

## Migrating from MinIO

RustFS is API-compatible with S3/MinIO-style clients. Point your app at the same host/port and use `RUSTFS_ACCESS_KEY` / `RUSTFS_SECRET_KEY` like MinIO access/secret keys. You will need to recreate buckets and re-upload objects; this stack does not migrate MinIO volume data automatically.
