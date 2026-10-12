#!/usr/bin/env bash
# Sincroniza os bancos imobi e cliente da DigitalOcean (fonte da verdade) para a AWS.
#
# Executar NO SERVIDOR AWS, a partir de uma sessão com agent forwarding, para que
# a chave da DigitalOcean nunca seja copiada para o servidor:
#
#   ssh-add ~/.ssh/mundial_do_migration
#   ssh -A ubuntu@56.126.59.251
#   cd ~/mundial-sync && ./sincroniza-banco-do.sh <etapa>
#
# Etapas (nesta ordem):
#   precheck  verifica acesso à DO, container, espaço em disco e bancos existentes
#   dump      gera pg_dump (formato custom) de cada banco na DO e grava na AWS
#   restore   restaura em <banco>_sync, aplica permissões e recria tabelas da homepage
#   verify    compara a contagem de linhas de todas as tabelas: DO x <banco>_sync
#             (depois do swap: ./sincroniza-banco-do.sh verify "" compara com <banco>)
#   swap      troca por RENAME: <banco> -> <banco>_old e <banco>_sync -> <banco>
#   rollback  desfaz o swap: <banco> -> <banco>_sync e <banco>_old -> <banco>
#   drop-old  remove <banco>_old (somente depois de validar a produção)
#
# Até o swap, os bancos em uso pela aplicação e pelo ERP não são alterados.

set -Eeuo pipefail

DO_HOST="${DO_HOST:-root@192.81.214.67}"
# O /usr/bin da DO traz clientes 9.2 do CentOS; o servidor é PostgreSQL 10.
DO_PG_BIN="${DO_PG_BIN:-/usr/pgsql-10/bin}"
PG_CONTAINER="${PG_CONTAINER:-postgres}"
DATABASES="${DATABASES:-imobi cliente}"
WORKDIR="${WORKDIR:-/var/lib/mundial/sync}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SQL_DIR="${SQL_DIR:-$SCRIPT_DIR/sql}"
RESTORE_JOBS="${RESTORE_JOBS:-2}"

SSH_DO=(ssh -o BatchMode=yes -o LogLevel=ERROR -o ServerAliveInterval=30 "$DO_HOST")

log() { printf '[%s] %s\n' "$(date '+%H:%M:%S')" "$*"; }
die() { log "ERRO: $*"; exit 1; }

# psql como administrador dentro do container; SQL via stdin.
pg_admin() {
  local database="$1"; shift
  sudo docker exec -i "$PG_CONTAINER" sh -c \
    'db="$1"; shift; exec psql --set ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$db" -At "$@"' \
    _ "$database" "$@"
}

app_user() {
  sudo docker exec "$PG_CONTAINER" sh -c 'printf %s "$MUNDIAL_APP_USER"'
}

db_exists() {
  [[ "$(pg_admin postgres -c "SELECT 1 FROM pg_database WHERE datname = '$1'")" == "1" ]]
}

# Contagem exata de linhas por tabela (compatível com PostgreSQL 10 e 16).
COUNT_SQL="
SELECT tablename || '|' || (xpath('/row/c/text()',
       query_to_xml(format('SELECT count(*) AS c FROM %I.%I', schemaname, tablename), false, true, '')))[1]::text
FROM pg_tables
WHERE schemaname = 'public'
ORDER BY tablename;"

precheck() {
  log "Acesso à DigitalOcean"
  "${SSH_DO[@]}" "cd /tmp && sudo -u postgres $DO_PG_BIN/psql -Atc 'SELECT version()'" \
    || die "sem acesso à DO (use ssh -A com a chave mundial_do_migration carregada)"

  log "Container $PG_CONTAINER"
  pg_admin postgres -c 'SELECT version()'
  log "Usuário da aplicação: $(app_user)"

  for db in $DATABASES; do
    db_exists "$db" || die "banco $db não existe na AWS"
    db_exists "${db}_old" && die "${db}_old já existe; rode drop-old ou rollback antes"
    local size
    size="$("${SSH_DO[@]}" "cd /tmp && sudo -u postgres $DO_PG_BIN/psql -Atc \"SELECT pg_size_pretty(pg_database_size('$db'))\"")"
    log "DO $db: $size"
  done

  log "Espaço em disco na AWS"
  df -h / | tail -1
  for f in grant-app-data-access.sql create-homepage-photo-feedback.sql; do
    [[ -f "$SQL_DIR/$f" ]] || die "arquivo ausente: $SQL_DIR/$f"
  done
  log "precheck OK"
}

