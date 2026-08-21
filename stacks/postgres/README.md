# PostgreSQL da Mundial

Esta stack executa PostgreSQL 16 e, por padrão, publica a porta 5432 somente em `127.0.0.1`. A aplicação acessa o banco pela rede Docker externa `mundial_backend`, usando o hostname `postgres`.

Enquanto houver integração legada sem suporte a túnel SSH, `POSTGRES_PUBLISH_ADDRESS=0.0.0.0` pode ser usado no servidor somente quando o firewall externo limitar a porta 5432 a um IPv4 `/32` conhecido. Remova essa exceção assim que a integração aceitar SSH ou VPN.

Na primeira inicialização, são criadas as bases `imobi` e `cliente`, ambas pertencentes ao usuário de aplicação. O usuário administrativo deve ser reservado para manutenção e restauração.

O diretório persistente padrão é `/var/lib/mundial/postgres`. O script de inicialização só é executado quando esse diretório está vazio.

Os limites iniciais são 3 GB de memória e 1,5 CPU. Para ajustá-los, altere `deploy.resources.limits` em `compose.yml` e recrie somente o serviço PostgreSQL em uma janela de manutenção aprovada.

## Permissões após restaurar o banco

Depois de importar um dump, aplique as permissões de dados ao usuário da aplicação:

```bash
docker exec -i postgres sh -lc \
  'psql --username "$POSTGRES_USER" --dbname imobi --set app_user="$MUNDIAL_APP_USER"' \
  < ../../scripts/postgres/grant-app-data-access.sql
```

O usuário poderá consultar e gravar dados, mas não alterar a estrutura do banco nem administrar usuários.

Antes de iniciar:

1. Crie a rede externa com `docker network create mundial_backend`.
2. Copie `.env.example` para `.env` no servidor.
3. Substitua todas as senhas de exemplo por valores fortes e exclusivos.
4. Garanta que `/var/lib/mundial/postgres` pertença ao UID/GID usados pela imagem oficial do PostgreSQL.

Nunca versione `.env`, dumps ou o diretório persistente.
