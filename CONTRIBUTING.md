# Contributing to AltDTO

## Prerequisites

- **Docker** (Docker Desktop, Colima, etc.) - that's it. You do **not** need PHP or Composer installed locally; every command below runs them inside the official `composer` image (which bundles a current PHP CLI + Composer).

Verify Docker is available before continuing:

```zsh
docker ps
```

## One-time shell setup

Define two helper functions that transparently run Composer/PHP inside a container, mounting the repo at `/app`:

```zsh
dcomposer() { docker run --rm -v "$PWD":/app -w /app -e COMPOSER_HOME=/tmp/composer-home --user "$(id -u):$(id -g)" composer "$@"; }
dphp()      { docker run --rm -v "$PWD":/app -w /app -e COMPOSER_HOME=/tmp/composer-home --user "$(id -u):$(id -g)" --entrypoint php composer "$@"; }
```

Paste these into your current shell session, or add them to `~/.zshrc` to persist across sessions. 
Once defined, `dcomposer` behaves like `composer` and `dphp` behaves like `php`.

## Common tasks

Install dependencies:

```zsh
dcomposer install --no-interaction
```

Run the full test suite (either form works):

```zsh
dphp vendor/bin/phpunit -c .build/phpunit.xml.dist
dcomposer test
```

Run a single test or filter by name:

```zsh
dphp vendor/bin/phpunit -c .build/phpunit.xml.dist --filter testMemoryCacheDistinguishesCachedNullFromMiss
```

Add or update a dependency:

```zsh
dcomposer require some/package
dcomposer update some/package --with-all-dependencies --no-interaction
```

Regenerate the autoloader (usually only needed after classmap/file-based autoload changes; PSR-4 classes under `src/`/`tests/` are picked up automatically):

```zsh
dcomposer dump-autoload -o
```

Validate `composer.json`:

```zsh
dcomposer validate --strict
```

Check the PHP/Composer versions available inside the container:

```zsh
dphp -v
dcomposer --version
```

## Notes / gotchas

- If a command hangs with no output, you likely forgot `--no-interaction` on a Composer command, or ran a `git`/pager-invoking command from a script without `--no-pager` (not a container issue, just a shell habit worth keeping).
- `composer` tracks a recent PHP version, which stays within this project's supported range (`php-version: ['8.2', '8.3', '8.4', '8.5']` in `.github/workflows/ci.yml`). If you need to pin an exact PHP version locally, swap the image for `php:8.2-cli` and install `composer.phar` into it manually (e.g. via a multi-stage `COPY --from=composer /usr/bin/composer /usr/bin/composer`).
- Always keep the `--user "$(id -u):$(id -g)"` flag. Without it, `vendor/`, `composer.lock`, and any newly generated files end up root-owned on the host and require `sudo chown -R "$(id -u):$(id -g)" .` to fix.

