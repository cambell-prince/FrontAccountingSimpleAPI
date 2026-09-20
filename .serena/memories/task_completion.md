# Task Completion Checklist

No linter, formatter or type checker is configured. Do not invent one.

1. `php -l <file>` on every file touched. The local CLI is 8.3 while `master` targets 5.6/7.0, so
   a clean lint proves little — re-read for PHP-8-only syntax before declaring done.
2. Keep PSR-2 in `src/` and `tests/` by hand (4 spaces, one class per file).
3. If `@SWG` annotations changed, regenerate and commit `swagger.json`
   (`npx gulp doc-swagger-json`). It is a tracked build product and is expected to stay in sync.
4. Run `docker/fa-api lint` and `docker/fa-api test` (`mem:docker`). Five tests fail
   before you touch anything — check the failure list in `mem:docker` before blaming
   your change, and if the tests cannot be run here, say so rather than implying they passed.
5. Add a dated bullet at the top of `CHANGELOG.md` for any behaviour change; bump the version in
   both `gulpfile.js` (`version`/`release`) and the `@SWG\Info` block in `index.php` for a release.
6. `git status` — `vendor/`, `node_modules/`, `_frontaccounting`, `public/`, `*.zip`, `*.tgz`
   must stay untracked. Never commit this module from the parent FrontAccounting repo.
