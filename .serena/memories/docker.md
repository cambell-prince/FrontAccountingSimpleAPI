# Docker stack (`docker/`, CLI `docker/fa-api`)

Added 2026-09-20. The only way to run this module and its suite; gulp and Travis
were removed the same day. Modelled on `<FA>/docker/fa` but
solves a different problem: FrontAccounting is **cloned into the image** at
build time (`FA_REPO`/`FA_REF` build args, upstream master by default) and this
checkout is bind-mounted at `/var/www/html/modules/api`. No FA checkout needed,
so it works identically on GitHub Actions.

- `fa-api init` (free ports) → `up` (build, seed, `composer install`) → `test`,
  `lint`, `analyze`. `ci` = what `.github/workflows/ci.yml` runs. `fa-api make
  <target>` runs a phpmake target (`docs-json`, `package`); `make.phar` is in the
  image. Each command delegates to a composer script, so there is one definition
  of every task — see [[tech_stack]] and [[suggested_commands]].
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
  [[php8_migration]]. `lint` gates on `php -l` and reports phpcs without failing
  (125 PSR-2 errors, 77 auto-fixable); `analyze` gates on PHPStan level 0.
- `docker/fa-api` needs its exec bit in the index — `core.fileMode=false` here.

## Diagnosing a failure

`docker/fa-api logs errors` (FrontAccounting's `tmp/errors.log`) first. FA turns
a database error into `E_USER_ERROR` and `output_html()` swallows it, so a
broken endpoint answers **200 with FA page HTML or an empty body**, never a 500.

## The suite passes

19 tests, 216 assertions on PHP 7.4 against FA master, since 2026-09-20. It did
not before: FA connects with `SET sql_mode = 'STRICT_ALL_TABLES'` (`SQL_MODE` in
`includes/db/connect_db_mysqli.inc`) and the module passed `''` to integer and
date columns. Setting the server's `--sql-mode` does nothing — FA overrides it
per connection, so the fix had to be in the module.

Still true and worth knowing: on 7.4, Slim 2.6.3's `get_magic_quotes_gpc()`
deprecation becomes an `ErrorException` (Slim's handler rethrows anything in
`error_reporting()`), killing every POST/PUT whenever FA's `$go_debug` is on.

Full detail in `docker/README.md`.
