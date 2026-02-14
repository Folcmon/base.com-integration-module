# Monitoring & Observability Stack

Ten dokument opisuje kompletny stack monitorowania i logowania dla modułu integracji Baselinker.

## Architektura Monitoringu

### Komponenty

1. **Prometheus** - zbieranie metryk wydajności
2. **Grafana** - wizualizacja metryk i dashboardy
3. **Graylog** - centralne logowanie i analiza logów
4. **Redis** - storage dla metryk Prometheus
5. **Kubernetes** - orkiestracja i auto-skalowanie

## Metryki Prometheus

### Dostępne Metryki

#### 1. API Request Duration (Histogram)
```
baselinker_api_request_duration_milliseconds
```
- **Opis**: Czas trwania zapytań do API Baselinker w milisekundach
- **Labels**: `method`, `status_code`
- **Buckets**: 10, 50, 100, 250, 500, 1000, 2500, 5000, 10000 ms

#### 2. API Requests Total (Counter)
```
baselinker_api_requests_total
```
- **Opis**: Całkowita liczba zapytań do API
- **Labels**: `method`, `status` (success/error)

#### 3. API Errors Total (Counter)
```
baselinker_api_errors_total
```
- **Opis**: Całkowita liczba błędów API
- **Labels**: `method`, `error_type` (client_error, server_error, transport_error, decoding_error)

#### 4. Orders Imported Total (Counter)
```
baselinker_orders_imported_total
```
- **Opis**: Całkowita liczba zaimportowanych zamówień
- **Labels**: `marketplace` (allegro, amazon)

#### 5. Queue Processing Duration (Histogram)
```
baselinker_queue_processing_duration_seconds
```
- **Opis**: Czas przetwarzania wiadomości z kolejki w sekundach
- **Labels**: `handler`, `status` (success/failure)
- **Buckets**: 0.1, 0.5, 1, 2.5, 5, 10, 30, 60, 120 seconds

### Endpoint Metryk

Metryki są eksponowane pod adresem:
```
GET /metrics
```

## Grafana Dashboards

### Baselinker Integration Dashboard

Dashboard zawiera następujące panele:

1. **API Request Duration (p95, p99)** - wykres czasowy pokazujący percentyle 95 i 99 czasu odpowiedzi API
2. **API Request Rate** - liczba zapytań na sekundę, z podziałem na sukces/błąd
3. **API Errors (5m rate)** - wskaźnik błędów w ostatnich 5 minutach
4. **Orders Imported (per hour)** - liczba zaimportowanych zamówień na godzinę, z podziałem na marketplace
5. **Queue Processing Time (p95)** - 95. percentyl czasu przetwarzania kolejki

### Dostęp do Grafana

- **URL**: http://localhost:3000
- **User**: admin
- **Password**: admin

## Graylog - Centralne Logowanie

### Konfiguracja

Graylog zbiera logi w formacie GELF (Graylog Extended Log Format) przez UDP.

#### Porty
- **Web Interface**: 9000
- **GELF UDP**: 12201
- **Syslog TCP**: 1514
- **Syslog UDP**: 1514

### Dostęp do Graylog

- **URL**: http://localhost:9000
- **User**: admin
- **Password**: admin

### Strukturalne Logowanie

Wszystkie logi są w formacie JSON z następującymi polami:

```json
{
  "operation": "getOrders",
  "duration_ms": 245.67,
  "status_code": 200,
  "timestamp": 1234567890,
  "datetime": "2024-01-15T10:30:00+00:00",
  "is_slow": false,
  "performance_category": "good",
  "is_success": true,
  "is_client_error": false,
  "is_server_error": false,
  "service": "baselinker-integration",
  "integration_type": "api",
  "endpoint": "https://api.baselinker.com/connector.php",
  "has_error": false,
  "error_type": null
}
```

### Kategorie Wydajności

- **excellent**: < 100ms
- **good**: 100-500ms
- **acceptable**: 500-1000ms
- **slow**: 1000-5000ms
- **critical**: > 5000ms

## Kubernetes Deployment

### Health Checks

#### Liveness Probe
```
GET /health/live
```
Sprawdza czy aplikacja jest uruchomiona.

#### Readiness Probe
```
GET /health/ready
```
Sprawdza czy aplikacja jest gotowa do obsługi ruchu (sprawdza połączenie z bazą danych).

### Auto-scaling

HorizontalPodAutoscaler konfiguracja:
- **Min replicas**: 2
- **Max replicas**: 10
- **CPU target**: 70%
- **Memory target**: 80%

### Resource Limits

#### Web Pods
- **Requests**: 256Mi RAM, 250m CPU
- **Limits**: 512Mi RAM, 500m CPU

