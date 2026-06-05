---
name: deploy-ops
description: Runbook a prueba de tontos para desplegar LendusFind en un servidor AlmaLinux 9 bare-metal (nginx + php-fpm + Reverb + queue + PostgreSQL + Redis). Audiencia DevOps con experiencia Linux. Cubre env vars como parámetros, SELinux, firewalld, script de deploy, rotación de secrets y troubleshooting AlmaLinux-specific.
---

# Deploy Ops — AlmaLinux 9 (bare-metal)

## Cuándo aplica

Usar este runbook al hacer un primer deploy de LendusFind a producción en
un servidor AlmaLinux 9 plano (sin Docker/K8s), o al onboardear un
servidor staging idéntico. Audiencia: DevOps con experiencia Linux.
Si la infra cambia a containers o PaaS, esta skill queda como referencia
para los gotchas (SELinux, `config:cache`, php-fpm env) y la topología.

**Variante del web server**: el runbook cubre **dos sabores** del mismo
deploy:

- **Variante Nginx** (secciones 10–11): standalone/VPS sin panel.
- **Variante Apache** (sección 21): cuando el servidor ya tiene Apache
  con cPanel/WHM/Plesk u otro stack heredado. Cubre los VirtualHosts del
  backend y los frontends, el WebSocket proxy (`mod_proxy_wstunnel`) y
  los gotchas SELinux específicos de `httpd_t`.

Elegí una de las dos según tu setup y saltá la otra. El resto del
runbook (Postgres, Redis, env vars, systemd, SELinux base) aplica igual
para ambos.

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

# Warmup del cache publico — pre-popula v2:config:* y v2:manifest:* para
# que el primer usuario tras el deploy no pague el cold de ~1s. El reload
# de FPM arriba mato los workers, OPCache se llena en el primer hit;
# este warmup hace ese primer hit nosotros, no el usuario.
sudo -u deploy -E php artisan cache:warmup --force || true

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

## 21. Variante Apache (httpd) — backend + frontends

Usar esta variante cuando el server tiene Apache instalado (típico de
cPanel/WHM, Plesk, hosting administrado o stacks heredados). Saltea las
secciones 10–11 si estás acá; lo demás (Postgres, Redis, env vars,
systemd, SELinux base, firewall) aplica igual.

### 21.1 Módulos requeridos

```bash
# AlmaLinux 9 — Apache (httpd) viene con el core, falta wstunnel
sudo dnf install -y httpd mod_ssl
# mod_proxy_wstunnel suele venir con httpd pero hay que cargarlo
httpd -M 2>&1 | grep -E "proxy_module|proxy_http|proxy_wstunnel|rewrite|ssl"
```

Tenés que ver estos 5: `proxy_module`, `proxy_http_module`,
`proxy_wstunnel_module`, `rewrite_module`, `ssl_module`. Si falta alguno,
editá `/etc/httpd/conf.modules.d/00-proxy.conf`:

```apache
LoadModule proxy_module modules/mod_proxy.so
LoadModule proxy_http_module modules/mod_proxy_http.so
LoadModule proxy_wstunnel_module modules/mod_proxy_wstunnel.so
LoadModule rewrite_module modules/mod_rewrite.so
```

Y `/etc/httpd/conf.modules.d/00-ssl.conf`:
```apache
LoadModule ssl_module modules/mod_ssl.so
```

### 21.2 VirtualHost del backend — `apifind.lendus.app`

Crear `/etc/httpd/conf.d/apifind.lendus.app.conf`:

