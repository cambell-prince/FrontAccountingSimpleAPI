# Commands

## Docker stack — the normal path (`mem:docker`)

| command | effect |
|---|---|
| `docker/fa-api init` | write `docker/.env` with free host ports (once) |
| `docker/fa-api up` | build, boot, seed `fa_test`, `composer install` |
| `docker/fa-api test [args]` | PHPUnit against the running stack (`--filter X` to narrow) |
| `docker/fa-api lint [--strict]` | `php -l` sweep, then phpcs PSR-2 |
| `docker/fa-api ci` | up --build + lint + test, as GitHub Actions runs it |
| `docker/fa-api logs errors` | FA's `tmp/errors.log` — first stop for a 200 with no JSON |
| `docker/fa-api db reset` | reload the fixture |

Everything below is the older host-based path, kept for reference.

Run from the module root (`<FA>/modules/api`). Gulp 3 needs an old node (`nvm use lts/dubnium`);
gulp is not global here, so use `npx gulp <task>`. `npx gulp tasks` greps the task list.

## Setup

| command | effect |
|---|---|
| `composer install` | runtime + dev deps into `vendor/` |
| `composer install --no-dev` | what `gulp package-vendor` does before packaging (wipes `vendor/` first) |
| `npm install` | gulp toolchain |
| `rm -f _frontaccounting` | remove the stray empty file that breaks `FA_ROOT` (see `mem:core`) |

## Server

`sh build-startServer.sh` — `php -S localhost:8000 -t ../..` (document root = the FA tree, so the
API is at `http://localhost:8000/modules/api/...`). The tests require this to be running.

Smoke test: `curl -H 'X-COMPANY: 0' -H 'X-USER: test' -H 'X-PASSWORD: test' \
  http://localhost:8000/modules/api/category/`

## Tests — read `mem:testing` first (`env-db` drops and reloads the `fa_test` database)

| command | effect |
|---|---|
| `npx gulp test` | `env-db` (reload `fa_test`) then PHPUnit |
| `npx gulp test-only` | PHPUnit only, no db reload |
| `npx gulp test-watch` | re-run `test` on php/inc changes |
| `npx gulp env-db` | `gunzip -c tests/data/fa_test.sql.gz \| mysql -u travis --password='' -D fa_test` |
| `npx gulp env-files` | copy `tests/data/*.php` into `_frontaccounting/` (CI layout only) |

`test-only` invokes phpunit through the **`_frontaccounting/modules/api/` path**, i.e. it only
works in the CI layout. In a normal FA checkout run phpunit directly:
`php vendor/bin/phpunit -c phpunit.xml` (add `--filter <TestName>` for one test).

## Docs

| command | effect |
|---|---|
| `npx gulp doc-swagger-json` | regenerate `swagger.json` from `@SWG` annotations in `src/` + `index.php` |
| `npx gulp doc-spectacle` | render `swagger.json` to static HTML in `public/` (needs global `spectacle`) |
| `npx gulp doc` / `doc-watch` | both / watch mode |

## Packaging (releases are zip/tgz uploads, not git clones)

`npx gulp package` → `package-zip` + `package-tar`, both depending on `package-vendor`
(`rm -rf vendor && composer install --no-dev`). Version/release strings are hard-coded in
`gulpfile.js` (`version: "2.4"`, `release: "-api.module.1.7"`) — bump them there and in the
`@SWG\Info` version in `index.php` together. Excludes come from `upload-exclude*.txt`.
