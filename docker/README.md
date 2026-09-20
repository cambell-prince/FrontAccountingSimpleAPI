# Docker test stack

A throwaway FrontAccounting install with this module plugged into it — Apache +
mod_php + MariaDB — for developing the API and running its test suite without a
FrontAccounting checkout, a PHP, or a database on your own machine.

    docker/fa-api init      # pick host ports that are free here
    docker/fa-api up        # build, boot, seed, install composer deps
    docker/fa-api test      # run the PHPUnit suite
    docker/fa-api lint      # php -l, then phpcs PSR-2

`up` prints the URLs. `docker/fa-api help` lists every command.

The same CLI is what `.github/workflows/ci.yml` runs, so `docker/fa-api ci`
locally is the build that will run on a push.

## How it fits together

This module is not an application. It is an extension that only runs from
`modules/api` inside a FrontAccounting tree, which is why `.travis.yml` had to
clone FrontAccounting and rsync the module into it before it could test
anything.

The stack does the same thing, pinned and repeatable:

| | |
| --- | --- |
| FrontAccounting | cloned into the image at build time from `FA_REPO` / `FA_REF` — upstream `master` by default |
| this checkout | bind-mounted at `/var/www/html/modules/api`, so an edit is live on the next request |
| `config.php`, `config_db.php`, the two `installed_extensions.php` and `lang/installed_languages.inc` | written by the entrypoint on every start, into the image's FA tree |
| `vendor/` | installed by `up` from `composer.lock`, into your checkout, owned by you |

Nothing is written into your checkout except `vendor/`. There is no
FrontAccounting checkout to keep in step and no generated config of yours to
overwrite — the contrast with `gulp env-files`, which copies fixture config
files over whatever is in the working tree.

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

`test` is the fixture the gulpfile's `env-db` task loads and the one the suite's
`X-COMPANY: 0` / `X-USER: test` / `X-PASSWORD: test` credentials come from.
`docker/fa-api db dump` writes a gzipped dump back out.

## The suite does not currently pass

`docker/fa-api test` on PHP 7.4 against FrontAccounting master reports **19
tests, 164 assertions, 4 failures and 1 error**. All five predate this stack and
none of them are caused by it. They are worth knowing before you read a red run
as something you just broke.

**Four are the same thing:** FrontAccounting forces `SET sql_mode =
'STRICT_ALL_TABLES'` on every connection (`SQL_MODE` in
`includes/db/connect_db_mysqli.inc`, "prevents SQL injection with silent field
content truncation"), and this module hands `''` to integer and date columns:

| test | rejected INSERT |
| --- | --- |
| `CustomerTest` | `0_cust_branch.salesman` ← `''` — `Customers::post()` defaults `salesman` and `area` to `''` |
| `DimensionTest` | `0_dimensions.type_` ← `''` |
| `StockAdjustTest` | `0_journal.event_date` ← `''` |
| `SalesTest` | fails in `createCustomer`, so the same one |

Setting the server's `--sql-mode` does not help: FA overrides it per connection.
The fix belongs in the module — pass `0` and a real date — not here.

What you see when it happens is an endpoint answering **200 with a fragment of
FrontAccounting page HTML** instead of 201 with JSON, because FA turns a
database error into `E_USER_ERROR`, and `output_html()` swallows the rest.
`docker/fa-api logs errors` shows the actual `DATABASE ERROR` line. That is the
first place to look whenever a response is HTML or empty.

**The fifth**, `JournalTest`, expects reference `1` and gets `2` — a test that
assumes a reference counter starting where a clean fixture leaves it.

### PHP 7.4 and POST

Separately: Slim 2.6.3 calls `get_magic_quotes_gpc()` in
`Slim\Http\Util::stripSlashesIfMagicQuotes()`, which PHP 7.4 deprecates. Slim
installs an error handler that turns anything in `error_reporting()` into an
`ErrorException`, so with FrontAccounting's debug mode on (`$go_debug` in
`config.php`) **every POST and PUT dies before reaching its route** with "Slim
Application Error". With debug off the deprecation is below the reporting level
and requests work. `.travis.yml` tested 5.6 and 7.0, where the function is not
deprecated, so this is new ground. It is one more thing the Slim 4 port on
`feature/php8` removes.

## Continuous integration

`.github/workflows/ci.yml` runs `up --build`, `lint` and `test` through this
same CLI, on two PHP versions:

- **7.4** — the gate. The newest either this module or FrontAccounting 2.4.x is
  written for.
- **8.3** — `continue-on-error`, a scoreboard for the Slim 4 / PHP 8 port on
  `feature/php8`. Slim 2 and phpunit 4.2 cannot run there; saying so in red on
  every unrelated pull request would only teach people to ignore it.

`lint` gates on `php -l` and reports phpcs without failing — there are 125 PSR-2
errors in `src/` and `tests/` today, 304 of which `phpcbf` can fix
automatically. `docker/fa-api lint --strict` makes them count, once someone has
done that.

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

The gulp tasks. `gulp test` and `gulp env-db` assume the `_frontaccounting/`
layout `.travis.yml` built and need node 10 for gulp 3; the documentation build
(`gulp doc`) additionally wants a global `spectacle`. Swagger generation and
packaging still run on the host.

## Relationship to the FrontAccounting stack

The sibling stack at `<frontaccounting>/docker/fa` serves a FrontAccounting
*checkout* and runs FA's own `modules/tests` suite. This one serves a
FrontAccounting *clone in an image* and runs this module's suite. They share a
design and nothing else — different containers, different volumes, different
ports. Run both at once if you want.
