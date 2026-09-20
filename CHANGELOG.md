# CHANGELOG

#### September 2026

- 21 Sep: Fix credit notes (`trans_type` 11), which could not be posted at all.
  The cart was given a sales type id but nothing that hangs off it, and a credit
  note is the one sales document written straight to `debtor_trans` — an invoice
  goes by way of a sales order that FrontAccounting reads back, which fills the
  tax treatment in on the way. Under `sql_mode=STRICT_ALL_TABLES` the empty
  `tax_included` was rejected and the caller got a 200 carrying page HTML. The
  sales type is now applied through `set_sales_type()` when adding and when
  editing, so the tax treatment follows the type that was posted, and a sales
  type that does not exist is answered with a 400 instead of failing the write.
- 21 Sep: Run on PHP 8.3, and gate CI on both 7.4 and 8.3. Slim 2 still calls
  `get_magic_quotes_gpc()`, removed in PHP 8.0, and `session-custom.inc` let
  FrontAccounting's `init()` query the database before the API had opened a
  connection.
- 21 Sep: Fix voiding a journal, which used today's date. `add_audit_trail()`
  fills `fiscal_year` from a lookup on that date and cannot store null, so a
  void outside a fiscal year failed — silently on 7.4, and as a 500 on 8.1 and
  later where mysqli throws. It now voids on the transaction's own date, takes
  an optional `date`, and reports FrontAccounting's refusal instead of
  discarding it.
- 21 Sep: phpunit 4.2 -> ^9.6 and guzzle 6.3 -> ^7.5; both old versions are
  blocked by security advisories and neither installs on PHP 8. swagger-php
  moves to `build/swagger/`, because it pins `doctrine/annotations ^1.4`, which
  cannot be installed on PHP 8 — see `build/swagger/README.md`.

- 21 Sep: Add `POST /payments` to record a customer payment, optionally
  allocated to one document, and `DELETE /payments/{id}` to void one. Bad input
  is answered with 400 and a message rather than FrontAccounting's swallowed
  E_USER_ERROR, which reaches the caller as a 200 carrying page HTML.
- 21 Sep: Fix `GET /customers/{id}/branches/`, which inner joined `salesman` and
  `areas` and so never listed a branch created by `POST /customers` — those are
  created with both set to 0.

- 20 Sep: Run on PHP 7.4 with a green test suite. FrontAccounting connects with
  `sql_mode=STRICT_ALL_TABLES`, which rejects the `''` this module passed to the
  integer and date columns behind customers, dimensions, stock adjustments and
  sales orders; each now gets a storable default.
- 20 Sep: Fix `/exrates/{curr_abrev}`, which queried an empty currency code —
  `Currencies::getLastExchangeRate()` used an `$id` that was never defined.
  Found by PHPStan.
- 20 Sep: Drop `strip_quotes()` and its call on `$_POST`. It was built on
  `get_magic_quotes_gpc()`, deprecated in PHP 7.4 and removed in 8.0, and magic
  quotes themselves went in PHP 5.4.
