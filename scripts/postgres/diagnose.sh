#!/usr/bin/env bash
set -Eeuo pipefail

compose_file="${1:-stacks/postgres/compose.yml}"

docker compose --env-file "$(dirname "$compose_file")/.env" -f "$compose_file" ps
docker compose --env-file "$(dirname "$compose_file")/.env" -f "$compose_file" exec -T postgres \
  psql --username "${POSTGRES_ADMIN_USER:-mundial_admin}" --dbname postgres \
  --command="SELECT datname FROM pg_database WHERE datname IN ('imobi', 'cliente') ORDER BY datname;"

