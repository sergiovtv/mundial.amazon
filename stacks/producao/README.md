# Aplicação Mundial em produção

Esta stack publica a aplicação PHP na porta 80 e acessa o PostgreSQL exclusivamente
pela rede Docker interna `mundial_backend`.

No servidor, execute usando o arquivo de variáveis já protegido da stack PostgreSQL:

```bash
docker compose \
  --env-file ../postgres/.env \
  -f compose.yml \
  up -d --build
```

Não copie credenciais para este diretório nem versione arquivos `.env`.

Antes de publicar a Área do Cliente:

1. Migre os PDFs para `/var/lib/mundial/documentos/boletos`, sem montar nenhum diretório da DigitalOcean.
2. Defina `BOLETO_API_KEY` no `.env` protegido da stack PostgreSQL.
3. Confirme que o usuário da aplicação possui acesso de dados aos bancos `imobi` e `cliente`.
4. Os parâmetros `BREVO_API_KEY`, `BREVO_SENDER_EMAIL` e `BREVO_SENDER_NAME` são lidos da tabela `parametro` do banco `imobi`.

O host `177.200.69.64` é a API financeira e não pertence à DigitalOcean. A aplicação não consulta arquivos, banco ou código no droplet antigo.
