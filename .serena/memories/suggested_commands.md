# Commands

Everything runs through the docker stack ([[docker]]); the tasks themselves are
composer scripts, so they also work on a host that already has PHP and a
FrontAccounting install.

## Docker

| command | effect |
|---|---|
| `docker/fa-api init` | write `docker/.env` with free host ports (once) |
| `docker/fa-api up` | build, boot, seed `fa_test`, `composer install` |
| `docker/fa-api test [args]` | PHPUnit (`--filter X` to narrow) |
| `docker/fa-api lint [--strict]` | `php -l`, then phpcs PSR-2 (advisory) |
| `docker/fa-api analyze` | PHPStan |
| `docker/fa-api ci` | up --build + lint + analyze + test, as GitHub Actions runs it |
| `docker/fa-api make <target>` | a phpmake target: `docs-json`, `docs`, `package`, `clean` |
| `docker/fa-api logs errors` | FA's `tmp/errors.log` — first stop for a 200 with no JSON |
| `docker/fa-api db reset` | reload the fixture |
| `docker/fa-api shell` | bash in the module directory |

## Composer scripts (what those delegate to)

`composer test`, `lint`, `cs:check`, `cs:fix`, `analyze`, `quality` (lint +
analyze), `ci` (quality + test). `composer run -l` lists them with descriptions.

`composer test` needs a server and a seeded `fa_test` database — it is an HTTP
suite, not unit tests. See [[testing]].

## Builds (phpmake, `makefile.json`)

| target | effect |
|---|---|
| `make.phar docs-json` | regenerate `swagger.json` from the `@SWG` annotations |
| `make.phar docs` | that, then `spectacle` for the static HTML (node, host-only) |
| `make.phar package` | release `.zip` + `.tgz` against `--no-dev` vendor, then restore dev deps |

`package` re-installs dev dependencies as its last command rather than depending
on a `vendor-dev` target: phpmake re-runs shared dependencies and has no
post-hooks, so ordering has to be explicit.

Install `make.phar` on the host by cloning saygoweb/phpmake and running
`php make.php install`; it is already on PATH in the docker image.
