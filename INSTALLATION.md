# INSTALLATION.md

## Instrukcje Instalacji Modułu Integracji Baselinker

### Wymagania Wstępne

- PHP 8.4+
- Composer 2.x
- Docker & Docker Compose
- Git

### Krok 1: Klonowanie Repozytorium

```bash
git clone <repository-url>
cd base.com-integration-module
```

### Krok 2: Instalacja Zależności PHP

```bash
composer install
```

To zainstaluje wszystkie wymagane pakiety:
- Symfony 8.0
- Doctrine ORM
- Prometheus Client PHP
- Graylog GELF PHP
- Monolog
- PHPUnit

### Krok 3: Konfiguracja Środowiska

Skopiuj plik przykładowej konfiguracji:

```bash
cp .env.example .env.local
```

Edytuj `.env.local` i ustaw swoje wartości:

```dotenv
BL_API_TOKEN=your_real_baselinker_token
DATABASE_URL="postgresql://baselinker:baselinker@database:5432/baselinker?serverVersion=16&charset=utf8"
REDIS_URL=redis://redis:6379
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
```

### Krok 4: Uruchomienie Stacku Docker

Uruchom wszystkie serwisy (aplikacja, baza danych, Redis, Prometheus, Grafana, Graylog):

```bash
docker-compose up -d
```

Sprawdź status:

```bash
docker-compose ps
```

Wszystkie serwisy powinny być w stanie "Up".

### Krok 5: Inicjalizacja Bazy Danych

Wejdź do kontenera aplikacji:

```bash
docker-compose exec app bash
```

Uruchom migracje:

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

### Krok 6: Weryfikacja Instalacji

#### Sprawdź Health Checks

```bash
curl http://localhost:8080/health/live
curl http://localhost:8080/health/ready
```

Oba powinny zwrócić status 200.

#### Sprawdź Metryki Prometheus

```bash
curl http://localhost:8080/metrics
```

Powinieneś zobaczyć metryki Prometheus w formacie tekstowym.

#### Sprawdź Grafana

Otwórz przeglądarkę: http://localhost:3000
- Login: admin
- Hasło: admin

Dashboard "Baselinker Integration Monitoring" powinien być dostępny.

#### Sprawdź Graylog

Otwórz przeglądarkę: http://localhost:9000
- Login: admin
- Hasło: admin

### Krok 7: Uruchomienie Testów

Wewnątrz kontenera:

```bash
docker-compose exec app php bin/phpunit
```

Lub lokalnie (jeśli masz PHP 8.4):

```bash
php bin/phpunit
```

### Krok 8: Testowe Importowanie Zamówień

```bash
docker-compose exec app php bin/console baselinker:import-orders allegro --from=2024-01-01 --to=2024-01-31
```

Sprawdź w Grafanie czy metryki się pojawiły.

## Troubleshooting

### Problem: "Connection refused" do Redis

```bash
# Sprawdź czy Redis działa
docker-compose ps redis
docker-compose logs redis

# Restart Redis
docker-compose restart redis
```

### Problem: "Connection refused" do PostgreSQL

```bash
# Sprawdź czy PostgreSQL działa
docker-compose ps database
docker-compose logs database

# Restart PostgreSQL
docker-compose restart database
```

### Problem: Brak metryk w Prometheus

```bash
# Sprawdź logi aplikacji
docker-compose logs app

# Sprawdź logi Prometheus
docker-compose logs prometheus

# Sprawdź czy endpoint działa
curl http://localhost:8080/metrics
```

### Problem: Graylog nie pokazuje logów

```bash
# Sprawdź czy Elasticsearch działa
docker-compose ps elasticsearch
docker-compose logs elasticsearch

# Sprawdź czy MongoDB działa
docker-compose ps mongodb
docker-compose logs mongodb

# Restart całego stacku Graylog
docker-compose restart mongodb elasticsearch graylog
```

### Problem: Composer wymaga rozszerzenia Redis

Musisz zainstalować rozszerzenie PHP Redis:

```bash
# Ubuntu/Debian
sudo apt-get install php8.4-redis

# macOS (Homebrew)
pecl install redis

# Lub użyj Docker (zalecane)
docker-compose exec app php -m | grep redis
```

## Wdrożenie na Kubernetes

### Przygotowanie

1. Zbuduj obraz Docker:

```bash
docker build -t baselinker-integration:latest .
```

2. Wypchnij do rejestru:

```bash
docker tag baselinker-integration:latest your-registry/baselinker-integration:latest
docker push your-registry/baselinker-integration:latest
```

3. Zaktualizuj `k8s/deployment.yaml` z URL obrazu.

### Wdrożenie

```bash
kubectl apply -f k8s/deployment.yaml
```

### Sprawdzenie statusu

```bash
kubectl get pods -n baselinker-integration
kubectl get services -n baselinker-integration
kubectl get hpa -n baselinker-integration
```

### Dostęp do logów

```bash
kubectl logs -n baselinker-integration -l app=baselinker-integration -f
```

## Konfiguracja Produkcyjna

### 1. Secrets Management

Nie commituj `.env` do repozytorium. Użyj:
- Kubernetes Secrets
- HashiCorp Vault
- AWS Secrets Manager
- Azure Key Vault

### 2. SSL/TLS

Skonfiguruj Ingress z certyfikatami SSL (Let's Encrypt).

### 3. Backup

Skonfiguruj automatyczne backupy:
- PostgreSQL (pg_dump)
- Redis (RDB snapshots)
- Volumes Kubernetes

### 4. Monitoring Alerts

Skonfiguruj Alertmanager dla Prometheus z integracją:
- Slack
- PagerDuty
- Email

### 5. Log Retention

Skonfiguruj politykę retencji w Graylog (np. 30 dni).

## Wsparcie

Jeśli masz problemy:
1. Sprawdź [MONITORING.md](MONITORING.md)
2. Przejrzyj logi: `docker-compose logs`
3. Sprawdź health checks
4. Zweryfikuj konfigurację w `.env.local`

