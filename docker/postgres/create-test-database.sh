#!/bin/sh

set -eu

: "${POSTGRES_DB:?POSTGRES_DB is required}"
: "${POSTGRES_TEST_DB:?POSTGRES_TEST_DB is required}"
: "${POSTGRES_USER:?POSTGRES_USER is required}"
: "${POSTGRES_PASSWORD:?POSTGRES_PASSWORD is required}"

if [ "$POSTGRES_TEST_DB" = "$POSTGRES_DB" ]; then
    echo "The PostgreSQL test database must differ from the application database." >&2
    exit 1
fi

case "$POSTGRES_TEST_DB" in
    ''|[0-9]*|*[!a-zA-Z0-9_]*)
        echo "POSTGRES_TEST_DB must be a valid unquoted PostgreSQL identifier." >&2
        exit 1
        ;;
esac

database_host="${DB_HOST:-postgres}"
database_port="${DB_PORT:-5432}"
export PGPASSWORD="$POSTGRES_PASSWORD"

database_exists="$(
    psql \
        --host="$database_host" \
        --port="$database_port" \
        --username="$POSTGRES_USER" \
        --dbname="$POSTGRES_DB" \
        --tuples-only \
        --no-align \
        --command="SELECT 1 FROM pg_database WHERE datname = '$POSTGRES_TEST_DB';"
)"

if [ "$database_exists" = "1" ]; then
    echo "PostgreSQL test database '$POSTGRES_TEST_DB' already exists."
    exit 0
fi

createdb \
    --host="$database_host" \
    --port="$database_port" \
    --username="$POSTGRES_USER" \
    --owner="$POSTGRES_USER" \
    "$POSTGRES_TEST_DB"

echo "Created PostgreSQL test database '$POSTGRES_TEST_DB'."
