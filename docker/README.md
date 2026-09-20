# Docker test stack

A throwaway FrontAccounting install with this module plugged into it — Apache +
mod_php + MariaDB — for developing the API and running its test suite without a
FrontAccounting checkout, a PHP, or a database on your own machine.

    docker/fa-api init      # pick host ports that are free here
    docker/fa-api up        # build, boot, seed, install composer deps
    docker/fa-api test      # run the PHPUnit suite
    docker/fa-api lint      # php -l, then phpcs PSR-2
    docker/fa-api analyze   # PHPStan

`up` prints the URLs. `docker/fa-api help` lists every command.

The same CLI is what `.github/workflows/ci.yml` runs, so `docker/fa-api ci`
locally is the build that will run on a push.

## How it fits together

This module is not an application. It is an extension that only runs from
`modules/api` inside a FrontAccounting tree, which is why the Travis build this
replaced had to clone FrontAccounting and rsync the module into it before it
could test anything.

The stack does the same thing, pinned and repeatable:

| | |
| --- | --- |
| FrontAccounting | cloned into the image at build time from `FA_REPO` / `FA_REF` — upstream `master` by default |
| this checkout | bind-mounted at `/var/www/html/modules/api`, so an edit is live on the next request |
| `config.php`, `config_db.php`, the two `installed_extensions.php` and `lang/installed_languages.inc` | written by the entrypoint on every start, into the image's FA tree |
| `vendor/` | installed by `up` from `composer.lock`, into your checkout, owned by you |

Nothing is written into your checkout except `vendor/`. There is no
FrontAccounting checkout to keep in step and no generated config of yours to
overwrite — unlike the old `gulp env-files`, which copied fixture config files
over whatever was in the working tree.

To build against a different FrontAccounting, set both in `docker/.env` and
rebuild — the clone is a build step:

    FA_REPO=https://github.com/cambell-prince/frontaccounting.git
    FA_REF=master-cp

`docker/fa-api info` prints the commit the running image was built from.

### Port 8000

Inside the container Apache listens on **8000**, not 80, because
`tests/TestEnvironment.php` builds its Guzzle client against
`http://localhost:8000` and phpunit runs in that container. Serving there is
what lets the committed suite run unedited. On the host it is `HTTP_PORT`, 8090
by default, clear of the FrontAccounting stack's 8080.

## Datasets

`docker/fa-api db load <what>` and `db reset <what>`:

| what | source | login |
| --- | --- | --- |
| `test` (default) | `tests/data/fa_test.sql.gz` | test / test |
| `test-2.3` | `tests/data/fa_test_2.3.sql.gz` | test / test |
| `new` | FrontAccounting's `sql/en_US-new.sql`, from the image | admin / password |
| `demo` | FrontAccounting's `sql/en_US-demo.sql`, from the image | admin / password |
| a path | any `.sql` or `.sql.gz` on the host | — |

`test` is the fixture the old `gulp env-db` task loaded, and the one the suite's
`X-COMPANY: 0` / `X-USER: test` / `X-PASSWORD: test` credentials come from.
`docker/fa-api db dump` writes a gzipped dump back out.

## Tasks live in composer, not here

`fa-api test`, `lint` and `analyze` run `composer test`, `composer lint` +
`composer cs:check`, and `composer analyze`. The CLI adds what a container needs
around them — waiting for Apache, seeding the database, running as your uid —
and nothing else, so there is one definition of each task rather than two that
drift. They work on a host with its own FrontAccounting install too.

