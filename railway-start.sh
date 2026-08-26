#!/usr/bin/env sh
#
# One start command, three services.
#
# Railway runs a single process per service, and every service in this project
# deploys this same repository — so the start command in railway.json is shared
# whether you want it to be or not. Without this dispatch a Reverb or queue
# service would boot `artisan serve` instead of its own process, and would
# re-run migrations and the seeder on top of the web service's.
#
# RAILWAY_SERVICE_NAME is injected by Railway. The fallback is the web branch,
# so an unnamed or locally-run container still behaves the way it always did.
#
# See the Procfile for what each process is for.

set -e

case "${RAILWAY_SERVICE_NAME:-web}" in

    # The WebSocket server behind live chat. Needs its own public domain: the
    # browser connects to it directly, not through the web service.
    *reverb* | *Reverb* | *REVERB*)
        echo "[start] reverb on :${PORT}"
        exec php artisan reverb:start --host 0.0.0.0 --port "${PORT}"
        ;;

    # Queued jobs. Without this, notifications pile up in the jobs table.
    *worker* | *Worker* | *queue*)
        echo "[start] queue worker"
        exec php artisan queue:work --tries=3 --max-time=3600 --sleep=3
        ;;

    # The site itself. Only this branch touches the database: running migrate
    # or db:seed from three services at once is a race nobody needs.
    *)
        echo "[start] web on :${PORT}"
        php artisan migrate --force
        php artisan db:seed --force
        php artisan storage:link
        exec php artisan serve --host 0.0.0.0 --port "${PORT}" --no-reload
        ;;

esac
