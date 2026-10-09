# PequenaVia — Plano 4: Imagem de produção e stack Swarm

> **Para agentes:** SUB-SKILL OBRIGATÓRIA: `superpowers:subagent-driven-development`. Os passos usam checkbox (`- [ ]`).

**Objetivo:** empacotar o app Laravel numa imagem Docker de produção (PHP 8.5, Imagick com ImageMagick 7, Postgres, upload de 50 MB) e escrever a stack Docker Swarm (app + worker de fila + Postgres + RustFS + criação do bucket) no padrão que a VPS já usa (Traefik, `network_swarm_public`, Watchtower), **sem publicar nada**: build, testes dentro da imagem e fumaça são locais. Push da imagem, criação dos segredos e o deploy ficam com o dono do projeto.

**Arquitetura:** uma imagem multi-stage (`assets` → `vendor` → `runtime` → `test`) com FrankenPHP/Caddy em Alpine (um processo HTTP, sem nginx+fpm). O mesmo contêiner roda em três papéis conforme o comando: `web` (padrão), `queue` (`php artisan queue:work`, necessário porque `RemoverImagemModelo` é um job enfileirado) e tarefas de manutenção. O entrypoint só migra e aquece caches no papel `web`.

**Tech stack:** Docker (buildx local), `dunglas/frankenphp` (Alpine, PHP 8.5), `install-php-extensions` (imagick, pdo_pgsql, pcntl, opcache, intl, gd não é necessário), Node 22 (build do Vite+), Composer 2, Postgres 17, RustFS 1.0.1, Traefik (já existente).

## Restrições globais

- Locale `pt_BR` (`APP_LOCALE=pt_BR`), imagens de modelo até 50 MB: `docker/php/uploads.ini` (55M/60M/512M) precisa estar carregado no runtime, e o Caddy não pode limitar o corpo (`request_body` sem `max_size` menor que 60 MB).
- Imagick **contra ImageMagick 7** (os testes de referência do Plano 2 só valem para o 7; o Debian traz o 6, por isso Alpine).
- Nada de segredo na imagem nem no repositório: `.env` fica fora (`.dockerignore`), a stack usa `${VARIAVEL}` e `secrets`/variáveis do Portainer.
- `resources/fonts` e `lang/` entram na imagem (estão no repositório). `php artisan fontes:baixar` **não** roda em produção.
- Traefik: mesmo padrão da stack `pequenavia` atual (Host `certificados.pequenavia.app.br`, `websecure`, `certresolver=letsencryptresolver`, `network_swarm_public` externa, healthcheck, `update_config` start-first com rollback, label do Watchtower).
- Sessão, cache e fila em banco (`database`); `FILESYSTEM_DISK=local`; disco `s3` aponta para o RustFS interno com path style.
- Não alterar dependências do projeto. Convenções do `AGENTS.md` valem (pint `--dirty --format agent`).

---

### Task 1: Proxy confiável e arquivos de ambiente de produção

**Files:**

- Modify: `bootstrap/app.php`, `.env.example`
- Create: `tests/Feature/ProxyConfiavelTest.php`

**Interfaces:**

- Produces: o app atrás do Traefik gera URLs `https` e lê o IP do cliente de `X-Forwarded-*`; `.env.example` documenta as variáveis de produção (comentadas) sem mudar os valores de desenvolvimento.

- [ ] **Step 1: Teste que falha**

`tests/Feature/ProxyConfiavelTest.php` (use `php artisan make:test --pest ProxyConfiavelTest`):

```php
<?php

it('gera URLs https quando o proxy informa X-Forwarded-Proto', function () {
    $resposta = $this->withHeaders([
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'certificados.pequenavia.app.br',
        'X-Forwarded-For' => '203.0.113.7',
    ])->get('/login');

    $resposta->assertOk();

    expect(url('/login'))->toStartWith('https://');
});

it('confia no IP encaminhado pelo proxy', function () {
    $this->withHeaders(['X-Forwarded-For' => '203.0.113.7'])
        ->get('/login');

    expect(request()->ip())->toBe('203.0.113.7');
});
```

Rode `php artisan test --compact tests/Feature/ProxyConfiavelTest.php` e veja **falhar**.

- [ ] **Step 2: Confiar no proxy**

Em `bootstrap/app.php`, dentro de `withMiddleware`, antes de `encryptCookies`, acrescente (o app só é alcançável pela rede overlay do Traefik, então confiar em qualquer proxy é seguro):

```php
        $middleware->trustProxies(at: '*');
```

