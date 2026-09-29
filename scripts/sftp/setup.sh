#!/usr/bin/env bash
# Prepara e sobe a stack SFTP (stacks/arquivos) no servidor.
# Uso: sudo bash scripts/sftp/setup.sh
set -euo pipefail

REPO_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
STACK_DIR="$REPO_DIR/stacks/arquivos"
KEYS_DIR=/var/lib/mundial/sftp/host-keys
FOTOS_DIR=/var/lib/mundial/media/_fotos_1020304050
DOCS_DIR=/var/lib/mundial/documentos
SFTP_UID=1001
PORT=2222

if [[ $EUID -ne 0 ]]; then
  echo "Execute com sudo." >&2
  exit 1
fi

install -d -m 0700 "$KEYS_DIR"
[[ -f $KEYS_DIR/ssh_host_ed25519_key ]] || ssh-keygen -q -t ed25519 -N '' -f "$KEYS_DIR/ssh_host_ed25519_key"
[[ -f $KEYS_DIR/ssh_host_rsa_key ]] || ssh-keygen -q -t rsa -b 4096 -N '' -f "$KEYS_DIR/ssh_host_rsa_key"

install -d "$FOTOS_DIR" "$DOCS_DIR"
echo "Donos atuais (antes do ajuste):"
ls -ldn "$FOTOS_DIR" "$DOCS_DIR"
chown -R "$SFTP_UID:$SFTP_UID" "$FOTOS_DIR" "$DOCS_DIR"
chmod -R u+rwX,go+rX "$FOTOS_DIR" "$DOCS_DIR"

PASSWORD=""
if [[ ! -f $STACK_DIR/.env ]]; then
  PASSWORD="$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-20)"
  HASH="$(openssl passwd -6 "$PASSWORD")"
  sed "s|^SFTP_USERS=.*|SFTP_USERS='mundial:$HASH:e:$SFTP_UID:$SFTP_UID'|" \
    "$STACK_DIR/.env.example" > "$STACK_DIR/.env"
  chmod 600 "$STACK_DIR/.env"
fi

docker compose --project-directory "$STACK_DIR" -f "$STACK_DIR/compose.yml" up -d

IP="$(curl -fsS --max-time 5 https://checkip.amazonaws.com || hostname -I | awk '{print $1}')"
echo
echo "================ Dados para o FileZilla ================"
echo "Protocolo: SFTP"
echo "Host:      $IP"
echo "Porta:     $PORT"
echo "Usuário:   mundial"
if [[ -n $PASSWORD ]]; then
  echo "Senha:     $PASSWORD"
  echo "(anote agora: ela não fica salva em texto em lugar nenhum)"
else
  echo "Senha:     a já definida anteriormente (.env existente)"
fi
echo "========================================================"
echo "Lembre de liberar a porta $PORT no firewall do Lightsail."
