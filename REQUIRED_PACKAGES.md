# UWAGA: Wymagane Pakiety do Zainstalowania

Po sklonowaniu projektu, wykonaj:

```bash
composer install
```

To zainstaluje wszystkie wymagane pakiety, w tym:

## Główne Zależności

- `promphp/prometheus_client_php: ^2.10` - Prometheus metrics client
- `graylog2/gelf-php: ^2.0` - Graylog GELF handler dla Monolog
- `ext-redis` - PHP Redis extension (dla Prometheus storage)

## Instalacja Rozszerzenia Redis

### Ubuntu/Debian:
```bash
sudo apt-get install php8.4-redis
```

### macOS (Homebrew):
```bash
pecl install redis
```

### Docker (Zalecane):
```bash
docker-compose up -d
# Redis jest już zainstalowany w kontenerze
```

## Weryfikacja

Po instalacji sprawdź:

```bash
php -m | grep redis
composer show | grep prometheus
composer show | grep graylog
```

## Uruchomienie

```bash
# Z Dockerem (zalecane)
docker-compose up -d

# Lokalnie
php bin/console baselinker:import-orders allegro --from=2024-01-01 --to=2024-01-31
```

## Metryki i Monitoring

Po uruchomieniu dostępne są:

- **Metryki**: http://localhost:8080/metrics
- **Grafana**: http://localhost:3000 (admin/admin)
- **Graylog**: http://localhost:9000 (admin/admin)
- **Prometheus**: http://localhost:9090

---

Szczegółowe instrukcje w **INSTALLATION.md**

