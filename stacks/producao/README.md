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