`fa-api make <target>` runs a [phpmake](https://github.com/saygoweb/phpmake)
target from `makefile.json` — `docs-json`, `docs`, `package`, `clean`.
`make.phar` is built into the image. `docs` also needs `spectacle`, a node tool
that is deliberately not installed here; `docs-json`, which regenerates
`swagger.json` from the `@SWG` annotations, works on its own.

## What the suite needs

19 tests, 216 assertions, green on PHP 7.4 against FrontAccounting master. They
are HTTP integration tests: they drive Apache in this container, so `up` has to
have seeded the database first. `fa-api test` waits for the application before
the first assertion rather than letting seven Guzzle connection errors stand in
for "the stack is not up yet".

Two of them depend on what ran before — `SalesTest` posts a hard-coded
`customer_id=2`, so it fails on its own against a freshly seeded database and
passes in a full run. Use `fa-api test` without `--filter` to reproduce CI.

When a response comes back as HTML or an empty body, FrontAccounting has turned
a database error into `E_USER_ERROR` and `output_html()` has swallowed it.
`docker/fa-api logs errors` shows the `DATABASE ERROR` line. That is the first
place to look; the endpoint will have answered 200, never a 500.

## Continuous integration

`.github/workflows/ci.yml` runs `up --build`, `lint`, `analyze` and `test`
through this same CLI, on two PHP versions:

- **7.4** — the oldest the module supports, and the platform `composer.json`
  resolves the lock against, so one lock file serves both jobs.
- **8.3** — current. Both gate; the suite is green on each.

Run the stack on either. The environment wins over `docker/.env`, so:

    PHP_VERSION=8.3 COMPOSE_PROJECT_NAME=fa-api-83 HTTP_PORT=8095 DB_PORT=3311 \
        docker/fa-api up --build

gives a second stack beside the 7.4 one rather than replacing it.

`lint` gates on `php -l` and reports phpcs without failing — there are 125 PSR-2
errors in `src/` and `tests/` today, 77 of which `composer cs:fix` can correct
automatically. `docker/fa-api lint --strict` makes them count, once someone has
done that. `analyze` gates on PHPStan level 0, which is clean; `phpstan.neon`
records what raising the level would cost.

`docker/fa-api` has to be executable in git for the workflow to run it. This
repo has `core.fileMode=false`, so `chmod +x` alone does not record it:
`git update-index --chmod=+x docker/fa-api`, confirmed with `git ls-files -s`.

## Settings

`docker/.env` — gitignored, written by `docker/fa-api init`.
`docker/.env.example` lists everything that can go in it: ports, PHP and MariaDB
versions, which FrontAccounting to build against, database name and credentials,
the seed dataset, xdebug.

The stack is named after the checkout directory, so a worktree gets its own
containers and its own database volume rather than colliding with the main
checkout.

## Base image and PHP version

Debian **trixie** (13) with PHP from [Ondřej Surý's
repository](https://deb.sury.org/), for the reason the FrontAccounting stack
gives at more length: the official `php:7.4-*` images are built on bullseye,
whose LTS ended on 2026-08-31, and there is no 7.4 image on trixie and never
will be.

`PHP_VERSION` accepts anything sury publishes for trixie (7.4, 8.0 … 8.4).
Debian's packaging keeps a `conf.d` per SAPI, so `php.ini` is installed into
both the `apache2` and `cli` trees — the suite runs under the latter.

## Debugging

`display_errors` is **off** here, unlike the FrontAccounting stack, because
every response is JSON and a warning printed into the body makes `json_decode()`
return null. Errors go to the container log and to FrontAccounting's own
`tmp/errors.log`:

    docker/fa-api logs app        # Apache, PHP startup errors
    docker/fa-api logs errors     # FrontAccounting's error log

xdebug is compiled in but **not loaded** unless asked for. That is not
tidiness: FrontAccounting guards its xdebug calls with
`function_exists('xdebug_call_file')`, and under xdebug 3 that guard is wrong —
the function exists whenever the extension is loaded but throws unless
`xdebug.mode` includes `develop`. An extension sitting at `mode=off` breaks
every request. The entrypoint therefore unloads it outright.

To use it, set `XDEBUG_MODE=debug` in `docker/.env` and run **`docker/fa-api
up`** — not `restart`, which keeps the container and its old environment. Then
listen on port 9003; it starts on trigger, so set an `XDEBUG_TRIGGER` cookie or
query parameter. `docker/fa-api` folds `develop` into whatever mode you ask for,
for the reason above.

## Not included

`spectacle`, the node tool that renders `swagger.json` into the static HTML
published on `gh-pages`. It is the last thing in this project that wanted node,
and installing a node toolchain into the image to run one command at release
time is not worth it — `npm i -g spectacle-docs` on the host, then
`make.phar docs`.

## Relationship to the FrontAccounting stack

The sibling stack at `<frontaccounting>/docker/fa` serves a FrontAccounting
*checkout* and runs FA's own `modules/tests` suite. This one serves a
FrontAccounting *clone in an image* and runs this module's suite. They share a
design and nothing else — different containers, different volumes, different
ports. Run both at once if you want.
