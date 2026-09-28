# Task Completion Checklist

phpcs (PSR-2, advisory) and PHPStan (level 0, gating) are configured; there is no formatter.

1. `composer lint` (`php -l` over everything). The local CLI is 8.3 while master runs on
   7.4, so a clean lint proves little — re-read for PHP-8-only syntax before declaring done.
2. Keep PSR-2 in `src/` and `tests/` by hand (4 spaces, one class per file).
3. If `@SWG` annotations changed, regenerate and commit `swagger.json`
   (`make.phar docs-json`). It is a tracked build product and is expected
   to stay in sync — it had drifted for years before 2026-09-20.
4. Run `tools/ci.sh` in the shared FrontAccounting CI image (`mem:ci`) — lint,
   phpcs (advisory), analyze and phpunit in one command. The suite is green,
   so a failure is yours. If it cannot be run here, say so rather than implying it passed.
5. Add a dated bullet at the top of `CHANGELOG.md` for any behaviour change; bump the version in
   both `makefile.json` (`version`/`release`) and the `@SWG\Info` block in `index.php` for a release.
6. `git status` — `vendor/`, `_frontaccounting`, `public/`, `*.zip` and `*.tgz`
   must stay untracked. Never commit this module from the parent
   FrontAccounting repo.
