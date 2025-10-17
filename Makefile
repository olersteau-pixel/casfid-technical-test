.PHONY: build up dev stop down sh install init-db test phpstan phpcs

APP_SERVICE="server_casfid_technical_test"

help: 
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

build: 
	docker-compose build --no-cache

up: 
	docker-compose up -d

dev: 
	docker-compose up

stop: 
	docker-compose stop

down: 
	docker-compose down

sh: 
	docker-compose exec ${APP_SERVICE} /bin/bash

install:
	docker-compose exec ${APP_SERVICE} /bin/sh -c 'composer install'

init-db:
	docker-compose exec ${APP_SERVICE} /bin/sh -c 'php bin/console doctrine:database:create --if-not-exists'	
	docker-compose exec ${APP_SERVICE} /bin/sh -c 'php bin/console doctrine:migrations:migrate'	



test: 
	docker-compose exec ${APP_SERVICE} /bin/sh -c 'php bin/console doctrine:database:create --env=test --if-not-exists'	
	docker-compose exec ${APP_SERVICE} /bin/sh -c 'php bin/console doctrine:migrations:migrate --env=test --no-interaction'	
	docker-compose exec ${APP_SERVICE} /bin/sh -c 'vendor/bin/phpunit --list-tests'
	docker-compose exec ${APP_SERVICE} /bin/sh -c 'vendor/bin/phpunit tests'	

phpstan:
	docker-compose exec ${APP_SERVICE} /bin/sh -c 'vendor/bin/phpstan analyze -c phpstan.neon src'

phpcs:
	docker-compose exec ${APP_SERVICE} /bin/sh -c './vendor/bin/php-cs-fixer fix src'

scraper: 
	docker-compose exec ${APP_SERVICE} /bin/sh -c 'php bin/console app:scrape-feeds'	