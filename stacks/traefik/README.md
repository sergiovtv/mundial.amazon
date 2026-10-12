# Traefik (HTTPS)

Publica as portas 80 e 443, redireciona HTTP para HTTPS e encaminha
`mundialimoveis.com.br` e `www.mundialimoveis.com.br` para a aplicação pela rede
Docker `mundial_web`. O certificado é emitido e renovado automaticamente pelo
Let's Encrypt (desafio HTTP-01), guardado em `/var/lib/mundial/traefik/letsencrypt`.

Antes de iniciar:

```bash
docker network create mundial_web
sudo install -d -m 0700 /var/lib/mundial/traefik/certs /var/lib/mundial/traefik/letsencrypt
sudo install -d -m 0755 /var/log/traefik
```

`/var/lib/mundial/traefik/certs` precisa conter `fullchain.pem` e `privkey.pem`, usados
como certificado padrão enquanto o Let's Encrypt não emite o próprio (por exemplo,
logo após trocar o DNS). Nunca versione esses arquivos.

```bash
docker compose -f compose.yml up -d
```

A emissão automática só funciona com o DNS do domínio apontando para este servidor.
Por isso o label `traefik.http.routers.mundial.tls.certresolver=letsencrypt` da aplicação
(`stacks/producao/compose.yml`) só deve ser ativado depois da troca do DNS; antes disso o
Traefik usa o certificado padrão de `/var/lib/mundial/traefik/certs`.
