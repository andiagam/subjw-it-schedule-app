#!/bin/sh
set -eu

cd /app

mkdir -p \
    /tmp/storage/framework/cache/data \
    /tmp/storage/framework/sessions \
    /tmp/storage/framework/views \
    /tmp/storage/logs \
    /tmp/bootstrap/cache

export VIEW_COMPILED_PATH="${VIEW_COMPILED_PATH:-/tmp/storage/framework/views}"

db_connection="${DB_CONNECTION:-pgsql}"

if [ "$db_connection" = "pgsql" ]; then
    php artisan migrate --force --no-interaction

    needs_seed="$(php -r '
        require "/app/vendor/autoload.php";
        $app = require "/app/bootstrap/app.php";
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();
        $users = Illuminate\Support\Facades\DB::table("users");
        $empty = (int) (clone $users)->count() === 0;
        $missing = $empty || (clone $users)->where("username", "aagam648")->doesntExist();
        fwrite(STDOUT, $missing ? "1" : "0");
    ' | tr -cd "0-9")"

    if [ "$needs_seed" = "1" ]; then
        php artisan db:seed --force --no-interaction
    fi
elif [ "$db_connection" = "sqlite" ]; then
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
