#!/bin/sh

set -eu

default_project_root="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
project_root="${1:-$default_project_root}"

if [ ! -d "$project_root/backend" ] || [ ! -d "$project_root/docker/backend" ]; then
    echo "Expected backend and docker/backend directories under: $project_root" >&2
    exit 1
fi

backend_env="$project_root/backend/.env"
docker_backend_env="$project_root/docker/backend/.env"
docker_backend_env_example="$project_root/docker/backend/.env.example"
expected_link_target="../docker/backend/.env"

if [ ! -e "$docker_backend_env" ]; then
    cp -p "$docker_backend_env_example" "$docker_backend_env"
    echo "Created docker/backend/.env from docker/backend/.env.example."
fi

if [ -L "$backend_env" ] && [ "$(readlink "$backend_env")" = "$expected_link_target" ]; then
    exit 0
fi

if [ -e "$backend_env" ] || [ -L "$backend_env" ]; then
    rm -f -- "$backend_env"
fi

ln -s "$expected_link_target" "$backend_env"
echo "Linked backend/.env to docker/backend/.env."
