start: app

app: deps
	docker-compose up -d app

behat:
	docker-compose run --rm cli bash -c "cd ../features && ../data/vendor/bin/behat --stop-on-failure"

behatappend:
	docker-compose run --rm cli bash -c "cd ../features && ../data/vendor/bin/behat --append-snippets"

clean:
	docker-compose kill
	docker system prune -f

deps:
	docker-compose run --rm cli composer install --no-scripts

depsupdate:
	docker-compose run --rm cli composer update --no-scripts

test: deps behat
