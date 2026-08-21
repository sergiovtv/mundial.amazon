#!/usr/bin/env bash
set -Eeuo pipefail

compose_file="${1:-stacks/postgres/compose.yml}"
env_file="$(dirname "$compose_file")/.env"

sudo docker compose --env-file "$env_file" -f "$compose_file" ps
sudo docker exec postgres sh -c \
  'psql --username "$POSTGRES_USER" --dbname postgres --command="SELECT datname FROM pg_database WHERE datname IN ('"'"'imobi'"'"', '"'"'cliente'"'"') ORDER BY datname;"'
