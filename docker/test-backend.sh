#!/bin/sh

set -eu

project_root="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
backend_env="$project_root/docker/backend/.env"
backend_env_example="$project_root/docker/backend/.env.example"
backend_test_env="$project_root/docker/backend/.env.testing"
backend_test_env_example="$project_root/docker/backend/.env.testing.example"

if [ ! -e "$backend_env" ]; then
    cp -p "$backend_env_example" "$backend_env"
    echo "Created docker/backend/.env from docker/backend/.env.example."
fi

if [ ! -e "$backend_test_env" ]; then
    cp -p "$backend_test_env_example" "$backend_test_env"
    echo "Created docker/backend/.env.testing from docker/backend/.env.testing.example."
fi

docker compose \
    --project-directory "$project_root" \
    --env-file "$backend_env" \
    --profile test \
    run --rm test-database

set -- php artisan test --compact --do-not-cache-result "$@"

exec docker compose \
    --project-directory "$project_root" \
    --env-file "$backend_env" \
    --profile test \
    run --build --rm --no-deps backend-test "$@"