- 20 Sep: Replace gulp and Travis with composer scripts (`test`, `lint`,
  `cs:check`, `analyze`, `quality`, `ci`) and [phpmake](https://github.com/saygoweb/phpmake)
  targets in `makefile.json` for the docs and release builds. No node toolchain
  is needed any more.
- 20 Sep: Add phpcs (PSR-2, `phpcs.xml`) and PHPStan (`phpstan.neon`, level 0,
  resolving FrontAccounting's symbols from `../..`).
- 20 Sep: Regenerate `swagger.json`, which had drifted from the annotations —
  it was missing the trial balance endpoint added in July 2018.

#### July 2018

- 03 Jul: Fixed #46 Trial Balance feature at endpoint /glquery/trialbalance/{startDate}/{endDate}
- 03 Jul: Fixed #47 Journal post without having a memo set on an item causes 500 server error
- 03 Jul: Complete #43, add journal support for non-company currencies and exchange rate.

#### June 2018

- 29 Jun: In Journal add support for get, post, put of memo.
- 29 Jun: Use ISO8601 for Journal dates.
- 28 Jun: Fix #41 Exchange rate get (all) doesn't return an ISO8601 date.

#### 26 June 2018 V2.4-1.7

- 26 Jun: Add full CRUD for exchange rates under a new /exchangerates endpoint.

#### 22 June 2018 v2.4-1.6

- 23 Jun: Change README.md API documentation to point to http://andresamayadiaz.github.io/FrontAccountingSimpleAPI/
- 22 Jun: Add full CRUD for Journal entry, update, and delete (void).
- 21 Jun: Add full CRUD for Bank Accounts under bankaccounts endpoint.
- 21 Jun: Fix formatting of code under src/ and test/ to conform to [PSR-2](https://www.php-fig.org/psr/psr-2/)
- 20 Jun: Add full CRUD for GLAccounts under glaccounts endpoint.
- 20 Jun: Add CRUD for Dimensions under dimensions endpoint.
- 20 Jun: Add swagger and spectacle to produce api documentation from annotated code.

#### 19 June 2018 v2.4-1.5

- Core VARLIB_PATH and VARLOG_PATH inclusions added [see this commit](https://github.com/FrontAccountingERP/FA/commit/4a37a28c49bf900dcc370fd3f21186cedcd632c9).
- TaxType: Added getById (Apmuthu 24 Nov 2017).
- Sales: Fix #32 Sales transactions Total not returned.
- Stock Adjust: Added unit test.
- Stock Adjust: Return now encoded as json msg.
- Stock Adjust: Missing argument $info fixed (Apmuthu 23 Apr 2018).
- Stock Adjust: add_stock_adjustment parameters fixed for FA 2.4 changes in API (Apmuthu, justapeddler 19 Apr 2018).
- Translated Spanish comments to English (Apmuthu 18 Nov 2017).

#### 27 November 2017

- Refactor tests to ensure consistency in POST and PUT api.
  Added tests/Crud_Base.php
- Add api_ensureAssociateArray to remove the numeric index elements that come from the Front Accounting functions.
- The category end point now follows the database schema more closely for property names.
- The customers end point now follows the database schema more closely for property names.

#### 23 November 2017

- Added support for requests sent using Content-Type: application/json
  The body is presumed to be in JSON format and converted appropriately.

#### 17 November 2017

- Updated API to support Front Accounting version 2.4.x
- Added PHP Unit tests
- Added Travis CI build

#### 6 September 2014
Thanks to Cambell Prince

- Added composer.json and dependency on Slim
- Improved error presentation for xdebug users.
- Changed expected headers to uppercase.
- Switch to use composer installed Slim
- Switch to use Slim installed via composer.
- Remove hard coded path 'api' and simplify includes.

#### 17 September 2014
Thanks to Salman Sarwar

- Bug Fix in inventory.inc

#### 14 July 2013:
- Added .htaccess so you can now use API URL's without index.php, examples:
  (Thanks to Christian Estrella)
    OLD: GET http://mysystem.com/api/index.php/locations/
    NEW: GET http://mysystem.com/api/locations/

- Added Pagination to GET methods, it used to return all entries, now is per page, under index.php it has define("RESULTS_PER_PAGE", 2); that defines how many entries you will get per page, if you dont establish a page on the request you will get the first page. (Thanks to Christian Estrella)
    OLD Request: GET http://mysystem.com/api/index.php/locations/
    OLD Response: ALL LOCATIONS
    
    NEW Request 1: GET http://mysystem.com/api/index.php/locations/
    NEW Response 1: First Page of Locations
    
    NEW Request 2: GET http://mysystem.com/api/index.php/locations/?page=5
    NEW Response 2: Page 5 of Locations

- Added Sales Transactions Methods for Quotes, Sales Orders, Deliveries, Invoices (GET, PUT, POST)
    NOTE: This changes hasn't been tested deeply, might have some bugs

#### 14 June 2013:
- Added POST /locations/ To Add A Location Thanks to Richard Vinke

