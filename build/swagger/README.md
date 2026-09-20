# swagger-php

`swagger.json` is generated from the `@SWG\*` annotations in `src/` and
`index.php` by [swagger-php](https://github.com/zircote/swagger-php) 2.x, which
is the version that reads `@SWG` — 3.x and later read `@OA` and emit OpenAPI 3
instead.

It lives here rather than in the module's `require-dev` because it cannot be
installed on PHP 8: it depends on `doctrine/annotations ^1.4`, which declares
`php ^5.6 || ^7.0`. Keeping it in the main dependency set would mean the test
suite could not run on PHP 8 either.

`make.phar docs-json` installs it here on first use and then runs it. Generate
the docs on PHP 7.4.

Moving the annotations to `@OA` and swagger-php 4 would remove the split, at the
cost of migrating every annotation in the module and switching the published
document to OpenAPI 3.