```apache
# Redirect HTTP → HTTPS
<VirtualHost *:80>
    ServerName apifind.lendus.app
    Redirect permanent / https://apifind.lendus.app/
</VirtualHost>

<VirtualHost *:443>
    ServerName apifind.lendus.app
    DocumentRoot /var/www/lendusfind/backend/backend/public

    SSLEngine on
    SSLCertificateFile      /etc/letsencrypt/live/apifind.lendus.app/fullchain.pem
    SSLCertificateKeyFile   /etc/letsencrypt/live/apifind.lendus.app/privkey.pem
    Include /etc/letsencrypt/options-ssl-apache.conf

    ServerTokens Prod
    ServerSignature Off

    ErrorLog  /var/log/httpd/apifind.error.log
    CustomLog /var/log/httpd/apifind.access.log combined

    # ===========================================
    # WebSocket Reverb — proxypear /app a 127.0.0.1:8080
    # IMPORTANTE: este bloque va ANTES del handler PHP, sino Apache lo
    # entrega a Laravel y devuelve 404.
    # ===========================================
    RewriteEngine On
    RewriteCond %{HTTP:Upgrade} =websocket [NC]
    RewriteRule ^/app/?(.*) ws://127.0.0.1:8080/app/$1 [P,L]
    RewriteCond %{HTTP:Upgrade} !=websocket [NC]
    RewriteRule ^/app/?(.*) http://127.0.0.1:8080/app/$1 [P,L]
    ProxyPreserveHost On
    ProxyRequests Off
    ProxyTimeout 3600

    # ===========================================
    # Laravel — todo lo demás (REST API)
    # ===========================================
    <Directory /var/www/lendusfind/backend/backend/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        # PHP-FPM via Unix socket (configurado en sección 6)
        <FilesMatch \.php$>
            SetHandler "proxy:unix:/var/run/php-fpm/lendusfind.sock|fcgi://localhost/"
        </FilesMatch>
    </Directory>

    # Subir el límite si Laravel sube docs grandes
    LimitRequestBody 52428800
</VirtualHost>
```

> **`.htaccess` de Laravel**: el `public/.htaccess` de Laravel ya hace el
> rewriting a `index.php`. Como pusimos `AllowOverride All`, Apache lo
> procesa. No hace falta duplicar reglas.

### 21.3 VirtualHost de los frontends — `*.lendus.app`

Crear `/etc/httpd/conf.d/tenants.lendus.app.conf`:

```apache
<VirtualHost *:80>
    ServerName lendus.app
    ServerAlias moneycapital.lendus.app finatea.lendus.app demo.lendus.app
    Redirect permanent / https://%{HTTP_HOST}/
</VirtualHost>

# Bloque común a los 3 tenants: el directorio del DocumentRoot se
# resuelve dinámicamente vía SetEnvIf según el Host.
<VirtualHost *:443>
    ServerName moneycapital.lendus.app
    ServerAlias finatea.lendus.app demo.lendus.app

    SSLEngine on
    SSLCertificateFile      /etc/letsencrypt/live/lendus.app/fullchain.pem
    SSLCertificateKeyFile   /etc/letsencrypt/live/lendus.app/privkey.pem
    Include /etc/letsencrypt/options-ssl-apache.conf

    # Map Host → tenant dir
    SetEnvIf Host "moneycapital.lendus.app" TENANT_DIR=frontend-moneycapital
    SetEnvIf Host "finatea.lendus.app"       TENANT_DIR=frontend-finatea
    SetEnvIf Host "demo.lendus.app"          TENANT_DIR=frontend-demo

    # DocumentRoot resuelto por env var
    DocumentRoot /var/www/lendusfind/${TENANT_DIR}

    ErrorLog  /var/log/httpd/tenants.error.log
    CustomLog /var/log/httpd/tenants.access.log combined

    <Directory /var/www/lendusfind/frontend-moneycapital>
        AllowOverride None
        Require all granted
        Options -Indexes
    </Directory>
    <Directory /var/www/lendusfind/frontend-finatea>
        AllowOverride None
        Require all granted
        Options -Indexes
    </Directory>
    <Directory /var/www/lendusfind/frontend-demo>
        AllowOverride None
        Require all granted
        Options -Indexes
    </Directory>

    # ===========================================
    # Cache headers — clave para evitar bugs de bundle stale en deploys
    # ===========================================
    # SW: nunca cachear (el browser tiene que ver el SW nuevo en cada deploy)
    <LocationMatch "^/sw\.js$">
        Header set Cache-Control "no-cache, no-store, must-revalidate"
        Header set Expires "0"
    </LocationMatch>

    # index.html: tampoco cachear (apunta a chunks con hash variable)
    <LocationMatch "^/(index\.html)?$">
        Header set Cache-Control "no-cache, no-store, must-revalidate"
        Header set Expires "0"
    </LocationMatch>

    # Assets con hash en el nombre: cache larga (immutable)
    <LocationMatch "\.(?:js|css|woff2?|png|jpg|jpeg|svg|ico)$">
        Header set Cache-Control "public, max-age=31536000, immutable"
    </LocationMatch>

    # ===========================================
    # SPA fallback: /assets/* directo; cualquier otra ruta → index.html
    # IMPORTANTE: A nivel VirtualHost, %{REQUEST_FILENAME} NO es la ruta
    # absoluta del filesystem (solo lo es dentro de .htaccess). Si usas
    # REQUEST_FILENAME aquí, las condiciones -f y -d siempre dan falso y
    # TODA petición termina en index.html (incluso assets que sí existen).
    # Hay que componer la ruta con DOCUMENT_ROOT + REQUEST_URI.
    # ===========================================
    RewriteEngine On
    RewriteCond %{DOCUMENT_ROOT}%{REQUEST_URI} -f [OR]
    RewriteCond %{DOCUMENT_ROOT}%{REQUEST_URI} -d
    RewriteRule ^ - [L]
    RewriteRule ^ /index.html [L]
</VirtualHost>
```