Rode o teste e veja **passar**. Se o segundo teste falhar porque `request()` não é a requisição do teste, troque por uma rota de teste registrada no próprio arquivo de teste (`Route::get('/_ip', fn (Request $r) => $r->ip())`) e assevere o corpo.

- [ ] **Step 3: Documentar variáveis de produção**

No fim de `.env.example`, acrescente este bloco (apenas comentários):

```dotenv

# --- Produção (valores reais ficam na stack, nunca no repositório) ---
# APP_ENV=production
# APP_DEBUG=false
# APP_URL=https://certificados.pequenavia.app.br
# LOG_CHANNEL=stderr
# LOG_LEVEL=info
# DB_HOST=postgres
# DB_PORT=5432
# AWS_ENDPOINT=http://rustfs:9000
# GOOGLE_REDIRECT_URI=https://certificados.pequenavia.app.br/auth/google/callback
# MAIL_MAILER=smtp  (e-mail de verificação e de redefinição de senha precisam de SMTP real)
```

- [ ] **Step 4: Verificar e commitar**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
git add bootstrap/app.php .env.example tests/Feature/ProxyConfiavelTest.php
git commit -m "feat: confia no proxy do Traefik e documenta variáveis de produção"
```

---

### Task 2: Imagem de produção (Dockerfile, Caddy, entrypoint) e testes dentro dela

**Files:**

- Create: `Dockerfile`, `.dockerignore`, `docker/Caddyfile`, `docker/entrypoint.sh`

**Interfaces:**

- Consumes: Task 1; `docker/php/uploads.ini`; `compose.yaml` (Postgres 127.0.0.1:54320 e RustFS 127.0.0.1:9010 de desenvolvimento, para os testes).
- Produces: alvos de build `runtime` (padrão) e `test`; imagem escuta na porta **8080** sem TLS; `GET /up` responde 200; comandos suportados: `web` (padrão), `queue`, ou qualquer comando arbitrário.

- [ ] **Step 1: `.dockerignore`**

```
.git
.github
.superpowers
.env
.env.*
!.env.example
node_modules
vendor
public/build
public/hot
storage/logs/*
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/views/*
tests/Fixtures/saida
docs
*.log
```

Antes de gravar, confira com `git ls-files tests | head` e `ls tests` se alguma pasta de `tests/` é necessária só no alvo `test` (ela precisa ir na imagem de teste, então **não** a ignore).

- [ ] **Step 2: `docker/Caddyfile`**

```
{
	frankenphp
	auto_https off
	servers {
		trusted_proxies static private_ranges
	}
}

:8080 {
	root * /app/public
	encode zstd gzip
	request_body {
		max_size 64MB
	}
	header {
		-Server
		X-Content-Type-Options nosniff
	}
	php_server
}
```

(Se a diretiva `request_body`/`max_size` não for aceita pela versão do Caddy da imagem, use `request_body { max_size 64MB }` conforme `caddy adapt` indicar; o requisito é aceitar corpos de até 60 MB.)

- [ ] **Step 3: `docker/entrypoint.sh`**

```sh
#!/bin/sh
set -e

cd /app

papel="${1:-web}"

case "$papel" in
  web)
    php artisan migrate --force
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
    exec frankenphp run --config /etc/caddy/Caddyfile --adapter caddyfile
    ;;
  queue)
    php artisan config:cache
    exec php artisan queue:work --sleep=3 --tries=3 --max-time=3600
    ;;
  *)
    exec "$@"
    ;;
esac
```

- [ ] **Step 4: `Dockerfile`**

```dockerfile
# syntax=docker/dockerfile:1

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
# O Wayfinder gera rotas TypeScript chamando o artisan; precisa de PHP e vendor.
COPY --from=vendor-dev /app/vendor ./vendor
RUN npm run build

FROM composer:2 AS vendor-dev
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --no-scripts --prefer-dist --ignore-platform-req=ext-imagick

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist --optimize-autoloader --ignore-platform-req=ext-imagick

FROM dunglas/frankenphp:1-php8.5-alpine AS base
RUN install-php-extensions imagick pdo_pgsql pcntl opcache intl zip \
    && apk add --no-cache font-noto-emoji || true
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/zz-uploads.ini
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint
WORKDIR /app
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr SERVER_NAME=:8080
EXPOSE 8080

FROM base AS runtime
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && composer --version >/dev/null 2>&1 || true
USER www-data
HEALTHCHECK --interval=15s --timeout=5s --start-period=40s --retries=3 \
    CMD wget -qO- http://127.0.0.1:8080/up >/dev/null || exit 1
ENTRYPOINT ["entrypoint"]
CMD ["web"]

FROM base AS test
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . .
COPY --from=vendor-dev /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build
ENV APP_ENV=testing
CMD ["php", "artisan", "test", "--compact"]
```

Ajustes que o implementador deve fazer ao compilar (registre-os no relatório): ordem dos estágios (`vendor-dev` precisa vir antes de `assets` no arquivo, o Docker exige a definição antes do uso), pacotes Alpine necessários ao Wayfinder/artisan no estágio `assets` (o estágio Node não tem PHP: se `npm run build` precisar do artisan, troque a base do estágio `assets` por `base` + `apk add nodejs npm`, ou gere o Wayfinder no estágio PHP e copie `resources/js/{actions,routes,wayfinder}`), `HEALTHCHECK` com a ferramenta que existe na imagem (`wget` do busybox ou `curl`), e remova a linha `|| true` do `font-noto-emoji` se ela não for necessária (as fontes dos certificados vêm de `resources/fonts`).

- [ ] **Step 5: Build e testes dentro da imagem**

```bash
docker compose up -d postgres rustfs rustfs-perms s3-init
docker build --target test -t pequenavia-test .
docker run --rm --add-host=host.docker.internal:host-gateway \
  -e DB_HOST=host.docker.internal -e DB_PORT=54320 -e DB_USERNAME=pequenavia -e DB_PASSWORD=secret \
  -e AWS_ENDPOINT=http://host.docker.internal:9010 -e AWS_ACCESS_KEY_ID=pequenavia -e AWS_SECRET_ACCESS_KEY=pequenavia-secret \
  pequenavia-test
```

Esperado: a suíte inteira passa, **incluindo** os testes de referência do gerador (não pode haver `skipped` por ImageMagick 6). Confira dentro da imagem: `docker run --rm --entrypoint php pequenavia-test -r 'echo Imagick::getVersion()["versionString"];'` mostra `ImageMagick 7.`, e `php -i | grep -E "upload_max_filesize|post_max_size"` mostra 55M e 60M. Se os testes dependerem de variáveis de ambiente que o `phpunit.xml` não define, passe-as com `-e` (não altere o `phpunit.xml` para isso).

- [ ] **Step 6: Build do runtime e teste de fumaça**

```bash
docker build --target runtime -t pequenavia:smoke .
docker run -d --name pv-smoke -p 18080:8080 --add-host=host.docker.internal:host-gateway \
  -e APP_KEY=base64:$(php -r 'echo base64_encode(random_bytes(32));') \
  -e APP_URL=http://localhost:18080 \
  -e DB_HOST=host.docker.internal -e DB_PORT=54320 -e DB_DATABASE=pequenavia -e DB_USERNAME=pequenavia -e DB_PASSWORD=secret \
  -e AWS_ENDPOINT=http://host.docker.internal:9010 -e AWS_ACCESS_KEY_ID=pequenavia -e AWS_SECRET_ACCESS_KEY=pequenavia-secret \
  -e AWS_BUCKET=modelos-certificado -e AWS_USE_PATH_STYLE_ENDPOINT=true \
  pequenavia:smoke
sleep 15
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:18080/up        # 200
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:18080/login     # 200
curl -s http://localhost:18080/login | grep -c "build/assets"             # > 0
curl -s -o /dev/null -w "%{http_code}\n" -H 'Content-Type: application/octet-stream' --data-binary @<(head -c 58000000 /dev/zero) -X POST http://localhost:18080/modelos   # 419 ou 302, nunca 413
docker exec pv-smoke php artisan fontes:listar 2>/dev/null || docker exec pv-smoke ls resources/fonts | head
docker exec pv-smoke php artisan queue:work --once --stop-when-empty
docker logs pv-smoke | tail -20
docker rm -f pv-smoke
```

Esperado: 200 em `/up` e `/login`, assets do Vite referenciados, corpo de 58 MB **não** retorna 413 (o 419 de CSRF prova que o corpo foi aceito), fontes presentes, worker roda sem erro. O banco de desenvolvimento é usado e migrado; isso é aceitável localmente.

- [ ] **Step 7: Commitar**

```bash
git add Dockerfile .dockerignore docker/Caddyfile docker/entrypoint.sh
git commit -m "feat: imagem de produção com FrankenPHP, Imagick 7 e testes dentro da imagem"
```

---

### Task 3: Stack Swarm

**Files:**

- Create: `deploy/stack.yml`

**Interfaces:**

- Consumes: imagem `jpbetanza/pequenavia-laravel:latest` (Task 2); rede externa `network_swarm_public`.
- Produces: stack `pequenavia-laravel` com serviços `app`, `queue`, `postgres`, `rustfs`, `s3-init`; todas as credenciais vêm de variáveis `${...}` da stack (Portainer).

- [ ] **Step 1: Escrever `deploy/stack.yml`**

```yaml
# Stack Swarm do PequenaVia (Laravel). Variáveis obrigatórias (definir no Portainer):
#   APP_KEY, DB_PASSWORD, S3_ACCESS_KEY, S3_SECRET_KEY,
#   GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET,
#   MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS
# Antes do primeiro deploy: publicar a imagem (docker push jpbetanza/pequenavia-laravel:latest).
version: '3.7'

x-app-env: &app-env
    APP_NAME: PequenaVia
    APP_ENV: production
    APP_DEBUG: 'false'
    APP_KEY: ${APP_KEY}
    APP_URL: https://certificados.pequenavia.app.br
    APP_LOCALE: pt_BR
    LOG_CHANNEL: stderr
    LOG_LEVEL: info
    DB_CONNECTION: pgsql
    DB_HOST: postgres
    DB_PORT: '5432'
    DB_DATABASE: pequenavia
    DB_USERNAME: pequenavia
    DB_PASSWORD: ${DB_PASSWORD}
    SESSION_DRIVER: database
    SESSION_SECURE_COOKIE: 'true'
    QUEUE_CONNECTION: database
    CACHE_STORE: database
    FILESYSTEM_DISK: local
    AWS_ACCESS_KEY_ID: ${S3_ACCESS_KEY}
    AWS_SECRET_ACCESS_KEY: ${S3_SECRET_KEY}
    AWS_DEFAULT_REGION: us-east-1
    AWS_BUCKET: modelos-certificado
    AWS_ENDPOINT: http://rustfs:9000
    AWS_USE_PATH_STYLE_ENDPOINT: 'true'
    GOOGLE_CLIENT_ID: ${GOOGLE_CLIENT_ID}
    GOOGLE_CLIENT_SECRET: ${GOOGLE_CLIENT_SECRET}
    GOOGLE_REDIRECT_URI: https://certificados.pequenavia.app.br/auth/google/callback
    MAIL_MAILER: smtp
    MAIL_HOST: ${MAIL_HOST}
    MAIL_PORT: ${MAIL_PORT}
    MAIL_USERNAME: ${MAIL_USERNAME}
    MAIL_PASSWORD: ${MAIL_PASSWORD}
    MAIL_FROM_ADDRESS: ${MAIL_FROM_ADDRESS}
    MAIL_FROM_NAME: PequenaVia

services:
    app:
        image: jpbetanza/pequenavia-laravel:latest
        command: ['web']
        environment: *app-env
        networks:
            - network_swarm_public
            - interna
        healthcheck:
            test: ['CMD', 'wget', '-qO-', 'http://127.0.0.1:8080/up']
            interval: 15s
            timeout: 5s
            retries: 3
            start_period: 60s
        deploy:
            mode: replicated
            replicas: 1
            restart_policy:
                condition: on-failure
            update_config:
                parallelism: 1
                delay: 10s
                order: start-first
                failure_action: rollback
                monitor: 30s
            rollback_config:
                parallelism: 1
                delay: 5s
                order: start-first
            labels:
                - 'com.centurylinklabs.watchtower.enable=true'
                - 'traefik.enable=true'
                - 'traefik.docker.network=network_swarm_public'
                - 'traefik.http.routers.pequenavia-laravel.rule=Host(`certificados.pequenavia.app.br`)'
                - 'traefik.http.routers.pequenavia-laravel.entrypoints=websecure'
                - 'traefik.http.routers.pequenavia-laravel.tls=true'
                - 'traefik.http.routers.pequenavia-laravel.tls.certresolver=letsencryptresolver'
                - 'traefik.http.routers.pequenavia-laravel.service=pequenavia-laravel'
                - 'traefik.http.services.pequenavia-laravel.loadbalancer.server.port=8080'

    queue:
        image: jpbetanza/pequenavia-laravel:latest
        command: ['queue']
        environment: *app-env
        networks:
            - interna
        deploy:
            mode: replicated
            replicas: 1
            restart_policy:
                condition: any
            labels:
                - 'com.centurylinklabs.watchtower.enable=true'

    postgres:
        image: postgres:17
        environment:
            POSTGRES_DB: pequenavia
            POSTGRES_USER: pequenavia
            POSTGRES_PASSWORD: ${DB_PASSWORD}
        volumes:
            - pequenavia_pg:/var/lib/postgresql/data
        networks:
            - interna
        healthcheck:
            test: ['CMD-SHELL', 'pg_isready -U pequenavia']
            interval: 10s
            retries: 10
        deploy:
            mode: replicated
            replicas: 1
            restart_policy:
                condition: on-failure

    rustfs:
        image: rustfs/rustfs:1.0.1
        # O volume precisa pertencer ao uid 10001; ver o serviço rustfs-perms.
        environment:
            RUSTFS_ACCESS_KEY: ${S3_ACCESS_KEY}
            RUSTFS_SECRET_KEY: ${S3_SECRET_KEY}
            RUSTFS_ADDRESS: ':9000'
            RUSTFS_CONSOLE_ENABLE: 'false'
        volumes:
            - pequenavia_s3:/data
        networks:
            - interna
        deploy:
            mode: replicated
            replicas: 1
            restart_policy:
                condition: on-failure

    rustfs-perms:
        image: alpine:3
        user: root
        command: chown -R 10001:10001 /data
        volumes:
            - pequenavia_s3:/data
        deploy:
            mode: replicated
            replicas: 1
            restart_policy:
                condition: none

    s3-init:
        image: amazon/aws-cli:latest
        environment:
            AWS_ACCESS_KEY_ID: ${S3_ACCESS_KEY}
            AWS_SECRET_ACCESS_KEY: ${S3_SECRET_KEY}
            AWS_DEFAULT_REGION: us-east-1
        entrypoint: >
            /bin/sh -c "
            until aws --endpoint-url http://rustfs:9000 s3api list-buckets; do sleep 3; done;
            aws --endpoint-url http://rustfs:9000 s3api head-bucket --bucket modelos-certificado
            || aws --endpoint-url http://rustfs:9000 s3api create-bucket --bucket modelos-certificado;
            "
        networks:
            - interna
        deploy:
            mode: replicated
            replicas: 1
            restart_policy:
                condition: none

volumes:
    pequenavia_pg:
    pequenavia_s3:

networks:
    network_swarm_public:
        external: true
        name: network_swarm_public
    interna:
        driver: overlay
```

Observações para o implementador (confira, não invente): o healthcheck do `app` e da imagem usam a mesma ferramenta (`wget`); `rustfs-perms` e `s3-init` rodam uma vez (`condition: none`) e o `rustfs` pode reiniciar até o volume ter dono, o que é aceitável.

- [ ] **Step 2: Validar o arquivo**

```bash
docker compose -f deploy/stack.yml config >/dev/null   # sintaxe (ignora deploy:, avisa sobre variáveis)
APP_KEY=x DB_PASSWORD=x S3_ACCESS_KEY=x S3_SECRET_KEY=x GOOGLE_CLIENT_ID=x GOOGLE_CLIENT_SECRET=x MAIL_HOST=x MAIL_PORT=1 MAIL_USERNAME=x MAIL_PASSWORD=x MAIL_FROM_ADDRESS=x@x.com docker compose -f deploy/stack.yml config | head -40
grep -n "SECRET\|PASSWORD" deploy/stack.yml | grep -v '\${' | grep -v "SECRET_ACCESS_KEY\|RUSTFS_SECRET_KEY: \${"
```

Esperado: o `config` renderiza sem erro; a última busca não mostra nenhum valor literal de segredo.

- [ ] **Step 3: Commitar**

```bash
git add deploy/stack.yml
git commit -m "feat: stack Swarm com app, worker, Postgres e RustFS"
```

---

## Verificação final do Plano 4

- [ ] Suíte inteira verde **dentro** do alvo `test` (sem testes pulados por ImageMagick 6), e `php artisan test` verde no host.
- [ ] Fumaça do runtime: `/up` e `/login` respondem 200, corpo de ~58 MB não dá 413, fila processa.
- [ ] `docker compose -f deploy/stack.yml config` válido; nenhum segredo literal no repositório (`git grep -n "secret" -- deploy Dockerfile docker` só mostra nomes de variáveis).
- [ ] Branch integrada em `main`, `git status` limpo.

## O que fica com o dono do projeto (não automatizável aqui)

1. `docker login` e `docker push jpbetanza/pequenavia-laravel:latest` (após `docker build --target runtime -t jpbetanza/pequenavia-laravel:latest .`).
2. Definir no Portainer as variáveis da stack (APP_KEY gerado com `php artisan key:generate --show`, senhas novas, credenciais do Google, SMTP).
3. No Google Cloud Console: acrescentar `https://certificados.pequenavia.app.br/auth/google/callback` às URIs de redirecionamento autorizadas (e **rotacionar** o segredo de OAuth usado no desenvolvimento).
4. Cutover: a stack `pequenavia` (Next.js + Supabase) usa o mesmo domínio; pare-a antes de subir a nova (começando do zero, não há dados a migrar).
