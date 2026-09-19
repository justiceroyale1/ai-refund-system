#!/bin/sh

set -eu

application_root="${1:-/var/www/html}"

for filename in .env .env.example; do
    environment_file="$application_root/$filename"

    if [ -e "$environment_file" ] || [ -L "$environment_file" ]; then
        echo "Unexpected application environment file in backend image: $environment_file" >&2
        exit 1
    fi
done

echo "Backend image contains no application .env or .env.example file."
