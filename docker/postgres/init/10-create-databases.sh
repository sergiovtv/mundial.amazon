#!/usr/bin/env bash
set -Eeuo pipefail

: "${MUNDIAL_APP_USER:?MUNDIAL_APP_USER nao definido}"
: "${MUNDIAL_APP_PASSWORD:?MUNDIAL_APP_PASSWORD nao definido}"

psql --set ON_ERROR_STOP=1 \
  --username "$POSTGRES_USER" \
  --dbname postgres \
  --set app_user="$MUNDIAL_APP_USER" \
  --set app_password="$MUNDIAL_APP_PASSWORD" <<'SQL'
SELECT format('CREATE ROLE %I LOGIN PASSWORD %L', :'app_user', :'app_password')
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = :'app_user') \gexec

SELECT format('CREATE DATABASE %I OWNER %I ENCODING %L TEMPLATE template0', 'imobi', :'app_user', 'UTF8')
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'imobi') \gexec

SELECT format('CREATE DATABASE %I OWNER %I ENCODING %L TEMPLATE template0', 'cliente', :'app_user', 'UTF8')
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'cliente') \gexec
SQL

for database in imobi cliente; do
  psql --set ON_ERROR_STOP=1 \
    --username "$POSTGRES_USER" \
    --dbname "$database" \
    --set app_user="$MUNDIAL_APP_USER" <<'SQL'
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
SELECT format('GRANT USAGE, CREATE ON SCHEMA public TO %I', :'app_user') \gexec
SQL
done

