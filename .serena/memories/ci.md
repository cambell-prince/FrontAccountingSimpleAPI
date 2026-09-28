# CI (`tools/ci.sh`, the FrontAccounting CI image)

Since 2026-09-28. The module's own `docker/` stack (`docker/fa-api`) is gone —
this now runs in the shared FrontAccounting CI package, `docker/ci` in
[cambell-prince/frontaccounting](https://github.com/cambell-prince/frontaccounting)
(`master-cp`): a prebuilt image with FrontAccounting, PHP, Apache and MariaDB in
one container, plus `plugin-test.sh` (local/driver) and the reusable
`.github/workflows/plugin-test.yml` (CI). api is not a FrontAccounting
extension (its `hooks.php` is empty), so every run passes `--name api
--no-activate` / `activate: false`.

- Locally, with that repository checked out beside this one:

      ../frontaccounting/docker/ci/plugin-test.sh --name api --no-activate \
          --setup 'composer install --no-interaction --no-progress' \
          . -- sh tools/ci.sh

- `tools/ci.sh` runs inside the image: lint, phpcs (advisory), analyze, then
  phpunit (with a `curl` probe of `/modules/api/category/` first, so a dead
  server shows up before phpunit's own error does). It requires `$FA_URL`
  (set by the image) and delegates to the same composer scripts as before —
  see [[tech_stack]] and [[suggested_commands]].
- The image serves FrontAccounting on `FA_URL=http://localhost` (not
  `:8000`); `tests/TestEnvironment.php` reads `FA_URL`, falling back to
  `http://localhost:8000` for a bare `phpunit` run outside the image.
- `.github/workflows/ci.yml` calls the shared reusable workflow with a
  `fa: [upstream, cp]` x `php: ['7.4', '8.3']` matrix — `upstream` is
  FrontAccountingERP/FA master, `cp` is this fork's `master-cp`.
- `docker/fa-api` needing its exec bit (`core.fileMode=false`) no longer
  applies — there is no script of that name in this repo. The same is true
  of `tools/ci.sh`, though: check `git ls-files -s tools/ci.sh` shows mode
  100755 after any change to it.

## Diagnosing a failure

The image logs FrontAccounting errors (`tmp/errors.log`) and Apache's error
log instead of displaying them, and prints their tails on failure. FA turns a
database error into `E_USER_ERROR` and `output_html()` swallows it, so a
broken endpoint answers **200 with FA page HTML or an empty body**, never a
500.

## The suite passes

31 tests, 362 assertions on PHP 7.4, against both `upstream` and `cp` images,
since 2026-09-28.

Still true and worth knowing: on 7.4, Slim 2.6.3's `get_magic_quotes_gpc()`
deprecation becomes an `ErrorException` (Slim's handler rethrows anything in
`error_reporting()`), killing every POST/PUT whenever FA's `$go_debug` is on.

Full detail in `docker/ci/README.md` in the frontaccounting repository.
