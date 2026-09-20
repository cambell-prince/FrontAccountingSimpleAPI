# PHP 8 / Slim 4 migration (branch `feature/php8`)

Active, **unfinished** line of work; as of 2026-09-20 a single `wip` commit (42cb793) ahead of
`master`, and the working tree is usually checked out on `master`. The local PHP CLI is 8.3, so
`master` cannot actually be run here — this branch is the path to a runnable module.

What the wip commit changes:
- `composer.json`: `slim/slim ~2.6.3` → `~4.0.0` plus `slim/psr7 ^1.5` (lock + a
  `package-lock.json` regenerated). The installed `vendor/` on disk matches **this** lock
  (guzzle 6.5.8, doctrine/annotations 1.14), not `master`'s.
- `index.php`: `new \Slim\Slim(...)` / `setName('SASYS')` commented out, replaced by
  `AppFactory::create()`; PSR-7 `Request`/`Response` imports added.
- `util.php`: `api_login()` now takes the `Slim\App` instead of `\Slim\Slim::getInstance('SASYS')`.
- Removed PHP-8-incompatible leftovers from the forked FA session files: `strip_quotes()`
  (used `get_magic_quotes_gpc()`) in `session_utils.inc` and its call on `$_POST` in
  `session-custom.inc`.

Known remaining work — Slim 4 has no equivalent of the Slim 2 idioms this codebase is built on:
- `$app->hook('slim.before', ...)` → PSR-15 middleware (the auth hook in `api_login`).
- `$rest->container->singleton(...)` → a PSR-11 container (`AppFactory::setContainer`), and every
  route closure's `$rest-><name>-><method>()` call goes with it.
- `$rest->request()` / `$req->get()` / `$req->post()` inside all 16 `src/` controllers →
  `$request->getQueryParams()` / `getParsedBody()`; handlers must **return** a `Response` instead
  of writing through `api_response()`'s `$app->response()->body()`.
- `$app->halt()` (used by `api_error`) has no Slim 4 counterpart.
- The `JsonToFormData` middleware extends `\Slim\Middleware`; Slim 4 parses JSON bodies itself.
- Route placeholders change from `:id` to `{id}`, and `$rest->run()` → `$rest->run()` still, but
  after `addRoutingMiddleware()` / `addErrorMiddleware()`.
- PHPUnit is still pinned `~4.2.6`, which does not run on PHP 8 — the test suite needs a bump
  (and `PHPUnit_Framework_TestCase` → `PHPUnit\Framework\TestCase`) before it can verify any of this.

Rewriting the controllers against a Slim-version-agnostic wrapper is the obvious way to avoid
touching all 16 classes twice, but no such decision is recorded — confirm with the user.
