#!/bin/sh
# Runs BOTH services inside one container:
#   - php-fpm  (FastCGI listener on 127.0.0.1:9000)
#   - nginx    (HTTP listener on 0.0.0.0:8080 — the port Render/PaaS detect)
#
# nginx runs in the foreground, so it is the container's main process: if it
# dies, the container exits and the platform restarts it.
set -e

echo "Starting php-fpm..."
php-fpm -D

echo "Starting nginx on :${PORT:-8080}..."
exec nginx -g 'daemon off;'
