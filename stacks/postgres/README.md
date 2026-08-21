# PostgreSQL da Mundial

Esta stack executa PostgreSQL 16 sem publicar a porta 5432 no host. A aplicação acessa o banco exclusivamente pela rede Docker externa `mundial_backend`, usando o hostname `postgres`.

Na primeira inicialização, são criadas as bases `imobi` e `cliente`, ambas pertencentes ao usuário de aplicação. O usuário administrativo deve ser reservado para manutenção e restauração.

O diretório persistente padrão é `/var/lib/mundial/postgres`. O script de inicialização só é executado quando esse diretório está vazio.

Os limites iniciais são 3 GB de memória e 1,5 CPU. Para ajustá-los, altere `deploy.resources.limits` em `compose.yml` e recrie somente o serviço PostgreSQL em uma janela de manutenção aprovada.

Antes de iniciar:

1. Crie a rede externa com `docker network create mundial_backend`.
2. Copie `.env.example` para `.env` no servidor.
3. Substitua todas as senhas de exemplo por valores fortes e exclusivos.
4. Garanta que `/var/lib/mundial/postgres` pertença ao UID/GID usados pela imagem oficial do PostgreSQL.

Nunca versione `.env`, dumps ou o diretório persistente.
