# Tech Stack

- **PHP** — 7.4 and 8.3, both green and both gating CI. `composer.json` sets
  `config.platform.php` to 7.4.33 so one lock file serves both. Two things make
  8.x work: a `get_magic_quotes_gpc()` shim in `index.php` (Slim 2 still calls
  it; removed in PHP 8.0) and opening the database connection in
  `session-custom.inc` before FA's `init()` runs.
- **Slim 2.6.3** (`slim/slim ~2.6.3`) — the only runtime dependency. Slim 2
  idioms everywhere: `new \Slim\Slim(...)`, `\Slim\Slim::getInstance('SASYS')`,
  `$app->hook('slim.before', ...)`, `$rest->container->singleton(...)`,
  `$app->halt()`, `:param` routes. Do not copy Slim 3/4 patterns into master.
- **Composer** with PSR-4 `FAAPI\` → `src/`, and **composer scripts as the task
  runner**: `test`, `lint`, `cs:check`, `cs:fix`, `analyze`, `quality`, `ci`.
  `vendor/` is gitignored and owned by the docker stack, which reinstalls it
  from the lock on every `up`.
- **Dev deps**: `phpunit/phpunit ^9.6`, `guzzlehttp/guzzle ^7.5`,
  `squizlabs/php_codesniffer ^3.7`, `phpstan/phpstan ^1.10`. The 4.2/6.3
  versions were both blocked by composer security advisories and neither
  installs on PHP 8.
- **swagger-php** lives in `build/swagger/`, not `require-dev`: it needs
  `doctrine/annotations ^1.4`, which caps at PHP 7, and keeping it in the main
  set would stop the suite running on PHP 8. `make.phar docs-json` installs it
  on demand. It reads `@SWG\*` (v2 syntax); moving to `@OA\*` and swagger-php 4
  would remove the split and switch the output to OpenAPI 3.
- **phpcs** — PSR-2 over `src/` and `tests/` via `phpcs.xml`. Not clean (125
  errors), so deliberately not in `quality`/`ci`; reported but not gating.
- **PHPStan** — `phpstan.neon`, level 0 and clean. It resolves FrontAccounting's
  symbols with `scanDirectories: ../../includes` etc. plus
  `fileExtensions: [php, inc]`, which is the part that makes FA's `.inc`
  codebase visible at all. `scanFiles` covers the constants and `api_*` helpers
  that are included at runtime rather than autoloaded.
- **phpmake** (saygoweb/phpmake) — `makefile.json` holds the multi-step builds
  composer scripts fit badly: `docs-json`, `docs`, `package`, `clean`. Installed
  as `make.phar` on PATH; built into the docker image.
- **MySQL/MariaDB** via FA's db layer; this module opens no connections.
- **No node.** gulp, `package.json` and `.travis.yml` were removed on
  2026-09-20. The one thing that still wants node is `spectacle`, for rendering
  `swagger.json` into the `gh-pages` HTML, and it is expected globally on the
  host at release time.
- **CI** — `.github/workflows/ci.yml`, driving `docker/fa-api` (see [[docker]]).
- **Xdebug** — `.vscode/launch.json` listens on 9003.
