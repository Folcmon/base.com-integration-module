.PHONY: help install start stop restart logs test shell db-migrate metrics health grafana graylog

help: ## Wyświetl pomoc
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

install: ## Instaluj zależności
	composer install
	@echo "✅ Zależności zainstalowane"

start: ## Uruchom stack Docker Compose
	docker-compose up -d
	@echo "⏳ Czekam 10s na uruchomienie wszystkich serwisów..."
	@sleep 10
	@echo "✅ Stack uruchomiony!"
	@echo "📊 Grafana: http://localhost:3000 (admin/admin)"
	@echo "📋 Graylog: http://localhost:9000 (admin/admin)"
	@echo "🎯 Prometheus: http://localhost:9090"
	@echo "🌐 Aplikacja: http://localhost:8080"

stop: ## Zatrzymaj stack Docker Compose
	docker-compose down
	@echo "✅ Stack zatrzymany"

restart: stop start ## Restart stacku Docker Compose

logs: ## Pokaż logi wszystkich serwisów
	docker-compose logs -f

logs-app: ## Pokaż logi aplikacji
	docker-compose logs -f app

logs-worker: ## Pokaż logi workera
	docker-compose logs -f worker

logs-prometheus: ## Pokaż logi Prometheus
	docker-compose logs -f prometheus

logs-grafana: ## Pokaż logi Grafana
	docker-compose logs -f grafana

logs-graylog: ## Pokaż logi Graylog
	docker-compose logs -f graylog

test: ## Uruchom testy
	docker-compose exec app php bin/phpunit
	@echo "✅ Testy zakończone"

test-local: ## Uruchom testy lokalnie (bez Docker)
	php bin/phpunit

shell: ## Wejdź do shella aplikacji
	docker-compose exec app bash

db-migrate: ## Wykonaj migracje bazy danych
	docker-compose exec app php bin/console doctrine:migrations:migrate --no-interaction
	@echo "✅ Migracje wykonane"

metrics: ## Wyświetl metryki Prometheus
	@curl -s http://localhost:8080/metrics | grep baselinker

health: ## Sprawdź health checks
	@echo "Liveness probe:"
	@curl -s http://localhost:8080/health/live | jq .
	@echo "\nReadiness probe:"
	@curl -s http://localhost:8080/health/ready | jq .

grafana: ## Otwórz Grafana w przeglądarce
	@echo "🚀 Otwieranie Grafana..."
	@xdg-open http://localhost:3000 2>/dev/null || open http://localhost:3000 2>/dev/null || echo "Otwórz ręcznie: http://localhost:3000"

graylog: ## Otwórz Graylog w przeglądarce
	@echo "🚀 Otwieranie Graylog..."
	@xdg-open http://localhost:9000 2>/dev/null || open http://localhost:9000 2>/dev/null || echo "Otwórz ręcznie: http://localhost:9000"

import-allegro: ## Importuj zamówienia z Allegro (przykład)
	docker-compose exec app php bin/console baselinker:import-orders allegro --from=2024-01-01 --to=2024-01-31
	@echo "✅ Import Allegro zakończony"

import-amazon: ## Importuj zamówienia z Amazon (przykład)
	docker-compose exec app php bin/console baselinker:import-orders amazon --from=2024-01-01 --to=2024-01-31
	@echo "✅ Import Amazon zakończony"

clean: ## Wyczyść cache i logi
	docker-compose exec app php bin/console cache:clear
	@echo "✅ Cache wyczyszczony"

ps: ## Pokaż status wszystkich kontenerów
	docker-compose ps

build: ## Zbuduj obrazy Docker
	docker-compose build --no-cache

rebuild: build restart ## Przebuduj i uruchom ponownie

k8s-deploy: ## Wdróż na Kubernetes
	kubectl apply -f k8s/deployment.yaml
	@if kubectl get crd servicemonitors.monitoring.coreos.com >/dev/null 2>&1; then \
		kubectl apply -f k8s/monitoring.yaml; \
		echo "✅ ServiceMonitor zastosowany"; \
	else \
		echo "ℹ️  Brak CRD ServiceMonitor. Zainstaluj Prometheus Operator lub zastosuj k8s/monitoring.yaml później."; \
	fi
	@echo "✅ Wdrożenie K8s wykonane"

k8s-status: ## Status wdrożenia K8s
	kubectl get pods -n baselinker-integration
	kubectl get services -n baselinker-integration
	kubectl get hpa -n baselinker-integration

k8s-logs: ## Logi z K8s
	kubectl logs -n baselinker-integration -l app=baselinker-integration -f

k8s-delete: ## Usuń wdrożenie K8s
	kubectl delete -f k8s/monitoring.yaml --ignore-not-found
	kubectl delete -f k8s/deployment.yaml
	@echo "✅ Wdrożenie K8s usunięte"
