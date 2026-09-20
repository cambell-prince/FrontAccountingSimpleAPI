# Tech Stack

- **PHP** — 7.4 is the version the suite is green on and what CI gates; the code
  still avoids 8-only syntax. Local CLI is 8.3, which master does not run on
  (see [[php8_migration]]).
- **Slim 2.6.3** (`slim/slim ~2.6.3`) — the only runtime dependency. Slim 2
  idioms everywhere: `new \Slim\Slim(...)`, `\Slim\Slim::getInstance('SASYS')`,
  `$app->hook('slim.before', ...)`, `$rest->container->singleton(...)`,
  `$app->halt()`, `:param` routes. Do not copy Slim 3/4 patterns into master.
- **Composer** with PSR-4 `FAAPI\` → `src/`, and **composer scripts as the task
  runner**: `test`, `lint`, `cs:check`, `cs:fix`, `analyze`, `quality`, `ci`.
  `vendor/` is gitignored and owned by the docker stack, which reinstalls it
  from the lock on every `up`.
- **Dev deps**: `phpunit/phpunit ~4.2.6` (so tests extend
  `PHPUnit_Framework_TestCase`), `guzzlehttp/guzzle ~6.3` (the suite's HTTP
  client), `zircote/swagger-php ^2.0` (`@SWG\*`, v2 syntax — not OpenAPI 3
  `@OA\*`), `squizlabs/php_codesniffer ^3.7`, `phpstan/phpstan ^1.10`.
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
