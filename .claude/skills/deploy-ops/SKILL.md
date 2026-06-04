---
name: deploy-ops
description: Runbook a prueba de tontos para desplegar LendusFind en un servidor AlmaLinux 9 bare-metal (nginx + php-fpm + Reverb + queue + PostgreSQL + Redis). Audiencia DevOps con experiencia Linux. Cubre env vars como parámetros, SELinux, firewalld, script de deploy, rotación de secrets y troubleshooting AlmaLinux-specific.
---

# Deploy Ops — AlmaLinux 9 (bare-metal)

## Cuándo aplica

Usar este runbook al hacer un primer deploy de LendusFind a producción en
un servidor AlmaLinux 9 plano (sin Docker/K8s), o al onboardear un
servidor staging idéntico. Audiencia: DevOps con experiencia Linux/Nginx.
Si la infra cambia a containers o PaaS, esta skill queda como referencia
para los gotchas (SELinux, `config:cache`, php-fpm env) y la topología.

## Topología

Un solo servidor AlmaLinux 9 corriendo todo:

| Componente | Puerto | Notas |
|------------|--------|-------|
| Nginx | 80/443 | Sirve `dist/` per-tenant + reverse proxy a php-fpm y a Reverb |
| PHP-FPM 8.2 | unix socket | Backend Laravel |
| Reverb (WebSocket) | 8080 (loopback) | systemd service, proxy nginx en `/app` |
| Queue worker | — | systemd service |
| PostgreSQL 15 | 5432 (loopback) | Una DB compartida (multi-tenant lógico) |
| Redis | 6379 (loopback) | Cache + queue + sessions |

DNS apuntando todo al mismo IP:
- `apifind.lendus.app` → backend (HTTP/WebSocket)
- `moneycapital.lendus.app`, `finatea.lendus.app`, `demo.lendus.app` → estáticos del frontend per-tenant
- `lendus.app` → landing institucional

## 1. Preparación del servidor (one-time)

```bash
# Repos: EPEL + Remi (para PHP 8.2) + NodeSource
sudo dnf install -y epel-release
sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm
curl -fsSL https://rpm.nodesource.com/setup_20.x | sudo bash -

# PHP 8.2
sudo dnf module reset php -y
sudo dnf module enable php:remi-8.2 -y
sudo dnf install -y \
  php php-fpm php-cli \
  php-pgsql php-redis php-mbstring php-xml php-zip \
  php-bcmath php-intl php-curl php-gd php-process

# Node 20, nginx, redis, postgres, certbot, git
sudo dnf install -y nodejs nginx redis git certbot python3-certbot-nginx
sudo dnf install -y postgresql15-server postgresql15
sudo /usr/pgsql-15/bin/postgresql-15-setup initdb

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Servicios base
sudo systemctl enable --now nginx php-fpm redis postgresql-15
```

## 2. Usuario `deploy` y estructura de directorios

```bash
sudo useradd -m -s /bin/bash deploy
sudo usermod -aG nginx deploy
sudo mkdir -p /var/www/lendusfind/{backend,frontend-moneycapital,frontend-finatea,frontend-demo}
sudo chown -R deploy:nginx /var/www/lendusfind
sudo chmod -R 755 /var/www/lendusfind
```

## 3. PostgreSQL

```bash
sudo -u postgres psql <<SQL
CREATE USER lendusfind WITH PASSWORD 'CAMBIAR_PASSWORD_FUERTE';
CREATE DATABASE lendusfind OWNER lendusfind;
GRANT ALL PRIVILEGES ON DATABASE lendusfind TO lendusfind;
SQL
```

Editar `/var/lib/pgsql/15/data/pg_hba.conf` para que `lendusfind` use `md5` desde `127.0.0.1`. Reload:
```bash
sudo systemctl reload postgresql-15
```

## 4. Variables de entorno (paso clave — leer entero)

**Patrón**: env vars en `/etc/sysconfig/lendusfind-backend` (root-only, 600). Lo leen systemd (Reverb + queue) directamente vía `EnvironmentFile=`. PHP-FPM **no** lee env vars del sysconfig — Laravel toma sus valores del `bootstrap/cache/config.php` que se genera con `php artisan config:cache` durante el deploy, después de exportar las env vars al shell.

