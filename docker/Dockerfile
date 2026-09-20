# Test/development image for the FrontAccounting API module.
#
# The module is not an application: it is an extension that only runs inside a
# FrontAccounting checkout, at modules/api. So the image supplies the host
# application — FrontAccounting is cloned to /var/www/html at build time — and
# this repository is bind-mounted over /var/www/html/modules/api at run time.
# That is the same arrangement .travis.yml assembled by hand with a git clone
# and an rsync, with the version pinned and the result reusable.
#
# Base and PHP source follow the sibling stack in the FrontAccounting repo
# (docker/Dockerfile there): plain Debian trixie with PHP from Ondřej Surý's
# repository, because the official php:7.4-* images are built on bullseye, whose
# LTS ended on 2026-08-31, and no 7.4 image on trixie exists or will.
FROM debian:trixie-slim

# Any version sury publishes for trixie: 7.4, 8.0 … 8.4. FrontAccounting 2.4.x
# is written against PHP 5.4-7.x and this module's master targets 5.6/7.0, so
# 7.4 is the newest either is known to run on. 8.x is what feature/php8 is for.
ARG PHP_VERSION=7.4
ARG INSTALL_XDEBUG=true

ENV DEBIAN_FRONTEND=noninteractive

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        gnupg \
        ; \
    install -d -m 0755 /etc/apt/keyrings; \
    curl -fsSL https://packages.sury.org/php/apt.gpg \
        -o /etc/apt/keyrings/sury-php.gpg; \
    echo "deb [signed-by=/etc/apt/keyrings/sury-php.gpg] https://packages.sury.org/php/ trixie main" \
        > /etc/apt/sources.list.d/sury-php.list; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*

# mysqli is required (FA's includes/db/connect_db_mysqli.inc); gd is used by
# reporting/; mbstring, xml and zip are what composer expects. curl is what the
# test suite's Guzzle client uses to reach Apache. mariadb-client backs
# `fa-api db shell`.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        apache2 \
        mariadb-client \
        unzip \
        zip \
        libapache2-mod-php${PHP_VERSION} \
        php${PHP_VERSION}-cli \
        php${PHP_VERSION}-common \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-gd \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-zip \
        ; \
    if [ "${INSTALL_XDEBUG}" = "true" ]; then \
        apt-get install -y --no-install-recommends php${PHP_VERSION}-xdebug; \
    fi; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# phpmake — the build runner the module's `composer docs` and `composer package`
# scripts delegate to. It is not a composer package: it is built from source
# into a phar and put on PATH, which is what its own install.php does.
ARG PHPMAKE_REPO=https://github.com/saygoweb/phpmake.git
ARG PHPMAKE_REF=master
RUN set -eux; \
    git clone --depth 1 --branch "${PHPMAKE_REF}" "${PHPMAKE_REPO}" /tmp/phpmake; \
    cd /tmp/phpmake; \
    php -d phar.readonly=0 build-phar.php; \
    install -m 0755 make.phar /usr/local/bin/make.phar; \
    rm -rf /tmp/phpmake; \
    test -s /usr/local/bin/make.phar

# ---------------------------------------------------------------------------
# FrontAccounting itself
# ---------------------------------------------------------------------------
#
# Point these at a fork or a tag to test against something other than upstream
# master — FA_REPO=https://github.com/cambell-prince/frontaccounting.git with
# FA_REF=master-cp, for instance. A shallow clone needs a branch or tag name; a
# bare commit sha will not work.
#
# Changing either needs `fa-api up --build`; the clone is a build step, so a
# running container keeps the tree it was built with.
ARG FA_REPO=https://github.com/FrontAccountingERP/FA.git
ARG FA_REF=master
RUN set -eux; \
    rm -rf /var/www/html; \
    git clone --depth 1 --branch "${FA_REF}" "${FA_REPO}" /var/www/html; \
    git -C /var/www/html log -1 --format='FrontAccounting %h %ad %s' --date=short; \
    git config --system --add safe.directory /var/www/html

# Debian keeps a conf.d per SAPI rather than the single one the official php
# images have, and the CLI (phpunit, composer, php -l) must see the same
# settings as Apache.
COPY php.ini /etc/php/${PHP_VERSION}/apache2/conf.d/zz-fa-api.ini
COPY php.ini /etc/php/${PHP_VERSION}/cli/conf.d/zz-fa-api.ini

# Port 8000, not 80: tests/TestEnvironment.php points Guzzle at
# http://localhost:8000 and phpunit runs inside this container, so serving there
# is what lets the committed suite run unedited. The host port is mapped to it.
COPY apache-fa-api.conf /etc/apache2/sites-available/fa-api.conf
RUN set -eux; \
    printf 'Listen 8000\n' > /etc/apache2/ports.conf; \
    a2dissite 000-default; \
    a2ensite fa-api; \
    a2enmod rewrite

# FrontAccounting writes into its own tree at runtime — tmp/ (VARLIB_PATH and
# VARLOG_PATH, defined in this module's session_utils.inc), company/<n>/js_cache
# for generated JavaScript, pdf_files, backup. .gitignore keeps most of them out
# of a clone, so Apache would meet a missing directory rather than an unwritable
# one.
RUN set -eux; \
    mkdir -p /var/www/html/tmp \
             /var/www/html/company/0/js_cache \
             /var/www/html/company/0/pdf_files \
             /var/www/html/company/0/backup \
             /var/www/html/company/0/images \
             /var/www/html/modules/api

# Apache runs as www-data remapped to the host user's ids, so anything written
# into the bind-mounted checkout — vendor/ above all — stays owned by you on the
# host rather than by root. Skipped when they already match, because usermod
# refuses to assign an id the account already holds.
ARG USER_UID=1000
ARG USER_GID=1000
RUN set -eux; \
    if [ "$(id -u www-data)" != "${USER_UID}" ] || [ "$(id -g www-data)" != "${USER_GID}" ]; then \
        groupmod -o -g "${USER_GID}" www-data; \
        usermod -o -u "${USER_UID}" -g "${USER_GID}" www-data; \
    fi; \
    chown -R www-data:www-data /var/www/html/tmp /var/www/html/company

# phpenmod/phpdismod in the entrypoint need to know which version to act on.
ENV PHP_VERSION=${PHP_VERSION}
ENV FA_ROOT=/var/www/html

WORKDIR /var/www/html/modules/api

EXPOSE 8000

COPY docker-entrypoint.sh /usr/local/bin/fa-api-entrypoint
RUN chmod +x /usr/local/bin/fa-api-entrypoint

ENTRYPOINT ["fa-api-entrypoint"]
CMD ["apache2ctl", "-D", "FOREGROUND"]
