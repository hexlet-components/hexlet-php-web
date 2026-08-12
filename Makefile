PORT ?= 3000

install:
	composer install

start:
	php -S localhost:$(PORT) index.php

lint:
	composer validate
	find src index.php -name '*.php' -exec php -l {} \;

# Проверка запуском: демо не имеет тестов, и подъём версии php ломает его не в
# composer, а на первом же запросе.
check:
	@php -S 127.0.0.1:$(PORT) index.php & \
	server=$$!; \
	trap "kill $$server 2>/dev/null" EXIT; \
	for i in $$(seq 1 30); do \
	  curl -sf http://127.0.0.1:$(PORT)/ >/dev/null && break; \
	  sleep 1; \
	done; \
	curl -sf http://127.0.0.1:$(PORT)/ >/dev/null && \
	curl -sf http://127.0.0.1:$(PORT)/articles >/dev/null && \
	curl -sf http://127.0.0.1:$(PORT)/api/users >/dev/null && \
	curl -sf http://127.0.0.1:$(PORT)/guestbook >/dev/null

.PHONY: install start lint check
