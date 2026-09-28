#!/bin/sh
# api's CI. Runs inside the FrontAccounting CI image (docker/ci in
# cambell-prince/frontaccounting) with this checkout at modules/api; api is
# not a FrontAccounting extension, so the run is --name api --no-activate.
set -eu
: "${FA_URL:?run this inside the FrontAccounting CI image (docker/ci/plugin-test.sh)}"

echo "==> lint"
composer run lint
echo "==> phpcs (advisory, as before)"
composer run cs:check -- --report=summary || true

echo "==> analyze"
composer run analyze

echo "==> phpunit"
curl -fsS -o /dev/null "$FA_URL/modules/api/category/" -H 'X-COMPANY: 0' -H 'X-USER: test' -H 'X-PASSWORD: test' \
    || echo "(modules/api/category/ did not answer 2xx; the suite will say why)"
composer run test