Crear `/etc/sysconfig/lendusfind-backend`:

```bash
sudo install -m 600 -o root -g root /dev/null /etc/sysconfig/lendusfind-backend
sudo $EDITOR /etc/sysconfig/lendusfind-backend
```

Contenido:

```dotenv
APP_NAME=LendusFind
APP_ENV=production
APP_KEY=                              # generar con `php artisan key:generate --show`
APP_DEBUG=false
APP_URL=https://apifind.lendus.app
APP_LOCALE=es

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=lendusfind
DB_USERNAME=lendusfind
DB_PASSWORD=CAMBIAR_PASSWORD_FUERTE

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SANCTUM_STATEFUL_DOMAINS=lendus.app,moneycapital.lendus.app,finatea.lendus.app,demo.lendus.app

QUEUE_CONNECTION=redis
CACHE_STORE=redis
BROADCAST_CONNECTION=reverb
FILESYSTEM_DISK=s3

REVERB_APP_ID=                        # generar valor único
REVERB_APP_KEY=                       # generar valor único
REVERB_APP_SECRET=                    # generar valor único
REVERB_HOST=apifind.lendus.app
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080

# Integraciones (rellenar con credenciales reales)
TWILIO_ACCOUNT_SID=
TWILIO_AUTH_TOKEN=
TWILIO_FROM_NUMBER=
TWILIO_WHATSAPP_FROM=

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="LendusFind"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=

OTP_USE_FIXED=false
```

### Por qué este patrón y no `.env`

| Forma | Pros | Cons |
|-------|------|------|
| `.env` en el repo | Familiar para Laravel | Permisos: lo lee `deploy` (que también ejecuta `git pull`). Si alguien hace `git status` con un secret pegado, queda en stage. Hay que recordar setear modo 600. |
| `/etc/sysconfig/lendusfind-backend` + `EnvironmentFile=` | Owner `root:root` 600, fuera del checkout. systemd lo lee directo. `config:cache` lo lee via `set -a; source ...`. Estándar AlmaLinux/RHEL. | Un paso más en deploy (export). |

Esta skill usa el segundo. Si preferís `.env`, igual el flujo funciona: solo cambia el paso del deploy script.

### ⚠ Gotcha de `config:cache`

Laravel cachea las env vars en el momento de correr `php artisan config:cache`. Después de eso, ni `.env` ni `EnvironmentFile=` afectan a las requests HTTP — todo sale de `bootstrap/cache/config.php`. El deploy script (sección 7) hace `set -a; source /etc/sysconfig/lendusfind-backend; set +a` **antes** de `config:cache`. Si rotás un secret y no re-corrés el deploy, los workers viejos siguen con el valor cacheado.

## 5. Backend Laravel — primer deploy

```bash
# Como deploy
sudo -u deploy git clone https://github.com/Hernai/lendus_find.git /var/www/lendusfind/backend
cd /var/www/lendusfind/backend/backend
sudo -u deploy composer install --no-dev --optimize-autoloader

# Generar APP_KEY y pegarla en /etc/sysconfig/lendusfind-backend
sudo -u deploy php artisan key:generate --show
sudo $EDITOR /etc/sysconfig/lendusfind-backend       # pegar APP_KEY=base64:...

# Primera corrida del config:cache (ver sección 7 para el script repetible)
set -a; source /etc/sysconfig/lendusfind-backend; set +a
sudo -u deploy -E php artisan config:cache
sudo -u deploy -E php artisan route:cache
sudo -u deploy -E php artisan view:cache
sudo -u deploy -E php artisan migrate --force
sudo -u deploy -E php artisan db:seed --force
sudo -u deploy php artisan storage:link
```

## 6. PHP-FPM pool dedicado

Crear `/etc/php-fpm.d/lendusfind.conf`:

```ini
[lendusfind]
user = deploy
group = nginx
listen = /var/run/php-fpm/lendusfind.sock
listen.owner = nginx
listen.group = nginx
listen.mode = 0660

pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500

; No carga env vars del sysconfig. Laravel las tiene en config:cache.
clear_env = yes

; Logs específicos del pool
access.log = /var/log/php-fpm/lendusfind.access.log
slowlog = /var/log/php-fpm/lendusfind.slow.log
request_slowlog_timeout = 5s

php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 50M
php_admin_value[post_max_size] = 50M
```

