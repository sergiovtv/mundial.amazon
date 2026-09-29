# SFTP de fotos e documentos

Esta stack publica um servidor SFTP para envio das fotos dos imóveis e dos
documentos da Área do Cliente. Ela não depende da aplicação nem do PostgreSQL e
pode ser reiniciada separadamente.

O usuário `mundial` fica restrito (chroot) à própria pasta e enxerga somente:

| Pasta no SFTP | Diretório no servidor                        | Uso na aplicação              |
| ------------- | -------------------------------------------- | ----------------------------- |
| `/fotos`      | `/var/lib/mundial/media/_fotos_1020304050`   | Originais das fotos (leitura) |
| `/documentos` | `/var/lib/mundial/documentos`                | Boletos e PDFs (leitura)      |

O cache `CacheFotos` não é exposto. Ao substituir uma foto já publicada, remova a
cópia correspondente em `CacheFotos` para que a marca d'água seja gerada de novo.

## Antes de iniciar

1. Copie `.env.example` para `.env` e gere o hash da senha:

   ```bash
   openssl passwd -6
   ```

   Cole o resultado em `SFTP_USERS`, mantendo as aspas simples.

2. Gere as chaves do servidor uma única vez, para que o fingerprint não mude a
   cada recriação do container:

   ```bash
   install -d -m 0700 /var/lib/mundial/sftp/host-keys
   ssh-keygen -t ed25519 -N '' -f /var/lib/mundial/sftp/host-keys/ssh_host_ed25519_key
   ssh-keygen -t rsa -b 4096 -N '' -f /var/lib/mundial/sftp/host-keys/ssh_host_rsa_key
   ```

3. Dê ao UID/GID `1001` (definido em `SFTP_USERS`) permissão de escrita nas pastas:

   ```bash
   chown -R 1001:1001 /var/lib/mundial/media/_fotos_1020304050 /var/lib/mundial/documentos
   chmod -R u+rwX,go+rX /var/lib/mundial/media/_fotos_1020304050 /var/lib/mundial/documentos
   ```

   A aplicação (`www-data`) continua lendo os arquivos, pois eles ficam legíveis
   para todos e são montados como somente leitura no container da aplicação.

4. Libere a porta `SFTP_PORT` (padrão `2222`) no firewall, de preferência apenas
   para os IPs que vão enviar arquivos.

## Execução

```bash
docker compose -f compose.yml up -d
```

## Conexão

- Protocolo: SFTP
- Host: IP ou domínio do servidor
- Porta: `2222`
- Usuário: `mundial`

Nunca versione `.env` nem as chaves em `/var/lib/mundial/sftp`.
