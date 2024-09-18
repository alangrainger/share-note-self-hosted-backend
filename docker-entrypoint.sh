#!/bin/sh
set -e

# Generate the env.cfg file from the template
envsubst < /var/www/html/api/env.cfg.docker > /var/www/html/api/env.cfg

# Execute the CMD
exec "$@"