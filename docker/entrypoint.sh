#!/bin/sh
set -eu

mkdir -p \
    /tmp/storage/framework/cache/data \
    /tmp/storage/framework/sessions \
    /tmp/storage/framework/views \
    /tmp/storage/logs \
    /tmp/bootstrap/cache

export VIEW_COMPILED_PATH="${VIEW_COMPILED_PATH:-/tmp/storage/framework/views}"

db_connection="${DB_CONNECTION:-sqlite}"

if [ "$db_connection" = "sqlite" ]; then
    db_path="${DB_DATABASE:-}"

    case "$db_path" in
        ""|database/database.sqlite|/app/database/database.sqlite)
            db_path="/tmp/database.sqlite"
            ;;
    esac

    export DB_CONNECTION=sqlite
    export DB_DATABASE="$db_path"

    mkdir -p "$(dirname "$db_path")"

    if [ ! -f "$db_path" ]; then
        if [ -f /app/database/database.sqlite ]; then
            cp /app/database/database.sqlite "$db_path"
        else
            touch "$db_path"
        fi
    fi
fi

exec frankenphp run --config /etc/frankenphp/Caddyfile