Requiere `mod_headers` (suele venir): `httpd -M | grep headers_module`.

### 21.4 PHP-FPM con Apache

La sección 6 ya configura el pool PHP-FPM con socket Unix en
`/var/run/php-fpm/lendusfind.sock`. En Apache, el `SetHandler` del
VirtualHost lo usa directamente. No hace falta `mod_php` (ojo: si está
instalado, deshabilítarlo con `dnf remove php` y mantener solo `php-fpm`).

### 21.5 SELinux específico de Apache

Las reglas de la sección 13 ya usan `httpd_sys_content_t` y
`httpd_sys_rw_content_t`, que son los contextos correctos para Apache.
**No hay que cambiar nada** — los mismos `chcon`/`setsebool` funcionan
idénticos con httpd.

Boolean específico para `mod_proxy_wstunnel` + Reverb local:
```bash
sudo setsebool -P httpd_can_network_relay 1
```

### 21.6 TLS con Let's Encrypt (Apache)

```bash
sudo dnf install -y python3-certbot-apache
sudo certbot --apache -d apifind.lendus.app
sudo certbot --apache -d lendus.app -d moneycapital.lendus.app -d finatea.lendus.app -d demo.lendus.app
```

Certbot edita los VirtualHosts automáticamente. Si ya los tenés escritos
a mano (como arriba), usá `--apache --reinstall` o `certonly` + agregás
las directivas SSL vos.

### 21.7 Habilitar y arrancar

```bash
sudo apachectl configtest          # verifica syntax — debe imprimir "Syntax OK"
sudo systemctl enable --now httpd
sudo systemctl reload httpd        # para apply de cambios
```

### 21.8 Health-checks específicos Apache

```bash
# ¿Está sirviendo?
curl -fsI https://apifind.lendus.app/api/v2/public/health
curl -fsI https://moneycapital.lendus.app/

# ¿Proxy WebSocket funciona? Handshake manual:
curl -i -N --max-time 5 \
  -H "Connection: Upgrade" \
  -H "Upgrade: websocket" \
  -H "Sec-WebSocket-Version: 13" \
  -H "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==" \
  "https://apifind.lendus.app/app/<REVERB_APP_KEY>"
# Esperar: HTTP/1.1 101 Switching Protocols

# Logs
sudo tail -f /var/log/httpd/apifind.error.log
sudo tail -f /var/log/httpd/tenants.error.log
```

### 21.9 Troubleshooting Apache-specific

