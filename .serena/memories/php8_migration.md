# PHP 8 support

Done as of 2026-09-21: the module runs on PHP 8.3 and CI gates on 7.4 and 8.3
alike. Slim 2.6.3 was kept; the Slim 4 port on `feature/php8` was not needed and
that branch is stale — it predates the docker stack, the composer tooling and
everything in [[docker]].

What it took, all small:

- A `get_magic_quotes_gpc()` shim in `index.php`. Slim 2 calls it from
  `Slim\Http\Util::stripSlashesIfMagicQuotes()` on every form POST, and PHP 8.0
  removed it. Returning false is what PHP 8 guarantees anyway.
- Opening the database connection in `session-custom.inc` before
  `front_accounting->init()`, which on current FA queries company prefs and so
  reached `mysqli_query()` with a null connection.
- Dependency bumps; see [[tech_stack]].

## The trap worth remembering

**PHP 8.1 made mysqli throw by default.** FrontAccounting 2.4 is written for
mysqli returning false and handles errors itself, so a failing query that 7.4
swallowed — FA raising `E_USER_ERROR`, `output_html()` stripping it out, the
endpoint answering 200 — becomes an uncaught `mysqli_sql_exception` and a 500 on
8.1+. That is how the journal void bug was found: it voided on today's date, and
`add_audit_trail()` cannot store a null `fiscal_year` for a date outside any
fiscal year. The same class of bug is likely to be hiding elsewhere; running the
suite on 8.3 is what surfaces it.
