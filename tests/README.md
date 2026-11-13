# Tests

Two independent harnesses, because Composer/phpunit and Packagist are unreachable
from this environment.

## Unit suite (no dependencies)

```
php tests/run.php
```

Loads `tests/bootstrap.php`, which provides minimal WordPress stubs (`tests/wp-stubs.php`),
then runs every `tests/Unit/*Test.php` case and exits non-zero on failure.

Stubs emulate WordPress meta behavior only as far as the addon needs it: keyed
`get_post_meta()` reads return already-unserialized values, exactly like WordPress.

## End-to-end suite (disposable WordPress)

Requires Docker with the local `wordpress:6.8.2-php8.2-apache` and `mariadb:10.11`
images. It mounts the addon and the ECard plugin into a throwaway stack on port 8099.

```
docker-compose -f tests/e2e/docker-compose.yml -p my-ecard-e2e up -d
docker-compose -f tests/e2e/docker-compose.yml -p my-ecard-e2e \
  exec -T -u www-data wp php /var/www/html/wp-content/plugins/wp-ecard-addons/tests/e2e/install.php
docker-compose -f tests/e2e/docker-compose.yml -p my-ecard-e2e \
  exec -T -u www-data wp php /var/www/html/wp-content/plugins/wp-ecard-addons/tests/e2e/checks.php with-ecard
```

The `without-ecard` mode of `checks.php` covers the degraded path: deactivate ECard
first, then run it.

`install.php` sets `$_SERVER['HTTP_HOST']` before loading WordPress because a CLI
install without it stores an empty `siteurl`.

Always remove the stack when finished:

```
docker-compose -f tests/e2e/docker-compose.yml -p my-ecard-e2e down --remove-orphans
```