| Síntoma | Causa | Fix |
|---------|-------|-----|
| `curl` al `/app/...` devuelve `404 Not Found` con HTML de Laravel | Apache no proxypea `/app`, lo pasa a PHP | Verificar que el bloque `RewriteRule` `^/app` esté ANTES del handler PHP en el VirtualHost de `apifind` |
| `502 Bad Gateway` en `/app` | Apache proxypea pero Reverb no responde | `ss -tlnp \| grep 8080`; iniciar Reverb (sección 8) |
| `503 Service Unavailable` en proxy | Falta `setsebool httpd_can_network_relay 1` | Aplicar el boolean y `setenforce` recargar SELinux |
| Frontend devuelve HTML cuando el chunk JS no existe → `Failed to load module script: Expected JS but got text/html` | Bundle viejo cacheado por el browser/SW o build incompleto en `dist/` | (1) Verificar que `ls /var/www/lendusfind/frontend-<tenant>/assets/` tiene los archivos con los hashes que pide el browser. (2) Desregistrar SW + clear site data + hard refresh. (3) Confirmar que el VirtualHost tiene `Cache-Control: no-cache` para `sw.js` e `index.html` (sección 21.3). |
| Frontend devuelve HTML para `.js` que **sí existen** en disco. `md5sum index.html` coincide con `curl /assets/foo.js`. Apache responde 200 con `Content-Length` = tamaño del index.html | Las reglas SPA fallback del Include usan `%{REQUEST_FILENAME}` que **no es la ruta absoluta** a nivel VirtualHost — las condiciones `-f`/`-d` siempre dan falso y todo cae a `index.html` | Cambiar las RewriteCond a `%{DOCUMENT_ROOT}%{REQUEST_URI} -f [OR]` y `%{DOCUMENT_ROOT}%{REQUEST_URI} -d` (sección 21.3 ya corregida). `REQUEST_FILENAME` solo funciona en `.htaccess`, no a nivel VirtualHost. |
| `.htaccess` de cPanel con reglas SPA **duplicadas** y sin flag `[L]` en `RewriteRule ^ /index.html` | cPanel genera un `.htaccess` legacy con el bloque dos veces. Sin `[L]` el procesamiento sigue y la segunda iteración rebote a `/index.html` | Reemplazar el `.htaccess` por uno mínimo: `RewriteEngine On` + condiciones `DOCUMENT_ROOT%{REQUEST_URI} -f/-d` + `RewriteRule ^ - [L]` + `RewriteRule ^ /index.html [L]`. Hacer backup antes. |
| `mod_proxy_wstunnel.so` no carga | Módulo no instalado o mal nombre | `httpd -M \| grep wstunnel`. AlmaLinux 9 lo trae con `httpd` core; verificar `LoadModule` |
| Apache + cPanel pisa el VirtualHost custom | cPanel regenera config desde `/var/cpanel/userdata/` | Usar Include EasyApache o `pre_virtualhost_global.conf`. Consultar `EA4` docs |
| 502 / errores en POST grandes | `LimitRequestBody` bajo o `php.ini` con `upload_max_filesize` chico | Subir ambos: Apache 50 MB + `php_admin_value[upload_max_filesize]=50M` en el pool PHP-FPM |
| Certbot falla en `--apache` con vhost custom | Detección automática rota | Usar `certonly --webroot -w /var/www/lendusfind/backend/backend/public -d apifind.lendus.app` y agregar las directivas SSL a mano |

### 21.10 Convención de paths con cPanel/WHM

Si el server usa cPanel, los DocumentRoots típicos son
`/home/<user>/public_html/<dominio>/` en lugar de `/var/www/lendusfind/`.
Adaptá:

| Path en runbook estándar | Path típico cPanel |
|--------------------------|--------------------|
| `/var/www/lendusfind/backend/backend/public` | `/home/lendus/public_html/apifind.lendus.app` |
| `/var/www/lendusfind/frontend-moneycapital` | `/home/lendus/public_html/moneycapital.lendus.app` |
| `/home/lendus/laravelfiles_moneycapital` | El código Laravel afuera del `public_html` (recomendado por cPanel) |

Mantener los `.env`/sysconfig fuera del DocumentRoot para que Apache no
los sirva como archivos públicos.

## 22. Performance & Observability — diagnóstico y optimización

Esta sección documenta las prácticas activas para mantener latencias bajas
(~700–1200 ms p95 en /v2/staff) sobre la DB remota de 192.168.0.100. Si la
DB se mueve a localhost, varias de estas dejan de ser críticas pero siguen
siendo buena higiene.

### 22.1 PDO persistent connections

Habilitado por default en `backend/config/database.php` vía
`PDO::ATTR_PERSISTENT => env('DB_PERSISTENT', true)`. Reduce el handshake
TCP a Postgres remoto (~280 ms por request) a una vez por worker PHP-FPM.

