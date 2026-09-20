# Simple API for Front Accounting

I needed some basic integration functions to another software and decided to create this REST API and contribute to the Front Accounting community. I hope you find it usefull!

## Installation

*DO NOT* use git to clone this repo into your FrontAccounting modules folder unless you are a developer.

*DO* download the [latest release](https://github.com/andresamayadiaz/FrontAccountingSimpleAPI/releases/latest) in either zip or tgz and unpack into a folder such as .../modules/api.

## API Quick Start

1. Just copy the files into the modules directory under a folder called "api".
2. OPTIONAL: To test your installation Edit the file util.php and change the $company, $username and $password variables so you can test. Use it at your own risk, to provide login from another software you need to send X-COMPANY, X-USER and X-PASSWORD headers in the request and the API will use those credentials, if they're wrong you will get a nice message saying "Bad Login"
3. Try to access the API, for example, try the Items Category List, type this on your explorer: http://YOUR_FA_URL/modules/api/category/ You will see a JSON response with all your items categories, if not check your credentials in the util.php file, or the X headers if set in the client.

## Documentation

See the [API Documentation](http://andresamayadiaz.github.io/FrontAccountingSimpleAPI/) for descriptions of each endpoint.

### Methods

The following API endpoints have been implemented:

- Sales
- Customers
- Items / Inventory
- Items Categories
- Suppliers
- Inventory Movements
- Locations
- Tax Groups
- Tax Types
- Bank Accounts
- GL Accounts
- GL Account Types.
- Journal
- Payments (customer payments, optionally allocated to a document, and voiding them)

Some of them have not been tested yet so be carefull.

## Development

Everything runs in docker — a FrontAccounting install with this module plugged
into it, built from scratch, so there is nothing to install on your machine but
docker itself:

    docker/fa-api init      # pick host ports that are free here
    docker/fa-api up        # build, boot, seed the database
    docker/fa-api test      # the PHPUnit suite
    docker/fa-api lint      # php -l, then phpcs PSR-2
    docker/fa-api analyze   # PHPStan

See [docker/README.md](docker/README.md). The same commands run on GitHub
Actions, so a green run locally is a green run there.

The tasks themselves are composer scripts, so they work anywhere a PHP and a
FrontAccounting install are already set up:

| command | effect |
| --- | --- |
| `composer test` | PHPUnit (needs a server and a seeded `fa_test` database) |
| `composer lint` | `php -l` over every `.php` and `.inc` file |
| `composer cs:check` / `cs:fix` | PSR-2 via phpcs / phpcbf (`phpcs.xml`) |
| `composer analyze` | PHPStan (`phpstan.neon`) |
| `composer quality` | lint, then analyze |
| `composer ci` | quality, then test |

Multi-step builds — regenerating `swagger.json`, building the release archives —
are [phpmake](https://github.com/saygoweb/phpmake) targets in `makefile.json`:

| command | effect |
| --- | --- |
| `make.phar docs-json` | regenerate `swagger.json` from the `@SWG` annotations |
| `make.phar docs` | that, then the static HTML (needs a global `spectacle`) |
| `make.phar package` | the release `.zip` and `.tgz`, built against `--no-dev` vendor |

`make.phar` is on PATH inside the docker image (`docker/fa-api make <target>`).
To install it on the host, clone
[saygoweb/phpmake](https://github.com/saygoweb/phpmake) and run `php make.php
install`.

## How to Help

Report issues you find in our GitHub Issue Tracker. Please report with as much detail as you can. Simply saying "It doesn't work" will gain you sympathy, but not a lot else.

Want to contribute code? Go right ahead, fork the project on GitHub, pull requests are welcome. Note that we're trying to follow the [PSR-2 Coding Style Guide](https://www.php-fig.org/psr/psr-2/).

## Contact

Any question about this you can always contact me: andres.amaya.diaz@gmail.com