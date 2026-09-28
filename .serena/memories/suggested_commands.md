# Commands

Everything runs through the shared FrontAccounting CI image ([[ci]]); the
tasks themselves are composer scripts, so they also work on a host that
already has PHP and a FrontAccounting install.

## CI image (`../frontaccounting/docker/ci/plugin-test.sh`)

| command | effect |
|---|---|
| `plugin-test.sh --name api --no-activate --setup 'composer install --no-interaction --no-progress' . -- sh tools/ci.sh` | the full CI run, locally |
| `... -- sh tools/ci.sh` with `--keep` | leaves the container running, FA on a printed localhost port |
| `... -- composer run test -- --filter X` | narrow phpunit to one test, inside the image |

`tools/ci.sh` runs lint, phpcs (advisory), analyze, then phpunit. See [[ci]]
for the full option list (`--fa`, `--php`, `--dataset`, `--with`, ...).

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
