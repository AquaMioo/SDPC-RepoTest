# The three processes SDPCC needs running at once.
#
# Railway runs ONE process per service, so this file is a menu rather than a
# recipe: create three services in the Railway project, all pointed at this
# same GitHub repo, and set each one's Start Command to the matching line
# below. Deploying once does not give you all three.
#
#   web     — the site itself. Binds $PORT, which Railway assigns and routes
#             the public domain to.
#   worker  — queued jobs. Without it, notifications and emails pile up in the
#             jobs table and never run.
#   reverb  — the WebSocket server behind live chat. Needs its own service and
#             its own public domain: the browser connects to it directly, not
#             through the web service.
#
# On `php artisan serve` for web: it is PHP's built-in server, which handles
# one request at a time unless PHP_CLI_SERVER_WORKERS is set — so set it (4 is
# plenty for a demo; see .env.production.example). That is fine for a showcase
# and for a class of people clicking at once. For sustained real traffic,
# move the web service to nginx + php-fpm or FrankenPHP.

web: php artisan serve --host 0.0.0.0 --port $PORT

worker: php artisan queue:work --tries=3 --max-time=3600 --sleep=3

reverb: php artisan reverb:start --host 0.0.0.0 --port $PORT
