#!/usr/bin/env bash
set -euo pipefail

# Deploy Okyema on the VPS: pull, build, recreate, migrate, notify.
# Usage (run on the VPS as the deploy user):
#   bash ~/okyema/deploy.sh

APP_DIR="${APP_DIR:-$HOME/okyema}"
COMPOSE_DIR="${COMPOSE_DIR:-$HOME/courtly}"
DEPLOY_EMAIL="${DEPLOY_EMAIL:-}"

cd "$APP_DIR"
git pull --ff-only origin master
COMMIT="$(git rev-parse --short HEAD)"
CHANGES="$(git log -1 --pretty=format:'%s%n%n%b')"
FILES="$(git diff-tree --no-commit-id --name-status -r HEAD)"
SUMMARY="$(printf '%s\n\nFiles changed:\n%s' "$CHANGES" "$FILES")"

cd "$COMPOSE_DIR"
sudo docker compose build okyema-app okyema-queue
sudo docker compose up -d --force-recreate okyema-app okyema-queue
sudo docker compose exec -T okyema-app php artisan migrate --force

if [ -n "$DEPLOY_EMAIL" ]; then
    sudo docker compose exec -T okyema-app php artisan mail:deploy-success "$DEPLOY_EMAIL" "$COMMIT" "$SUMMARY"
fi

sudo docker compose ps --format 'table {{.Name}}\t{{.Status}}'
