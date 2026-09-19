#!/bin/sh

set -eu

project_root="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
backend_env="$project_root/docker/backend/.env"
backend_env_example="$project_root/docker/backend/.env.example"
frontend_env="$project_root/docker/frontend/.env"
frontend_env_example="$project_root/docker/frontend/.env.example"

if [ ! -e "$backend_env" ]; then
    cp -p "$backend_env_example" "$backend_env"
    echo "Created docker/backend/.env from docker/backend/.env.example."
fi

if [ ! -e "$frontend_env" ]; then
    cp -p "$frontend_env_example" "$frontend_env"
    echo "Created docker/frontend/.env from docker/frontend/.env.example."
fi

exec docker compose \
    --project-directory "$project_root" \
    --env-file "$backend_env" \
    --env-file "$frontend_env" \
    up --build -d "$@"
