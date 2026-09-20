# Docker stack (`docker/`, CLI `docker/fa-api`)

Added 2026-09-20. The practical way to run this module and its suite; the gulp
tasks and `.travis.yml` layout are legacy. Modelled on `<FA>/docker/fa` but
solves a different problem: FrontAccounting is **cloned into the image** at
build time (`FA_REPO`/`FA_REF` build args, upstream master by default) and this
checkout is bind-mounted at `/var/www/html/modules/api`. No FA checkout needed,
so it works identically on GitHub Actions.

- `fa-api init` (free ports) → `up` (build, seed, `composer install`) → `test` /
  `lint`. `ci` = what `.github/workflows/ci.yml` runs.
- Apache listens on **8000 inside the container** because
  `tests/TestEnvironment.php` hardcodes `http://localhost:8000` and phpunit runs
  there. Host port is `HTTP_PORT`, default 8090.
- FA's five gitignored config files are written by the entrypoint on every
  start, into the image's FA tree. Nothing but `vendor/` is written into the
  checkout.
- `up` always runs `composer install`: vendor/ left over from another branch
  (Slim 4 from `feature/php8` under master's Slim 2) fails as a swallowed fatal
  — 200 with an empty body. See [[php8_migration]].
- CI matrix: 7.4 gating, 8.3 `continue-on-error` as the scoreboard for
  [[php8_migration]]. `lint` gates on `php -l`, reports phpcs (125 PSR-2 errors
  in src/ + tests/ as of writing, mostly phpcbf-fixable).
- `docker/fa-api` needs its exec bit in the index — `core.fileMode=false` here.

## Diagnosing a failure

`docker/fa-api logs errors` (FrontAccounting's `tmp/errors.log`) first. FA turns
a database error into `E_USER_ERROR` and `output_html()` swallows it, so a
broken endpoint answers **200 with FA page HTML or an empty body**, never a 500.

## The suite does not pass (pre-existing, not the stack)

19 tests, 164 assertions, 4 failures + 1 error on PHP 7.4 against FA master.
Four are one cause: FA sets `SET sql_mode = 'STRICT_ALL_TABLES'` per connection
(`SQL_MODE` in `includes/db/connect_db_mysqli.inc`), and this module passes `''`
to int/date columns — `0_cust_branch.salesman` (Customer, and Sales via
`createCustomer`), `0_dimensions.type_`, `0_journal.event_date` (StockAdjust).
Setting the server's `--sql-mode` does nothing; FA overrides it per connection.
The fifth, `JournalTest`, assumes a reference counter value.

Also on 7.4: Slim 2.6.3's `get_magic_quotes_gpc()` deprecation becomes an
`ErrorException` (Slim's error handler rethrows anything in `error_reporting()`),
killing every POST/PUT whenever FA's `$go_debug` is on. Travis tested 5.6/7.0,
where it is not deprecated.

Full detail in `docker/README.md`.
