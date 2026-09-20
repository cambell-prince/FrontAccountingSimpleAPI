# Tech Stack

- **PHP** — code targets 5.6/7.0 (Travis matrix). Local CLI is 8.3, which this code does *not*
  run on unmodified; see `mem:php8_migration`.
- **Slim 2.6.3** (`slim/slim ~2.6.3`) — the only runtime dependency. Slim 2 idioms everywhere:
  `new \Slim\Slim(...)`, `\Slim\Slim::getInstance('SASYS')`, `$app->hook('slim.before', ...)`,
  `$rest->container->singleton(...)`, `$app->halt()`, `:param` route placeholders.
  Do not copy Slim 3/4 patterns into `master`.
- **Composer** with PSR-4 `FAAPI\` → `src/`. `vendor/` is gitignored; `vendor/autoload.php` is
  included by `index.php`. Note the checked-in `vendor/` on disk may have been installed from the
  `feature/php8` lock and not match `master`'s `composer.lock`.
- **Dev deps**: `phpunit/phpunit ~4.2.6` (so tests use `PHPUnit_Framework_TestCase`, not the
  namespaced class), `guzzlehttp/guzzle ~6.3` (test HTTP client),
  `zircote/swagger-php ^2.0` (`@SWG\*` annotations, v2 syntax — not OpenAPI 3 `@OA\*`).
- **MySQL/MariaDB** via FA core's db layer; this module opens no connections of its own.
- **Node/npm** — build tooling only. `gulp ~3.9` (needs an old node, e.g. nvm `lts/dubnium`;
  gulp 3 will not run on modern node), plus `gulp-sequence`, `lodash.template`, `async`.
  `package.json` is named "frontaccounting-wrapper" and is shared lineage with the FA repo's.
- **Docs**: swagger-php generates `swagger.json`; `spectacle` (expected **globally** installed,
  `npm i -g spectacle-docs`) renders static HTML into `public/` (gitignored) for `gh-pages`.
- **Travis CI** (`.travis.yml`, legacy) — clones FA master into `_frontaccounting`, rsyncs this
  tree into `_frontaccounting/modules/api/`, starts `php -S localhost:8000`, runs `gulp test-travis`.
- **Xdebug**: `.vscode/launch.json` listens on port 9003.