Desactivar el pool default si existe (`/etc/php-fpm.d/www.conf` → renombrar a `www.conf.disabled` o setear `listen` a otro socket). Reload:

```bash
sudo systemctl restart php-fpm
sudo systemctl status php-fpm
```

## 7. Script de deploy continuo

`/usr/local/bin/lendusfind-deploy.sh` (root-owned, +x):

```bash
#!/usr/bin/env bash
# Uso: lendusfind-deploy.sh [REF]
# REF default: origin/main. Pasar tag o branch específico para rollback.
set -euo pipefail

REF="${1:-origin/main}"
APP_DIR=/var/www/lendusfind/backend/backend
ENV_FILE=/etc/sysconfig/lendusfind-backend

echo "▶ Deploy LendusFind backend → $REF"

# Cargar env vars al shell (crítico para config:cache)
set -a
# shellcheck disable=SC1090
source "$ENV_FILE"
set +a

cd "$APP_DIR"
sudo -u deploy git fetch --all --tags --prune
sudo -u deploy git reset --hard "$REF"
sudo -u deploy composer install --no-dev --optimize-autoloader --no-interaction

# El -E preserva el env del shell para el subproceso
sudo -u deploy -E php artisan down --retry=15 || true
sudo -u deploy -E php artisan config:clear
sudo -u deploy -E php artisan config:cache
sudo -u deploy -E php artisan route:clear
sudo -u deploy -E php artisan route:cache
sudo -u deploy -E php artisan view:clear
sudo -u deploy -E php artisan view:cache
sudo -u deploy -E php artisan migrate --force
sudo -u deploy -E php artisan storage:link 2>/dev/null || true
sudo -u deploy -E php artisan up

# Reload servicios
sudo systemctl reload php-fpm
sudo systemctl restart lendusfind-reverb
sudo systemctl restart lendusfind-queue

echo "✓ Deploy completado a $(git rev-parse --short HEAD)"
```

```bash
sudo chmod 750 /usr/local/bin/lendusfind-deploy.sh
sudo chown root:root /usr/local/bin/lendusfind-deploy.sh
```

Permitir al usuario que va a invocarlo:

```bash
# /etc/sudoers.d/lendusfind-deploy
deploy ALL=(root) NOPASSWD: /usr/local/bin/lendusfind-deploy.sh
```

## 8. Reverb como systemd service

`/etc/systemd/system/lendusfind-reverb.service`:

```ini
[Unit]
Description=Laravel Reverb WebSocket server (LendusFind)
After=network-online.target redis.service postgresql-15.service
Requires=redis.service

[Service]
Type=simple
User=deploy
Group=deploy
WorkingDirectory=/var/www/lendusfind/backend/backend
EnvironmentFile=/etc/sysconfig/lendusfind-backend
ExecStart=/usr/bin/php artisan reverb:start --host=127.0.0.1 --port=8080
Restart=on-failure
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now lendusfind-reverb
```

## 9. Queue worker como systemd service

`/etc/systemd/system/lendusfind-queue.service`:

```ini
[Unit]
Description=Laravel queue worker (LendusFind)
After=network-online.target redis.service
Requires=redis.service

[Service]
Type=simple
User=deploy
Group=deploy
WorkingDirectory=/var/www/lendusfind/backend/backend
EnvironmentFile=/etc/sysconfig/lendusfind-backend
ExecStart=/usr/bin/php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --memory=256
Restart=on-failure
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now lendusfind-queue
```

## 10. Nginx — backend (apifind)

`/etc/nginx/conf.d/apifind.lendus.app.conf`:

```nginx
server {
    listen 80;
    server_name apifind.lendus.app;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name apifind.lendus.app;

    ssl_certificate /etc/letsencrypt/live/apifind.lendus.app/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/apifind.lendus.app/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    root /var/www/lendusfind/backend/backend/public;
    index index.php;

    client_max_body_size 50M;
    server_tokens off;

    access_log /var/log/nginx/apifind.access.log;
    error_log /var/log/nginx/apifind.error.log warn;

    # API requests
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php-fpm/lendusfind.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_read_timeout 60s;
    }

    # WebSocket Reverb
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_read_timeout 3600s;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
```

