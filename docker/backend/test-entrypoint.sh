#!/bin/sh

set -eu

: "${APP_ENV:?APP_ENV is required}"
: "${DB_DATABASE:?DB_DATABASE is required}"
: "${POSTGRES_DB:?POSTGRES_DB is required}"
: "${POSTGRES_TEST_DB:?POSTGRES_TEST_DB is required}"

if [ "$APP_ENV" != "testing" ]; then
    echo "The backend test container requires APP_ENV=testing." >&2
    exit 1
fi

if [ "$DB_DATABASE" = "$POSTGRES_DB" ]; then
    echo "The backend test container refuses to use the application database." >&2
    exit 1
fi

if [ "$DB_DATABASE" != "$POSTGRES_TEST_DB" ]; then
    echo "DB_DATABASE and POSTGRES_TEST_DB must identify the same test database." >&2
    exit 1
fi

if [ ! -e .env ]; then
    touch .env
fi

php artisan config:clear --ansi

exec backend-entrypoint "$@"
