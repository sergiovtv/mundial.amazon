FROM postgres:16-bookworm

RUN apt-get update \
    && apt-get install --no-install-recommends -y locales \
    && sed -i 's/^# *pt_BR.UTF-8 UTF-8/pt_BR.UTF-8 UTF-8/' /etc/locale.gen \
    && locale-gen \
    && rm -rf /var/lib/apt/lists/*

ENV LANG=pt_BR.UTF-8 \
    LC_ALL=pt_BR.UTF-8 \
    TZ=America/Sao_Paulo

COPY --chmod=0555 docker/postgres/init/10-create-databases.sh /docker-entrypoint-initdb.d/10-create-databases.sh