## 11. Frontend — build en CI, sirve nginx

**Importante**: NO buildear el frontend en el server de producción. El bundle pesa ~3.7 MB (Monaco) y requiere Node + ~8 GB de heap. Buildear en CI y rsync/scp el `dist/` al server.

### 11.1 Buildear en CI (GitHub Actions)

Ejemplo `.github/workflows/build-frontend.yml`:

```yaml
name: Build frontend per-tenant
on:
  push:
    branches: [main]
    paths: [frontend/**]
  workflow_dispatch:

jobs:
  build:
    strategy:
      matrix:
        tenant: [moneycapital, finatea, demo]
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: 20
          cache: npm
          cache-dependency-path: frontend/package-lock.json
      - run: npm ci
        working-directory: frontend
      - name: Build ${{ matrix.tenant }}
        working-directory: frontend
        env:
          VITE_API_URL: https://apifind.lendus.app/api
          VITE_APP_URL: https://lendus.app
          VITE_REVERB_HOST: apifind.lendus.app
          VITE_REVERB_PORT: 443
          VITE_REVERB_SCHEME: https
          VITE_REVERB_APP_KEY: ${{ secrets.REVERB_APP_KEY }}
        run: npm run tenant:build -- ${{ matrix.tenant }}
      - name: Upload to server
        env:
          DEPLOY_KEY: ${{ secrets.DEPLOY_SSH_KEY }}
          SERVER: ${{ secrets.DEPLOY_SERVER }}
        run: |
          eval "$(ssh-agent -s)"
          echo "$DEPLOY_KEY" | ssh-add -
          rsync -avz --delete \
            -e "ssh -o StrictHostKeyChecking=no" \
            frontend/dist/ \
            deploy@$SERVER:/var/www/lendusfind/frontend-${{ matrix.tenant }}/
```

### 11.2 Alternativa: build local + scp

```bash
cd frontend
VITE_API_URL=https://apifind.lendus.app/api \
VITE_APP_URL=https://lendus.app \
VITE_REVERB_HOST=apifind.lendus.app \
VITE_REVERB_PORT=443 \
VITE_REVERB_SCHEME=https \
VITE_REVERB_APP_KEY=$REVERB_KEY \
npm run tenant:build -- moneycapital

rsync -avz --delete dist/ deploy@server:/var/www/lendusfind/frontend-moneycapital/
```

### 11.3 Nginx — un solo bloque para los 3 tenants

`/etc/nginx/conf.d/tenants.lendus.app.conf`:

```nginx
server {
    listen 80;
    server_name moneycapital.lendus.app finatea.lendus.app demo.lendus.app lendus.app;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name moneycapital.lendus.app finatea.lendus.app demo.lendus.app;

    # Certificado wildcard o multi-SAN (issuit en sección 12)
    ssl_certificate /etc/letsencrypt/live/lendus.app/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/lendus.app/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;

    # Map subdominio → directorio per-tenant
    set $tenant "";
    if ($host = "moneycapital.lendus.app") { set $tenant "moneycapital"; }
    if ($host = "finatea.lendus.app")       { set $tenant "finatea"; }
    if ($host = "demo.lendus.app")          { set $tenant "demo"; }

    root /var/www/lendusfind/frontend-$tenant;
    index index.html;

    access_log /var/log/nginx/tenants.access.log;
    error_log /var/log/nginx/tenants.error.log warn;

    # SPA: fallback a index.html
    location / {
        try_files $uri $uri/ /index.html;
    }

    # PWA service worker — nunca cachear (el browser tiene que ver el sw nuevo)
    location = /sw.js {
        add_header Cache-Control "no-cache, no-store, must-revalidate";
        expires 0;
    }

    # Assets con hash en el nombre → cache long
    location ~* \.(?:js|css|woff2?|png|jpg|svg|ico)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

## 12. TLS con Let's Encrypt

```bash
# Backend
sudo certbot --nginx -d apifind.lendus.app

