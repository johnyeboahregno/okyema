#!/bin/sh
set -e

# The named volume mounted over storage/ can come up empty (and owned by root)
# on first creation — create the framework dirs and re-assert ownership every
# start rather than only at build time.
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

exec "$@"