#### Worker Pods
- **Requests**: 128Mi RAM, 100m CPU
- **Limits**: 256Mi RAM, 250m CPU

## Uruchomienie Stack'u Monitoringu

### Docker Compose (Development)

```bash
# Uruchomienie wszystkich serwisów
docker-compose up -d

# Sprawdzenie statusu
docker-compose ps

# Logi z konkretnego serwisu
docker-compose logs -f prometheus
docker-compose logs -f grafana
docker-compose logs -f graylog
```

### Kubernetes (Production)

```bash
# Utworzenie namespace
kubectl apply -f k8s/deployment.yaml

# Sprawdzenie statusu
kubectl get pods -n baselinker-integration

# Sprawdzenie logów
kubectl logs -n baselinker-integration -l app=baselinker-integration -f

# Sprawdzenie metryk
kubectl top pods -n baselinker-integration
```

## Alerty i Monitorowanie

### Zalecane Alerty Prometheus

#### 1. Wysoka liczba błędów API
```yaml
- alert: HighAPIErrorRate
  expr: rate(baselinker_api_errors_total[5m]) > 0.1
  for: 5m
  annotations:
    summary: "Wysoka liczba błędów API ({{ $value }} errors/s)"
```

#### 2. Wolne zapytania API
```yaml
- alert: SlowAPIRequests
  expr: histogram_quantile(0.95, rate(baselinker_api_request_duration_milliseconds_bucket[5m])) > 5000
  for: 10m
  annotations:
    summary: "Wolne zapytania API (p95: {{ $value }}ms)"
```

#### 3. Długie przetwarzanie kolejki
```yaml
- alert: SlowQueueProcessing
  expr: histogram_quantile(0.95, rate(baselinker_queue_processing_duration_seconds_bucket[5m])) > 60
  for: 10m
  annotations:
    summary: "Długie przetwarzanie kolejki (p95: {{ $value }}s)"
```

## Troubleshooting

### Brak metryk w Prometheus

1. Sprawdź czy endpoint `/metrics` jest dostępny:
```bash
curl http://localhost:8080/metrics
```

2. Sprawdź konfigurację Prometheus:
```bash
docker-compose exec prometheus cat /etc/prometheus/prometheus.yml
```

3. Sprawdź logi Prometheus:
```bash
docker-compose logs prometheus
```

### Brak logów w Graylog

1. Sprawdź czy Graylog działa:
```bash
docker-compose ps graylog
```

2. Sprawdź połączenie GELF:
```bash
nc -zvu graylog 12201
```

3. Sprawdź input w Graylog Web UI:
   - System → Inputs → GELF UDP

### Grafana nie wyświetla danych

1. Sprawdź połączenie z Prometheus:
   - Configuration → Data Sources → Prometheus → Test

2. Sprawdź czy Prometheus zbiera metryki:
```bash
curl http://localhost:9090/api/v1/query?query=baselinker_api_requests_total
```

## Best Practices

### 1. Logowanie
- Używaj strukturalnego logowania (JSON)
- Dodawaj kontekst do każdego loga
- Używaj odpowiednich poziomów logowania (ERROR, WARNING, INFO, DEBUG)
- Loguj zarówno sukces jak i błędy

### 2. Metryki
- Zbieraj metryki dla każdego krytycznego procesu
- Używaj histogramów dla czasów (duration)
- Używaj counterów dla zliczeń (total counts)
- Dodawaj labels ale nie za dużo (kardynalność)

### 3. Alerty
- Twórz alerty dla krytycznych metryk biznesowych
- Używaj odpowiednich thresholdów
- Dodawaj runbooki do alertów
- Testuj alerty przed wdrożeniem na produkcję

### 4. Performance
- Monitoruj p95, p99 percentyle, nie tylko średnie
- Śledź wolne zapytania (> 5s)
- Monitoruj memory usage i CPU
- Używaj cache gdzie to możliwe

## Integracja z CI/CD

### Przed wdrożeniem

1. Uruchom testy integracyjne
2. Sprawdź czy metryki są eksponowane
3. Zwaliduj konfigurację K8s
4. Sprawdź health checks

### Po wdrożeniu

1. Monitoruj metryki przez pierwsze 30 minut
2. Sprawdź logi w Graylog
3. Zweryfikuj auto-scaling
4. Testuj alerty

## Dalsze Usprawnienia

1. **Distributed Tracing** - Dodanie Jaeger/Zipkin dla end-to-end tracing
2. **APM** - Application Performance Monitoring (New Relic, DataDog)
3. **Synthetic Monitoring** - Automatyczne testy dostępności
4. **Log Analytics** - Machine learning na logach dla wykrywania anomalii
5. **SLO/SLI** - Definicja Service Level Objectives i Indicators

