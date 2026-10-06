# Contributing to AltDTO

## Prerequisites

- **Docker** (Docker Desktop, Colima, etc.) - that's it. You do **not** need PHP or Composer installed locally; every command below runs them inside the `indianos/composer` image (which bundles a current PHP CLI + Composer).

Verify Docker is available before continuing:

```zsh
docker ps
```

## One-time shell setup

Define two helper functions that transparently run Composer/PHP inside a container, mounting the repo at `/app`. Both accept an optional `-p PHP_VERSION` flag (e.g. `-p 8.2` or `-p8.2`) to pin a specific `indianos/composer:php<version>` tag; without it, they default to the `indianos/composer:latest` tag:

```zsh
dcomposer() {
    local php_version="latest"

    if [[ "$1" == "-p" ]]; then
        [[ -n "$2" ]] || {
            echo "Usage: dcomposer [-p PHP_VERSION] <composer-command> [args...]"
            return 1
        }

        php_version="php$2"
        shift 2
    elif [[ "$1" == -p* ]]; then
        php_version="php${1#-p}"
        shift
    fi

    docker run --rm \
        -v "$PWD":/app \
        -w /app \
        -e COMPOSER_HOME=/tmp/composer-home \
        --user "$(id -u):$(id -g)" \
        "indianos/composer:${php_version}" \
        "$@"
}

dphp() {
    local php_version="latest"

    if [[ "$1" == "-p" ]]; then
        [[ -n "$2" ]] || {
            echo "Usage: dphp [-p PHP_VERSION] <php-command> [args...]"
            return 1
        }

        php_version="php$2"
        shift 2
    elif [[ "$1" == -p* ]]; then
        php_version="php${1#-p}"
        shift
    fi

    docker run --rm \
        -v "$PWD":/app \
        -w /app \
        -e COMPOSER_HOME=/tmp/composer-home \
        --user "$(id -u):$(id -g)" \
        --entrypoint php \
        "indianos/composer:${php_version}" \
        "$@"
}
```

Paste these into your current shell session, or add them to `~/.zshrc` to persist across sessions.
Once defined, `dcomposer` behaves like `composer` and `dphp` behaves like `php`. Pin a specific PHP version with `-p <version>` on either (e.g. `dcomposer -p 8.2 install` or `dphp -p8.2 -v`), which runs the matching `indianos/composer:php<version>` tag instead of `latest`.

## Common tasks

Install or update dependencies:

```zsh
dcomposer install --no-interaction
dcomposer require some/package
dcomposer update some/package --with-all-dependencies --no-interaction
```

Run the test suites:

```zsh
dcomposer test
dcomposer test --filter testMemoryCacheDistinguishesCachedNullFromMiss
```

Regenerate the autoloader (usually only needed after classmap/file-based autoload changes; PSR-4 classes under `src/`/`tests/` are picked up automatically):

```zsh
dcomposer dump-autoload -o
```

Validate `composer.json`:

```zsh
dcomposer validate --strict
```

Check the PHP/Composer versions available inside the container (optionally pinning a PHP version with `-p`):

```zsh
dphp -v
dcomposer --version
dphp -p 8.2 -v
dcomposer -p 8.2 --version
```

## Notes / gotchas

- If a command hangs with no output, you likely forgot `--no-interaction` on a Composer command, or ran a `git`/pager-invoking command from a script without `--no-pager` (not a container issue, just a shell habit worth keeping).
- `indianos/composer` tracks a recent PHP version by default (`latest` tag), which stays within this project's supported range (`php-version: ['8.2', '8.3', '8.4', '8.5']` in `.github/workflows/ci.yml`). To pin an exact PHP version locally, pass `-p <version>` to `dcomposer` or `dphp` (e.g. `dcomposer -p 8.2 install`), which runs the matching `indianos/composer:php8.2` tag.
- Always keep the `--user "$(id -u):$(id -g)"` flag. Without it, `vendor/`, `composer.lock`, and any newly generated files end up root-owned on the host and require `sudo chown -R "$(id -u):$(id -g)" .` to fix.