Cómo monitorear que sigue funcionando:

```bash
# 1. Conteo de conexiones por host (debe ser ~= pm.max_children del pool,
#    no debe escalar con el RPS):
sudo -u postgres psql -c "SELECT client_addr, count(*) FROM pg_stat_activity WHERE datname = 'lendusfind' GROUP BY client_addr;"

# 2. Tiempo de espera de conexión desde la app (medir con EXPLAIN ANALYZE
#    en queries triviales; si > 50 ms, sospechar de PDO cerrando conexiones).
```

Override solo si Postgres está cortando conexiones agresivamente
(`statement_timeout`, `idle_in_transaction_session_timeout`):

```bash
# /etc/sysconfig/lendusfind-backend
DB_PERSISTENT=false
```

### 22.2 PgBouncer (cuando la DB queda remota)

Si la DB no se puede mover al mismo host (`192.168.0.100` actual),
PgBouncer en el host de la app es la siguiente palanca: hace pooling
transaccional, una sola conexión TCP por worker, y baja la latencia del
roundtrip cuando hay conexión caliente.

```bash
sudo dnf install -y pgbouncer

# /etc/pgbouncer/pgbouncer.ini
[databases]
lendusfind = host=192.168.0.100 port=6927 dbname=lendusfind

[pgbouncer]
listen_addr = 127.0.0.1
listen_port = 6432
auth_type = scram-sha-256
auth_file = /etc/pgbouncer/userlist.txt
pool_mode = transaction
max_client_conn = 200
default_pool_size = 25
reserve_pool_size = 5
server_idle_timeout = 60
```

Cambiar en `/etc/sysconfig/lendusfind-backend`:
- `DB_HOST=127.0.0.1`
- `DB_PORT=6432`

Cosas que NO funcionan con `pool_mode=transaction`:
- `SET LOCAL` fuera de transacción.
- Prepared statements del lado del cliente (Laravel los usa; activar
  `PDO::ATTR_EMULATE_PREPARES => true` o cambiar a `pool_mode=session`).
- LISTEN/NOTIFY (no lo usamos).

### 22.3 Co-ubicación de la DB (preferido a mediano plazo)

Si se puede mover Postgres al mismo host de la app:
- Eliminamos `~280 ms/request` de latencia de red.
- PgBouncer deja de ser obligatorio.
- Habilitar `synchronous_commit=local` y `shared_buffers≈25% RAM`.

Decisión actual: queda en backlog hasta justificar mover la base fuera
del LAN existente (192.168.0.100 está en infra compartida).

### 22.4 Laravel Telescope (staging y debug temporal)

Telescope nunca se habilita en producción (overhead alto, expone datos
sensibles). Solo se instala en staging y para debug puntual.

```bash
cd /var/www/lendusfind/current
sudo -u deploy composer require laravel/telescope --dev
sudo -u deploy php artisan telescope:install
sudo -u deploy php artisan migrate
```

`config/telescope.php`:

```php
'enabled' => env('TELESCOPE_ENABLED', false),
'storage' => [
    'database' => [
        'connection' => env('TELESCOPE_DB_CONNECTION', 'sqlite'),
    ],
],
```

`/etc/sysconfig/lendusfind-backend-staging`:

```bash
TELESCOPE_ENABLED=true
TELESCOPE_DB_CONNECTION=sqlite
```

Acceso: `https://apifind-staging.lendus.app/telescope` — protegido por el
gate de `app/Providers/TelescopeServiceProvider.php` que limita a emails
del super_admin. Verificar que solo whitelista correos `@lendus.app`.

Limpieza periódica:

```bash
sudo -u deploy php artisan telescope:prune --hours=48
```

### 22.5 OPCache — verificación

PHP-FPM en producción depende de OPCache para no recompilar PHP en cada
request. Script para verificar que está activo y bien dimensionado:

```bash
# /usr/local/bin/lendusfind-opcache-stats.sh
#!/usr/bin/env bash
set -e
curl -s -H "Host: apifind.lendus.app" http://127.0.0.1/api/ops/opcache-stats \
  | jq '{
      enabled: .opcache_enabled,
      cache_full: .cache_full,
      memory_used_pct: ((.memory_usage.used_memory / (.memory_usage.used_memory + .memory_usage.free_memory)) * 100 | floor),
      hit_rate: .opcache_statistics.opcache_hit_rate,
      scripts: .opcache_statistics.num_cached_scripts,
      max_scripts: .opcache_statistics.max_cached_keys
    }'
```