dump() {
  sudo install -d -m 0700 -o "$(id -u)" -g "$(id -g)" "$WORKDIR"
  for db in $DATABASES; do
    log "Dump de $db na DO"
    "${SSH_DO[@]}" "cd /tmp && sudo -u postgres $DO_PG_BIN/pg_dump --format=custom --compress=6 $db" \
      > "$WORKDIR/$db.dump.partial"
    mv "$WORKDIR/$db.dump.partial" "$WORKDIR/$db.dump"
    log "$db.dump: $(du -h "$WORKDIR/$db.dump" | cut -f1)"
  done
  date '+%Y-%m-%d %H:%M:%S %z' > "$WORKDIR/dump-timestamp"
  log "dump OK ($(cat "$WORKDIR/dump-timestamp"))"
}

restore() {
  local user
  user="$(app_user)"

  for db in $DATABASES; do
    local target="${db}_sync" dumpfile="$WORKDIR/$db.dump"
    [[ -s "$dumpfile" ]] || die "dump ausente: $dumpfile (rode a etapa dump)"

    log "Recriando $target"
    pg_admin postgres --set db="$target" --set owner="$user" <<'SQL'
SELECT format('DROP DATABASE IF EXISTS %I', :'db') \gexec
SELECT format('CREATE DATABASE %I OWNER %I ENCODING %L LC_COLLATE %L LC_CTYPE %L TEMPLATE template0',
              :'db', :'owner', 'UTF8', 'pt_BR.UTF-8', 'pt_BR.UTF-8') \gexec
SQL
    pg_admin "$target" <<'SQL'
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
CREATE EXTENSION IF NOT EXISTS citext;
CREATE EXTENSION IF NOT EXISTS unaccent;
SQL

    log "Restaurando $db em $target (pode levar vários minutos)"
    sudo docker cp "$dumpfile" "$PG_CONTAINER:/tmp/$db.dump"
    # Objetos passam a pertencer ao usuário da aplicação, como hoje na AWS.
    # Erros esperados (ex.: COMMENT ON EXTENSION) ficam no log para revisão.
    if ! sudo docker exec "$PG_CONTAINER" sh -c \
        'pg_restore --username "$POSTGRES_USER" --dbname "$1" --no-owner --no-acl --role "$2" --jobs "$3" "/tmp/$4.dump"' \
        _ "$target" "$user" "$RESTORE_JOBS" "$db" > "$WORKDIR/restore-$db.log" 2>&1; then
      log "pg_restore terminou com avisos; revise $WORKDIR/restore-$db.log:"
      grep -E '^pg_restore: (error|erro)' "$WORKDIR/restore-$db.log" | head -20 || true
    fi
    sudo docker exec "$PG_CONTAINER" rm -f "/tmp/$db.dump"

    log "Permissões em $target"
    pg_admin "$target" --set app_user="$user" < "$SQL_DIR/grant-app-data-access.sql"

    if [[ "$db" == "imobi" ]]; then
      log "Tabelas de avaliação de fotos da homepage"
      pg_admin "$target" --set app_user="$user" < "$SQL_DIR/create-homepage-photo-feedback.sql"

      if [[ "$(pg_admin postgres -c "SELECT 1 FROM pg_roles WHERE rolname = 'mundial_dev_ro'")" == "1" ]]; then
        log "Permissões de leitura do mundial_dev_ro"
        pg_admin "$target" --set db="$target" <<'SQL'
SELECT format('GRANT CONNECT ON DATABASE %I TO mundial_dev_ro', :'db') \gexec
GRANT USAGE ON SCHEMA public TO mundial_dev_ro;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO mundial_dev_ro;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO mundial_dev_ro;
GRANT INSERT ON TABLE atendimento TO mundial_dev_ro;
GRANT USAGE, SELECT ON SEQUENCE gen_atendimento TO mundial_dev_ro;
SQL
      fi
    fi

    log "ANALYZE em $target"
    pg_admin "$target" -c 'ANALYZE'
  done
  log "restore OK"
}

