# Testing

`phpunit.xml` → testsuite = the whole `tests/` directory. PHPUnit 4, so tests extend
`PHPUnit_Framework_TestCase` (no namespace) and files are named `<Area>_Test.php` with class
`<Area>Test`.

The supported way to run them is the docker stack (`mem:docker`), which supplies
the FrontAccounting tree, the fixture database and the server in one command.
Everything below is what it automates, and what to do without it.

Green as of 2026-09-20: 19 tests, 216 assertions, on PHP 7.4 against FA master.

**These are HTTP integration tests, not unit tests.** Each one drives a Guzzle client against
`http://localhost:8000` (`TestEnvironment::client()`) hitting `/modules/api/...`, with the
`X-COMPANY: 0` / `X-USER: test` / `X-PASSWORD: test` headers from `TestEnvironment::headers()`.
Nothing is mocked; a **running PHP server and a populated `fa_test` database are prerequisites**,
which `docker/fa-api up` provides. A failing suite usually means the stack is not up,
`_frontaccounting` is shadowing `FA_ROOT` (see `mem:core`), or the db was not reloaded
(`docker/fa-api db reset`).

## Harness

| file | role |
|---|---|
| `tests/TestConfig.php` | resolves `ROOT_PATH`/`SRC_PATH`/`API_PATH`/`TEST_PATH`, same `_frontaccounting`-or-`../../..` rule as `config_api.php` |
| `tests/TestEnvironment.php` | Guzzle client, auth headers, fixture factories (`createCustomer`, `createItem`, `createJournal`), `createId()` = `date('YmdHis')`, `cleanTable`/`cleanBanking`, and an in-process FA bootstrap (`isGoodToGo()`) that asserts the db is `fa_test` |
| `tests/Crud_Base.php` | abstract CRUD suite: constructor takes `($url, $keyProperty, $postData, $putData = null)`; `testCRUD_Ok()` walks list → post → get → put → get → delete → list. Subclasses only pass data and may override `fixExpectedType`, `removeKeyProperty`, `checkCountInitial`, `checkGetAfterPost/Put`. `$this->method` selects `Crud_Base::FORM_DATA` (default) or `::JSON` |

Prefer extending `Crud_Base` for a new resource; write a standalone `*_Test.php` (like
`Sales_Test.php`, `Journal_Test.php`) only for multi-step or non-CRUD flows.

## Fixtures

`tests/data/` holds `fa_test.sql.gz` (+ a 2.3 copy) and FrontAccounting's `config.php`,
`config_db.php` and `installed_extensions.php`. Only the dumps matter now: the docker stack
generates FA's config itself and loads the dump. The config fixtures are left as documentation of
a working install, and phpcs excludes `tests/data/`.

**Two tests depend on execution order.** `SalesTest` posts a hard-coded `customer_id=2`, so it
passes only in a full run; `JournalTest` posts its own unique reference precisely so it does not.
Reproduce CI with a full `docker/fa-api test`, not `--filter`.

Tests create records with timestamp-derived refs and mostly clean up after themselves; a crashed
run can leave rows behind — reload with `docker/fa-api db reset`.