El endpoint `/api/ops/opcache-stats` se sirve con un controller minimal en
`app/Http/Controllers/Ops/OpcacheStatsController.php` (protegido por IP
allowlist a 127.0.0.1 y al rango de Jenkins).

Valores esperados sanos:
- `enabled: true`
- `cache_full: false`
- `hit_rate > 99` (después del warm-up)
- `memory_used_pct < 80` (si llega a 90%, subir `opcache.memory_consumption`).

`/etc/php.d/10-opcache.ini` recomendado:

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.fast_shutdown=1
```

Con `validate_timestamps=0`, el script de deploy debe correr
`php-fpm reload` (ya lo hace en la sección 7).

### 22.6 Cloudflare frente a `apifind.lendus.app`

Poner Cloudflare en proxy mode delante del backend da:
- TLS termination ya negociado (handshake compartido).
- Cache de assets estáticos del SPA (los frontends `*.lendus.app` también
  los benefician).
- WAF y rate limiting global (complementa los `throttle:*` de Laravel).
- DDoS L3/L4 absorbido por Cloudflare.

Pasos:

1. Agregar el dominio en Cloudflare con DNS proxy ON (nube naranja) para
   `apifind.lendus.app`.
2. SSL/TLS mode: **Full (strict)** — Cloudflare verifica el cert del origen.
3. Origin Server Certificate: generar uno en Cloudflare (15 años) y
   reemplazar el cert de Let's Encrypt del origen, o mantener Let's Encrypt
   y usar **Authenticated Origin Pulls** (mTLS al origen).
4. WebSocket support: habilitarlo en Network → WebSockets ON (default).
5. Reglas de cache:
   - `*.lendus.app/assets/*` → Cache Everything, TTL 1 año.
   - `apifind.lendus.app/api/*` → Bypass cache.
   - `apifind.lendus.app/app/*` (WebSocket Reverb) → Bypass cache.

⚠ Cuidado con headers detrás del proxy:
- Cloudflare reemplaza el IP del cliente. `app/Http/Middleware/TrustProxies.php`
  debe confiar en `CF-Connecting-IP` (ya está en `MetadataService::getRealIp()`).
- Si se activa "Always Use HTTPS", quitar el redirect HTTP→HTTPS del
  Apache para no causar loops.
- Origin restringido por firewall: solo aceptar conexiones de los
  rangos IP públicos de Cloudflare (https://www.cloudflare.com/ips/):
  ```bash
  for cidr in $(curl -s https://www.cloudflare.com/ips-v4); do
    sudo firewall-cmd --permanent --add-rich-rule="rule family='ipv4' source address='$cidr' service name='https' accept"
  done
  sudo firewall-cmd --reload
  ```

## 23. Geo IP con MaxMind GeoLite2 (DB local, sin API externa)

`audit_logs.latitude/longitude/city/region/country` se enriquece offline
desde una base de datos local de MaxMind. No usamos APIs externas tipo
ip-api.com porque agregan latencia al request (~200-500ms por lookup)
y rate limits que limitan el throughput.

### 23.1 Setup inicial (una sola vez)

**1. Cuenta MaxMind + License Key:**

   - Signup gratis: https://www.maxmind.com/en/geolite2/signup
   - Verifica el email, setea password.
   - Account → "My MaxMind Account ID" → copia el numero (~6-7 digits).
   - "Manage License Keys" → "Generate new license key":
     - Description: "LendusFind production server"
     - "Will this key be used for GeoIP Update?" → **YES**
     - Confirm → copia la key (la muestra una sola vez)

**2. Env vars en /etc/sysconfig/lendusfind-backend** (o `.env` de cPanel):

```bash
MAXMIND_ACCOUNT_ID=123456
MAXMIND_LICENSE_KEY=xxxxxxxxxxxxxxxx_xxxxxx
```

**3. Refresh config + descarga inicial de la DB:**

```bash
sudo -u deploy -E php artisan config:cache
sudo -u deploy -E php artisan audit-logs:update-geoip-db
# Resultado esperado: ~70MB descargados a storage/app/geoip/GeoLite2-City.mmdb
```

**4. Verifica:**

```bash
sudo -u deploy -E php artisan audit-logs:update-geoip-db --check
# Esperado:
#   Account ID: OK
#   License Key: OK
#   DB local: OK (70.X MB, mtime YYYY-MM-DD)
```

### 23.2 Schedule automatico

Ya configurado en `routes/console.php`:

- **`audit-logs:update-geoip-db`** — cada **miercoles 03:00** America/Mexico_City.
  MaxMind libera nueva version los martes; corremos miercoles para asegurar.

- **`audit-logs:resolve-geo --limit=1000 --since="2 hours"`** — cada **hora al minuto :15**.
  Recorre audit_logs de las ultimas 2h sin lat/lng, resuelve via MaxMind local.

Para que el schedule funcione, necesita uno de estos dos servicios corriendo:

**Opcion A (recomendada): systemd `schedule:work`**

```ini
# /etc/systemd/system/lendusfind-schedule.service
[Unit]
Description=LendusFind Laravel Schedule
After=network.target redis.service

[Service]
Type=simple
User=deploy
WorkingDirectory=/var/www/lendusfind/current
EnvironmentFile=/etc/sysconfig/lendusfind-backend
ExecStart=/usr/bin/php artisan schedule:work
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now lendusfind-schedule
```

**Opcion B: cron (cPanel-friendly)**

```cron
* * * * * cd /home/lendus/laravelfiles_moneycapital && /opt/cpanel/ea-php82/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

### 23.3 Verificacion / diagnostico

```bash
# Ver siguiente ejecucion programada
sudo -u deploy -E php artisan schedule:list

# Estado de la DB (tamano + fecha)
ls -lah /var/www/lendusfind/current/storage/app/geoip/GeoLite2-City.mmdb

# Manual: actualizar DB ahora mismo
sudo -u deploy -E php artisan audit-logs:update-geoip-db

# Manual: resolver TODOS los pendientes (no solo ultimas 2h)
sudo -u deploy -E php artisan audit-logs:resolve-geo --limit=50000

# Dry-run para diagnostico sin escribir nada
sudo -u deploy -E php artisan audit-logs:resolve-geo --dry-run --limit=100
```

### 23.4 Backup de la DB

`storage/app/geoip/*.mmdb` NO se versiona en git (`.gitignore` lo excluye).
Cada vez que `update-geoip-db` corre:
- Si existe la actual, la renombra a `.mmdb.bak` antes de overwrite.
- Si la nueva descarga es exitosa, borra el `.bak`.
- Si falla, restaura desde el `.bak`.

Sin necesidad de backup adicional — la siguiente corrida del schedule
re-descarga si la actual se corrompe.

### 23.5 Costo y limites

- **Free tier** de MaxMind GeoLite2: **gratis sin limites de uso** (DB local).
- Solo limite: actualizaciones cada semana (martes/jueves).
- Para tier "GeoIP2 City" (precision mejor): de pago, ~50 USD/mes.
- LendusFind usa GeoLite2 (free) y es suficiente para auditoria.

## Archivos clave de esta skill

Variante Nginx:
- `/etc/nginx/conf.d/apifind.lendus.app.conf` (sección 10)
- `/etc/nginx/conf.d/tenants.lendus.app.conf` (sección 11.3)

Variante Apache:
- `/etc/httpd/conf.d/apifind.lendus.app.conf` (sección 21.2)
- `/etc/httpd/conf.d/tenants.lendus.app.conf` (sección 21.3)

Comunes a ambos:
- `/etc/sysconfig/lendusfind-backend` — env vars de prod (600 root:root)
- `/etc/php-fpm.d/lendusfind.conf` — pool dedicado
- `/etc/systemd/system/lendusfind-reverb.service`
- `/etc/systemd/system/lendusfind-queue.service`
- `/usr/local/bin/lendusfind-deploy.sh` — entrypoint del deploy
- `/var/www/lendusfind/` — root de la app (o `/home/<user>/public_html/` con cPanel)
