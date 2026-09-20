# Testing

`phpunit.xml` → testsuite = the whole `tests/` directory. PHPUnit 4, so tests extend
`PHPUnit_Framework_TestCase` (no namespace) and files are named `<Area>_Test.php` with class
`<Area>Test`.

The supported way to run them is the docker stack (`mem:docker`), which supplies
the FrontAccounting tree, the fixture database and the server in one command.
Everything below is what it automates, and what to do without it.

**These are HTTP integration tests, not unit tests.** Each one drives a Guzzle client against
`http://localhost:8000` (`TestEnvironment::client()`) hitting `/modules/api/...`, with the
`X-COMPANY: 0` / `X-USER: test` / `X-PASSWORD: test` headers from `TestEnvironment::headers()`.
Nothing is mocked; a **running PHP server and a populated `fa_test` database are prerequisites**
(`sh build-startServer.sh`, `npx gulp env-db`). A failing suite usually means the server is down,
`_frontaccounting` is shadowing `FA_ROOT` (see `mem:core`), or the db was not reloaded.

## Harness

| file | role |
|---|---|
| `tests/TestConfig.php` | resolves `ROOT_PATH`/`SRC_PATH`/`API_PATH`/`TEST_PATH`, same `_frontaccounting`-or-`../../..` rule as `config_api.php` |
| `tests/TestEnvironment.php` | Guzzle client, auth headers, fixture factories (`createCustomer`, `createItem`, `createJournal`), `createId()` = `date('YmdHis')`, `cleanTable`/`cleanBanking`, and an in-process FA bootstrap (`isGoodToGo()`) that asserts the db is `fa_test` |
| `tests/Crud_Base.php` | abstract CRUD suite: constructor takes `($url, $keyProperty, $postData, $putData = null)`; `testCRUD_Ok()` walks list → post → get → put → get → delete → list. Subclasses only pass data and may override `fixExpectedType`, `removeKeyProperty`, `checkCountInitial`, `checkGetAfterPost/Put`. `$this->method` selects `Crud_Base::FORM_DATA` (default) or `::JSON` |

Prefer extending `Crud_Base` for a new resource; write a standalone `*_Test.php` (like
`Sales_Test.php`, `Journal_Test.php`) only for multi-step or non-CRUD flows.

## Fixtures

`tests/data/` holds `fa_test.sql.gz` (+ a 2.3 copy), `config.php`, `config_db.php`,
`installed_extensions.php`. The db connection is hard-coded: host `localhost`, db `fa_test`,
user `travis` with an **empty password**, prefix `0_`, company 0. `gulp env-files` copies these
over `_frontaccounting/` — that is the CI layout only; in a normal FA checkout it would clobber
the real config, so do not run it there.

Tests create records with timestamp-derived refs and mostly clean up after themselves; a crashed
run can leave rows behind — reload with `npx gulp env-db`.
