# FrontAccounting Simple API — Core

REST API delivered as a **FrontAccounting extension** (`modules/api`). Slim 2 micro-framework app:
all routing in `index.php`, thin controller classes in `src/` (namespace `FAAPI`, PSR-4, composer
autoload) that call procedural helpers in the root `*.inc` files and FA core's `*_db.inc` functions.
Responses are JSON emitted via the `api_*` helpers in `util.php`. Version line "2.4-1.7" (FA 2.4).

Remotes: `origin` = cambell-prince/FrontAccountingSimpleAPI (the working fork),
`upstream` = andresamayadiaz/FrontAccountingSimpleAPI. `gh-pages` holds the published docs.

## Relationship to the FrontAccounting repo

Checked out at `<FA>/modules/api`; FA root is `../../`. FA's `.gitignore` excludes `modules/*`,
so this is an independent repo — never commit it from the FA side.

**The FA checkout is a separate Serena project** ("frontaccounting") with its own memory set at
`../../.serena/memories/` — `core`, `architecture`, `conventions`, `modules`, `testing`,
`branching`, `tech_stack`, `suggested_commands`, `task_completion`. Read those (via `cat`, they
are not reachable through this project's `read_memory`) for anything about FA core: the
session/hooks bootstrap, `db_query`/`db_escape`/`TB_PREF` data access, `ST_*`/`SA_*` constants,
the `*_db.inc` API surface this module wraps, and cp's branch lines.

## Top-level source map

| path | role |
|---|---|
| `index.php` | bootstrap + **every route**; Slim `$rest`, DI singletons, Swagger `@SWG` root |
| `util.php` | `api_login`, `api_response`/`api_success_response`/`api_create_response`/`api_error`, `api_validate*`, `api_check` |
| `config_api.php` | defines `FA_ROOT` and `API_ROOT` |
| `src/*.php` | one `FAAPI\<Name>` controller per resource, methods `get/getById/post/put/delete` |
| `sales.inc`, `inventory.inc`, `assets.inc` | procedural endpoint bodies included on demand |
| `session-custom.inc`, `session_utils.inc` | **forked copies of FA core's session files**, adapted for headless API use |
| `sync_db.inc`, `salesquotes.inc` | legacy, nothing includes them — dead code, do not extend |
| `hooks.php` | tracked but **empty** — the module registers no FA menu/security hooks |
| `swagger.json` | generated from `@SWG` annotations, committed; source of the published docs |
| `tests/` | HTTP integration tests — see `mem:testing` |

Resource → class (all in `src/`): inventory→`Inventory`, locations→`InventoryLocations`,
category→`Category`, taxtypes→`TaxTypes`, taxgroups→`TaxGroups`, customers→`Customers`,
suppliers→`Suppliers`, bankaccounts→`BankAccounts`, glaccounts→`GLAccounts`,
glquery→`GLQueries`, currencies→`Currencies`, exchangerates→`ExchangeRates`,
itemcosts→`InventoryCosts`, sales→`Sales`, dimensions→`Dimensions`, journal→`Journal`.
`assets/*` routes are procedural and only registered when `modules/asset_register` exists.

## Invariants

- `config_api.php` resolves the FA root as `./_frontaccounting` **if that path exists**, else
  `../..`. CI clones FA into `_frontaccounting/`; locally the module sits inside a real FA tree.
  **Gotcha:** a stray *empty file* named `_frontaccounting` is present and wins that check, so
  `FA_ROOT` resolves to a non-directory and every FA include fails. Delete it before running
  anything locally. It is gitignored, never commit it.
- Auth is per-request via the `X-COMPANY` / `X-USER` / `X-PASSWORD` headers; `api_login()`
  installs a `slim.before` hook that calls `$_SESSION["wa_current_user"]->login(...)` and halts
  403 on failure. There are no tokens and no sessions to reuse.
- `$page_security = 'SA_API'` is set before including `session-custom.inc`.
- Every route closure is `function (...) use ($rest)` and forwards `$rest` as the first argument
  to the controller method; controllers pull input with `$rest->request()`.
- `.htaccess` rewrites all non-file requests to `index.php`; URLs are
  `/modules/api/<resource>/...` and **trailing slashes are significant** in Slim 2 routes.
- `JsonToFormData` middleware (defined in `index.php`) copies a JSON body into
  `slim.request.form_hash`, so handlers read JSON and form posts identically. It must be added
  *before* `\Slim\Middleware\ContentTypes` — the order of the `add()` calls is inverted at runtime.

## Further reading

- Slim/composer/php versions and pins, and the composer/phpmake toolchain: `mem:tech_stack`
- Running the server, tests, docs and packaging: `mem:suggested_commands`
- Code style, the controller/route/Swagger-annotation pattern: `mem:conventions`
- Test harness, fixture db and the running-server requirement: `mem:testing`
- The docker stack (`docker/fa-api`) that builds FA around the module, and CI: `mem:docker`
- What to verify before calling a change done: `mem:task_completion`
- The in-flight Slim 2 → Slim 4 / PHP 8 port on `feature/php8`: `mem:php8_migration`