verify() {
  local suffix="${1-_sync}"
  log "Dump gerado em: $(cat "$WORKDIR/dump-timestamp" 2>/dev/null || echo '?')"
  for db in $DATABASES; do
    local target="${db}${suffix}"
    "${SSH_DO[@]}" "cd /tmp && sudo -u postgres $DO_PG_BIN/psql -At -d $db" <<< "$COUNT_SQL" | LC_ALL=C sort -t'|' -k1,1 > "$WORKDIR/count-do-$db.txt"
    pg_admin "$target" <<< "$COUNT_SQL" | LC_ALL=C sort -t'|' -k1,1 > "$WORKDIR/count-aws-$db.txt"

    log "== $db (DO) x $target (AWS)"
    LC_ALL=C join -t'|' -a1 -a2 -e 'AUSENTE' -o '0,1.2,2.2' \
        "$WORKDIR/count-do-$db.txt" "$WORKDIR/count-aws-$db.txt" \
      | awk -F'|' -v OFS='|' '$2 != $3 { print $1, "DO=" $2, "AWS=" $3, ($2 ~ /^[0-9]+$/ && $3 ~ /^[0-9]+$/) ? "dif=" $2 - $3 : "" }' \
      | tee "$WORKDIR/diff-$db.txt"
    log "$db: $(wc -l < "$WORKDIR/count-do-$db.txt") tabelas na DO, $(wc -l < "$WORKDIR/diff-$db.txt") com diferença"
  done
  log "Diferenças pequenas em tabelas de log/acesso são esperadas (DO continua recebendo dados após o dump)."
  log "Tabelas homepage_photo_* existem apenas na AWS."
  return 0
}

swap() {
  for db in $DATABASES; do
    db_exists "${db}_sync" || die "${db}_sync não existe"
    db_exists "${db}_old" && die "${db}_old já existe"
  done

  for db in $DATABASES; do
    log "Trocando $db"
    pg_admin postgres --set db="$db" --set new="${db}_sync" --set old="${db}_old" <<'SQL'
SELECT format('ALTER DATABASE %I WITH ALLOW_CONNECTIONS false', :'db') \gexec
SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = :'db' AND pid <> pg_backend_pid();
SELECT format('ALTER DATABASE %I RENAME TO %I', :'db', :'old') \gexec
SELECT format('ALTER DATABASE %I RENAME TO %I', :'new', :'db') \gexec
SQL
    log "$db -> ${db}_old (conexões bloqueadas) | ${db}_sync -> $db"
  done
  log "swap OK. Confira o site e o ERP; para desfazer: $0 rollback"
}

rollback() {
  for db in $DATABASES; do
    db_exists "${db}_old" || die "${db}_old não existe; nada a desfazer"
  done

  for db in $DATABASES; do
    log "Desfazendo $db"
    pg_admin postgres --set db="$db" --set new="${db}_sync" --set old="${db}_old" <<'SQL'
SELECT format('ALTER DATABASE %I WITH ALLOW_CONNECTIONS false', :'db') \gexec
SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = :'db' AND pid <> pg_backend_pid();
SELECT format('ALTER DATABASE %I RENAME TO %I', :'db', :'new') \gexec
SELECT format('ALTER DATABASE %I RENAME TO %I', :'old', :'db') \gexec
SELECT format('ALTER DATABASE %I WITH ALLOW_CONNECTIONS true', :'db') \gexec
SQL
  done
  log "rollback OK: bancos anteriores restabelecidos"
}

drop_old() {
  for db in $DATABASES; do
    db_exists "${db}_old" || { log "${db}_old não existe"; continue; }
    read -r -p "Remover definitivamente ${db}_old? Digite o nome para confirmar: " answer
    [[ "$answer" == "${db}_old" ]] || { log "Ignorado: ${db}_old"; continue; }
    pg_admin postgres --set db="${db}_old" <<'SQL'
SELECT format('DROP DATABASE %I', :'db') \gexec
SQL
    log "${db}_old removido"
  done
}

case "${1:-}" in
  precheck) precheck ;;
  dump)     dump ;;
  restore)  restore ;;
  verify)   verify "${2-_sync}" ;;
  swap)     swap ;;
  rollback) rollback ;;
  drop-old) drop_old ;;
  *) sed -n '2,22p' "$0"; exit 1 ;;
esac
