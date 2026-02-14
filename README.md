# base.com-integration-module

Symfony-based integration module for a helpdesk system that connects to the Baselinker API. It fetches orders from multiple marketplaces (Allegro and Amazon), applies CQRS with DDD-style layering, and uses Symfony Messenger for queued imports.

## Key Features
- Baselinker API client with error handling, performance logging, and request monitoring
- CQRS (commands for imports, queries for read operations)
- Strategy/registry for marketplace-specific filters (minimum two marketplaces)
- Symfony Messenger-based queueing (sync or async)
- **Prometheus metrics** for performance monitoring
- **Grafana dashboards** for visualization
- **Graylog integration** for centralized logging
- **Kubernetes-ready** with health checks and auto-scaling
- Unit and integration tests

## Requirements
- PHP 8.4+
- Composer
- PostgreSQL (if using Doctrine Messenger transport)
- Redis (for Prometheus metrics storage)
- Docker & Docker Compose (for local development)

## Setup

### Quick Start z Makefile

```bash
# Instalacja zależności
make install

# Uruchomienie całego stacku
make start

# Wykonanie migracji
make db-migrate

# Uruchomienie testów
make test

# Zobacz wszystkie dostępne komendy
make help
```

### Manualna Instalacja

1. Install dependencies

```bash
composer install
```

2. Configure environment variables in `.env` or `.env.local`

```dotenv
BL_API_URL=https://api.baselinker.com/connector.php
BL_API_TOKEN=your_token_here
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
REDIS_URL=redis://redis:6379
GRAYLOG_HOST=graylog
GRAYLOG_PORT=12201
```

If you want a synchronous queue for local runs/tests:

```dotenv
MESSENGER_TRANSPORT_DSN=sync://
```

3. Start the monitoring stack with Docker Compose

```bash
docker-compose up -d
```

This will start:
- Application (PHP-FPM + Nginx)
- PostgreSQL database
- Redis (for Prometheus metrics)
- Prometheus (metrics collection)
- Grafana (visualization)
- Graylog + Elasticsearch + MongoDB (centralized logging)
- Messenger worker (queue processing)

## Usage
Queue import for a marketplace:

```bash
bin/console baselinker:import-orders allegro --from=2024-01-01 --to=2024-01-31
```

Process queued messages (async transport):

```bash
bin/console messenger:consume async -vv
```

## Tests
Run the full suite:

```bash
php bin/phpunit
```

## Architecture Notes
- Domain: `src/Integration/Baselinker/Domain`
- Application (CQRS): `src/Integration/Baselinker/Application`
- Infrastructure: `src/Integration/Baselinker/Infrastructure`
- UI (CLI): `src/Integration/Baselinker/UI`

Patterns used:
- Strategy + registry for marketplace-specific request parameters
- Mapper for translating Baselinker payloads to domain orders
- Adapter around Symfony HttpClient for API access
- Middleware pattern for queue metrics collection

## Monitoring and Logging

### Prometheus Metrics
Access metrics at: http://localhost:8080/metrics

Available metrics:
- `baselinker_api_request_duration_milliseconds` - API request duration
- `baselinker_api_requests_total` - Total API requests
- `baselinker_api_errors_total` - Total API errors
- `baselinker_orders_imported_total` - Total orders imported
- `baselinker_queue_processing_duration_seconds` - Queue processing time

### Grafana Dashboards
- URL: http://localhost:3000
- User: `admin` / Password: `admin`
- Pre-configured dashboard: "Baselinker Integration Monitoring"

### Graylog (Centralized Logging)
- URL: http://localhost:9000
- User: `admin` / Password: `admin`
- Structured JSON logs with performance categories
- GELF protocol on port 12201 (UDP)

### Health Checks
- Liveness: `GET /health/live`
- Readiness: `GET /health/ready`

For detailed monitoring documentation, see [MONITORING.md](MONITORING.md)

## Kubernetes Deployment

Deploy to Kubernetes:

```bash
kubectl apply -f k8s/deployment.yaml
```

Features:
- Auto-scaling (HPA) based on CPU/Memory
- Health probes (liveness & readiness)
- Service monitoring with Prometheus
- Separate web and worker deployments
- Resource limits and requests

## Supported Marketplaces
- Allegro
- Amazon
