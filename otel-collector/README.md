# OpenTelemetry Collector

This provides a local OTLP (OpenTelemetry Protocol) collector for development environments.

## What It Does

- Receives traces from your Laravel applications using OpenTelemetry
- Logs traces to console for easy debugging during development
- Can be configured to forward traces to Datadog or other observability platforms

## Ports

- `4318` - OTLP HTTP receiver (used by Laravel)
- `4317` - OTLP gRPC receiver (optional)

Ports are bound to `127.0.0.1` only (local access). From the host use `http://127.0.0.1:4318`; from containers on the shared network use `http://otel-collector:4318`.

## Configuration

Your Laravel app should connect to: `http://otel-collector:4318/v1/traces`

The OTLP collector is configured via `otel-collector-config.yaml` and currently exports traces to console logs for development.

## Viewing Traces

To see traces in real-time:

```bash
docker logs -f otel-collector
```

## Production Setup

In production, the OTLP collector should be configured to export to Datadog APM instead of console logging.