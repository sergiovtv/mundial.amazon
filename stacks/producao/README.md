# Aplicação Mundial em produção

Esta stack executa a aplicação PHP atrás do Traefik (`stacks/traefik`), que recebe as
portas 80 e 443 e encaminha o tráfego pela rede Docker `mundial_web`. O PostgreSQL é
acessado exclusivamente pela rede interna `mundial_backend`.

No servidor, execute usando o arquivo de variáveis já protegido da stack PostgreSQL:

```bash
docker compose \
  --env-file ../postgres/.env \
  -f compose.yml \
  up -d --build
```

Não copie credenciais para este diretório nem versione arquivos `.env`.

Antes de publicar a Área do Cliente:

1. Os PDFs ficam em `/var/lib/mundial/media/pdf_1020304050` (`boletos`, `extratos` e
   `informe_rendimentos`), enviados pelo ERP via FTP e montados somente leitura em
   `/var/lib/mundial/documentos` no container. Não monte nenhum diretório da DigitalOcean.
2. Cadastre `BOLETO_API_KEY` na tabela `parametro` do banco `imobi`.
3. Confirme que o usuário da aplicação possui acesso de dados aos bancos `imobi` e `cliente`.
4. Os parâmetros `BREVO_API_KEY`, `BREVO_SENDER_EMAIL` e `BREVO_SENDER_NAME` são lidos da tabela `parametro` do banco `imobi`.

## Cache de fotos com marca d'água

A aplicação lê os originais em `/var/lib/mundial/media/_fotos_1020304050` e grava a
primeira versão solicitada em `/var/lib/mundial/media/CacheFotos`. Prepare o cache
com permissão para o usuário `www-data` do container antes de subir a stack:

```bash
install -d -o 33 -g 33 -m 0775 /var/lib/mundial/media/CacheFotos
```

Uma foto já existente no cache é entregue diretamente pelo Apache e nunca é
reprocessada. Para gerar novamente uma foto específica, remova somente a cópia
correspondente de `CacheFotos`; o original permanece intacto.

O host `177.200.69.64` é a API financeira e não pertence à DigitalOcean. A aplicação não consulta arquivos, banco ou código no droplet antigo.
