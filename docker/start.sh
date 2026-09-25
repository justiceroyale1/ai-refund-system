#!/bin/sh

set -eu

project_root="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
backend_env="$project_root/docker/backend/.env"
backend_env_linker="$project_root/docker/ensure-backend-env-link.sh"
frontend_env="$project_root/docker/frontend/.env"
frontend_env_example="$project_root/docker/frontend/.env.example"

"$backend_env_linker" "$project_root"

if [ ! -e "$frontend_env" ]; then
    cp -p "$frontend_env_example" "$frontend_env"
    echo "Created docker/frontend/.env from docker/frontend/.env.example."
fi

exec docker compose \
    --project-directory "$project_root" \
    --env-file "$backend_env" \
    --env-file "$frontend_env" \
    up --build --watch "$@"