# Frontends + landing institucional
sudo certbot --nginx \
  -d lendus.app \
  -d moneycapital.lendus.app \
  -d finatea.lendus.app \
  -d demo.lendus.app

# Auto-renovación (ya viene como systemd timer)
sudo systemctl enable --now certbot-renew.timer
sudo systemctl list-timers | grep certbot
```

Para agregar tenants después: `sudo certbot --nginx -d <nuevo>.lendus.app --expand`.

## 13. SELinux (AlmaLinux 9 lo tiene activo por default)

```bash
# Backend Laravel: lectura
sudo semanage fcontext -a -t httpd_sys_content_t '/var/www/lendusfind/backend(/.*)?'
# Storage y bootstrap/cache: escritura
sudo semanage fcontext -a -t httpd_sys_rw_content_t '/var/www/lendusfind/backend/backend/storage(/.*)?'
sudo semanage fcontext -a -t httpd_sys_rw_content_t '/var/www/lendusfind/backend/backend/bootstrap/cache(/.*)?'
# Frontends estáticos
sudo semanage fcontext -a -t httpd_sys_content_t '/var/www/lendusfind/frontend-.+(/.*)?'

sudo restorecon -Rv /var/www/lendusfind

# Permitir a nginx/php-fpm conectar a redis, postgres y a Reverb en loopback
sudo setsebool -P httpd_can_network_connect 1
sudo setsebool -P httpd_can_network_connect_db 1
```

`semanage` viene en `policycoreutils-python-utils` si falta: `sudo dnf install -y policycoreutils-python-utils`.

## 14. Firewall

```bash
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --permanent --add-service=ssh
sudo firewall-cmd --reload
sudo firewall-cmd --list-all
```

PostgreSQL, Redis y Reverb solo escuchan en `127.0.0.1`, no necesitan exponerse.

## 15. Rotación de secrets

```bash
sudo $EDITOR /etc/sysconfig/lendusfind-backend
sudo /usr/local/bin/lendusfind-deploy.sh                 # re-corre config:cache
# Si solo cambia config de Reverb/queue:
sudo systemctl restart lendusfind-reverb lendusfind-queue
```

Para rotar `REVERB_APP_KEY` además hay que rebuildar el frontend con el valor nuevo (es público por diseño — el cliente Echo lo manda en el handshake).

## 16. Cómo pasar env vars en distintos contextos

| Contexto | Forma correcta | Cuándo |
|----------|----------------|--------|
| **Backend systemd (Reverb, queue)** | `EnvironmentFile=/etc/sysconfig/lendusfind-backend` en el unit file | Siempre que arranca el service |
| **Backend Laravel HTTP (php-fpm)** | `php artisan config:cache` después de `set -a; source /etc/sysconfig/lendusfind-backend; set +a` | En cada deploy. PHP-FPM lee el cache, no el env. |
| **Backend artisan one-off** (tinker, comandos manuales) | `sudo -u deploy -E /usr/bin/php artisan ...` con env exportado al shell del shell que invoca, o `EnvironmentFile`-equivalente | Solo al troubleshootear |
| **Frontend build local** | Inline antes del `npm run tenant:build`: `VITE_API_URL=... npm run tenant:build -- <slug>` | Builds ad-hoc |
| **Frontend build CI** | `env:` del job en GitHub Actions/GitLab CI, usando `secrets.*` | Pipeline automatizado |
| **Frontend runtime** | No aplica: las VITE vars quedan compiladas en el bundle JS. Para cambiarlas, rebuildar. | — |

> **Nunca** poner secrets en CLI args posicionales: aparecen en `ps aux` y en `history`. Siempre como env vars (no se ven).

## 17. Health-checks post-deploy

```bash
# Backend HTTP
curl -fs https://apifind.lendus.app/api/v2/public/health
# Reverb (vía nginx)
curl -fsI https://apifind.lendus.app/app/<REVERB_APP_KEY>
# Frontend (uno por tenant)
curl -fsI https://moneycapital.lendus.app/
# Queue worker procesando jobs
sudo -u deploy -E php /var/www/lendusfind/backend/backend/artisan queue:monitor redis
# Logs
sudo journalctl -u lendusfind-reverb -n 50 --no-pager
sudo journalctl -u lendusfind-queue -n 50 --no-pager
sudo tail -f /var/www/lendusfind/backend/backend/storage/logs/laravel.log
```

## 18. Rollback

```bash
# Deploy de tag/commit específico (el script ya lo soporta)
sudo /usr/local/bin/lendusfind-deploy.sh v1.2.3
sudo /usr/local/bin/lendusfind-deploy.sh abc1234
```

Para revertir migraciones rotas:
```bash
sudo -u deploy -E php artisan migrate:rollback --force
```

⚠ Las migraciones rollback solo si están escritas con `down()`. La política del repo es: cada migración debe tener `down()` no-destructivo (puede dejar columnas sin uso, pero no perder datos).

## 19. Troubleshooting AlmaLinux-specific

| Síntoma | Causa | Fix |
|---------|-------|-----|
| `502 Bad Gateway` en `/v2/...` | php-fpm socket no existe o sin permisos | `ls -la /var/run/php-fpm/lendusfind.sock`; ver que `listen.owner = nginx`, `listen.mode = 0660`; `systemctl restart php-fpm` |
| `403 Forbidden` en nginx sirviendo el frontend | SELinux niega lectura | `sudo restorecon -Rv /var/www/lendusfind`; verificar con `ls -lZ` que tenga `httpd_sys_content_t` |
| `500 Internal Server Error` tras cambiar `.env` | `config:cache` con valores viejos | Re-correr `lendusfind-deploy.sh` (hace `config:clear` + `config:cache`) |
| Laravel: `could not find driver pgsql` | falta `php-pgsql` | `dnf install -y php-pgsql && systemctl restart php-fpm` |
| Reverb: connection refused desde el cliente | Reverb escucha en `127.0.0.1:8080` pero nginx no proxea | Ver bloque `location /app` en el conf de `apifind.lendus.app.conf` |
| Queue: jobs encolados pero no procesados | Worker muerto o Redis no accesible | `systemctl status lendusfind-queue`; `journalctl -u lendusfind-queue` |
| `Class "Redis" not found` | falta `php-redis` o no se cargó el module | `dnf install -y php-redis && systemctl restart php-fpm` |
| `pcntl_signal()` warning en queue worker | falta `php-process` | `dnf install -y php-process` |
| nginx: `connect() to unix:/var/run/php-fpm/lendusfind.sock failed (13: Permission denied)` | SELinux | `sudo setsebool -P httpd_can_network_connect 1` y revisar `chcon` del socket dir |
| Frontend bundle aparece blanco / sin estilos | sw.js cacheó versión vieja | Forzar `Ctrl+Shift+R`, o verificar que nginx sirva `sw.js` con `no-cache` |
| Certbot falla con "challenge timeout" | DNS aún no propagó o firewall bloquea :80 | `dig +short <subdomain>`; `firewall-cmd --list-all` |
| `composer install` falla por OOM | VPS chico | Setear `COMPOSER_MEMORY_LIMIT=-1` o agregar swap |
| Reverb: `RuntimeException: A facade root has not been set.` | Reverb iniciado sin env file | Verificar `EnvironmentFile=` del unit y `systemctl daemon-reload` después de tocarlo |

## 20. Convención de versionado en deploy

- `main` siempre deployable → CI corre `npm run build` + `php artisan test` + `vue-tsc --build`.
- Tags `vX.Y.Z` para releases (semver). El script de deploy acepta tag o commit como argumento.
- Migraciones siempre `--force` en prod (Laravel pide confirmación interactiva si no).
- Modo mantenimiento (`php artisan down`) automático durante migraciones, levantado al final (`php artisan up`).

## Archivos clave de esta skill

- `/etc/sysconfig/lendusfind-backend` — env vars de prod (600 root:root)
- `/etc/php-fpm.d/lendusfind.conf` — pool dedicado
- `/etc/systemd/system/lendusfind-reverb.service`
- `/etc/systemd/system/lendusfind-queue.service`
- `/etc/nginx/conf.d/apifind.lendus.app.conf`
- `/etc/nginx/conf.d/tenants.lendus.app.conf`
- `/usr/local/bin/lendusfind-deploy.sh` — entrypoint del deploy
- `/var/www/lendusfind/` — root de la app
