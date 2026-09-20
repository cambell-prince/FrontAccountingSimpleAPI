# Conventions

**PSR-2** for `src/` and `tests/` (4 spaces, one class per file, braces on their own line for
classes/methods). The root `*.inc`/`*.php` files are older procedural code and the forked FA
session files use **tabs** — match the file you are in, not a global rule.

## Adding an endpoint

1. Controller in `src/<Resource>.php`: `namespace FAAPI;` then, if FA data access is needed,
   `$path_to_root = "../..";` followed by `include_once($path_to_root . "/<area>/includes/db/*.inc");`
   at file scope *before* the class (see `src/GLQueries.php`, `src/Customers.php`).
   Class is plain — no base class, no interface, no constructor.
2. Methods take `$rest` first, then route params: `get($rest)`, `getById($rest, $id)`,
   `post($rest)`, `put($rest, $id)`, `delete($rest, $id)`. Input: `$req = $rest->request();`
   then `$req->get('x')` (query) / `$req->post('x')` / `$req->params('x')`.
3. Register in `index.php`: a `use FAAPI\<Name>;` at the top, then
   `$rest->container->singleton('<name>', function () { return new <Name>(); });`
   followed by `$rest->group('/<name>', function () use ($rest) { ... });` with one closure per
   verb delegating to `$rest-><name>-><method>($rest, ...)`. Keep the banner comment style
   (`// ---------- <Resource> ----------`) that brackets each group.
4. Annotate with swagger-php **v2** `@SWG\*` docblocks (`@SWG\Definition` for the model,
   `@SWG\Get/Post/Put/Delete` with `path`, `tags`, `operationId`, `@SWG\Response`), then
   regenerate `swagger.json` (`mem:suggested_commands`).
5. Add a test (`mem:testing`) and a dated bullet at the top of `CHANGELOG.md`.

## Responses and errors

Never `echo`/`json_encode` directly — use `util.php`:
- `api_success_response($body)` (200), `api_create_response($body)` (201) — arrays are
  json-encoded for you.
- `api_error($code, $msg)` halts with `{"code":…,"success":0,"msg":…}`.
- `api_validate($property, $model, 412, 'api_validate_required'|'api_validate_date')` for input
  checks; `api_check($property, $model, $default)` fills defaults in place;
  `api_ensureAssociativeArray()` strips integer keys from posted arrays.
- Dates crossing the API boundary are **ISO 8601 `Y-m-d`**; convert to FA's user date format with
  core's `date_functions.inc` helpers before calling `*_db.inc`.

## Paging

`RESULTS_PER_PAGE` is defined in `index.php`. The convention is 1-based `?page=`; handlers do
`$from = --$page * RESULTS_PER_PAGE;` and call the `*_all($from)` variant, or the unpaged variant
when `page` is absent.

## Data access

All SQL goes through FA core's `*_db.inc` functions, with `TB_PREF` and `db_escape()` — see the
FA project's `conventions` memory (`../../.serena/memories/conventions.md`). This module does not
own any tables and adds no migrations.

## Git

Topic branches `feature/X`, `bug/X`, `fix/X` with the issue number appended where one exists
(`feature/TrialBalance_46`, `bug/JournalPost_47`), merged back with a merge commit. Commit
subjects are `Fix #<n> <what>` or `<branch-name>: <what>`. `master-cp` also exists on origin,
mirroring the FA repo's convention.
