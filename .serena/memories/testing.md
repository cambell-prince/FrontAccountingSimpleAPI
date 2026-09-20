# Testing

`phpunit.xml` → testsuite = the whole `tests/` directory. Tests extend
`PHPUnit\Framework\TestCase` (no namespace of their own) and files are named `<Class>Test.php`,
one test class per file.

One class per file is not just PSR-1 tidiness here: when a file declares a class whose
name matches the file's, PHPUnit adds only that class and silently ignores the others.
`BankAccountsTest.php` holding `BankAccountsTest` + `BankAccountsOtherTest` would run the
first and drop the second without a word. Anything shared between two test classes goes in
a non-`*Test.php` file they both `require_once` (see `tests/CategoryData.php`).

The supported way to run them is the docker stack (`mem:docker`), which supplies
the FrontAccounting tree, the fixture database and the server in one command.
Everything below is what it automates, and what to do without it.

Green as of 2026-09-21: 23 tests, 268 assertions, on PHP 7.4 against FA master.

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

Prefer extending `Crud_Base` for a new resource; write a standalone `*Test.php` (like
`SalesTest.php`, `JournalTest.php`) only for multi-step or non-CRUD flows.

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
