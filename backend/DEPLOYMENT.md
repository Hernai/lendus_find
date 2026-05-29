# Despliegue del Backend en AlmaLinux

Guía completa para desplegar el backend de **LendusFind** (Laravel 12 API) en un servidor **AlmaLinux 9** de producción.

---

## 1. Stack completo

| Componente | Versión | Rol |
|------------|---------|-----|
| **AlmaLinux** | 9.x | Sistema operativo |
| **PHP** | 8.2+ (php-fpm) | Runtime de la aplicación |
| **PostgreSQL** | 15+ | Base de datos (campos JSONB) |
| **Redis** | 7+ | Cache, sesiones y colas (queues) |
| **Nginx** | 1.20+ | Servidor web / reverse proxy |
| **Composer** | 2.x | Gestor de dependencias PHP |
| **Supervisor** | 4.x | Gestor de procesos (queue worker + Reverb) |
| **Certbot** | latest | Certificados SSL (Let's Encrypt) |
| **Node.js** | 20.19+ / 22.12+ | *Solo si se compila el frontend en el mismo host* |

> El backend es una **API REST pura**: no necesita Node.js para servir. Node solo se requiere si vas a buildear el frontend Vue en este mismo servidor.

### Servicios de larga duración (long-running)

- **php-fpm** — atiende las peticiones HTTP de la API.
- **Queue worker** (`php artisan queue:work redis`) — procesa jobs como `SendNotificationJob`.
- **Reverb** (`php artisan reverb:start`) — servidor WebSocket (puerto 8080).
- **Scheduler** (`php artisan schedule:run`) — vía cron cada minuto.

---

## 2. Preparación del sistema

```bash
# Actualizar el sistema
sudo dnf update -y

# Herramientas básicas
sudo dnf install -y git curl wget unzip tar policycoreutils-python-utils

# Habilitar repositorios EPEL y Remi (para PHP 8.2)
sudo dnf install -y epel-release
sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm
sudo dnf module reset php -y
sudo dnf module enable php:remi-8.2 -y
```

---

## 3. PHP 8.2 + extensiones

```bash
sudo dnf install -y \
  php php-fpm php-cli \
  php-pgsql php-redis php-mbstring php-xml php-bcmath \
  php-gd php-zip php-intl php-curl php-opcache php-pcntl

php -v   # Verificar 8.2.x
```

### Configuración recomendada de PHP (`/etc/php.ini`)

```ini
memory_limit = 512M
upload_max_filesize = 25M
post_max_size = 30M
max_execution_time = 120
date.timezone = America/Mexico_City
```

### php-fpm

Edita `/etc/php-fpm.d/www.conf` para que corra bajo el usuario de Nginx:

```ini
user = nginx
group = nginx
listen = /run/php-fpm/www.sock
listen.owner = nginx
listen.group = nginx
```

```bash
sudo systemctl enable --now php-fpm
```

---

## 4. PostgreSQL 15

```bash
# Repositorio oficial PGDG
sudo dnf install -y https://download.postgresql.org/pub/repos/yum/reporpms/EL-9-x86_64/pgdg-redhat-repo-latest.noarch.rpm
sudo dnf -qy module disable postgresql
sudo dnf install -y postgresql15-server postgresql15-contrib

# Inicializar e iniciar
sudo /usr/pgsql-15/bin/postgresql-15-setup initdb
sudo systemctl enable --now postgresql-15
```

### Crear base de datos y usuario

```bash
sudo -u postgres psql <<'SQL'
CREATE DATABASE lendusfind;
CREATE USER lendus WITH ENCRYPTED PASSWORD 'CAMBIA_ESTA_PASSWORD';
GRANT ALL PRIVILEGES ON DATABASE lendusfind TO lendus;
ALTER DATABASE lendusfind OWNER TO lendus;
SQL
```

### Permitir autenticación por contraseña

En `/var/lib/pgsql/15/data/pg_hba.conf` cambia `ident`/`peer` por `scram-sha-256` para conexiones locales:

```
host    lendusfind    lendus    127.0.0.1/32    scram-sha-256
local   lendusfind    lendus                    scram-sha-256
```

```bash
sudo systemctl restart postgresql-15
```

---

## 5. Redis

```bash
sudo dnf install -y redis
sudo systemctl enable --now redis

# Opcional: proteger con contraseña en /etc/redis/redis.conf
#   requirepass TU_PASSWORD_REDIS
sudo systemctl restart redis
```

---

## 6. Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

---

## 7. Desplegar la aplicación

```bash
# Crear directorio y clonar
sudo mkdir -p /var/www/lendusfind
sudo chown -R $USER:$USER /var/www/lendusfind
git clone <URL_DEL_REPO> /var/www/lendusfind
cd /var/www/lendusfind/backend

# Dependencias en modo producción
composer install --no-dev --optimize-autoloader

# Configurar entorno
cp .env.example .env
php artisan key:generate
```

### Variables de entorno (`backend/.env`)

```env
APP_NAME=LendusFind
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.tudominio.mx

LOG_CHANNEL=stack
LOG_LEVEL=warning

# Base de datos
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=lendusfind
DB_USERNAME=lendus
DB_PASSWORD=CAMBIA_ESTA_PASSWORD

# Redis (cache, colas y sesiones)
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Broadcasting (WebSocket Reverb)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=tu_app_id
REVERB_APP_KEY=tu_app_key
REVERB_APP_SECRET=tu_app_secret
REVERB_HOST=api.tudominio.mx
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

# Almacenamiento S3/MinIO
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

# Email (por tenant vía TenantApiConfig, este es el fallback)
MAIL_MAILER=smtp
MAIL_HOST=smtp.tuproveedor.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="no-reply@tudominio.mx"
MAIL_FROM_NAME="${APP_NAME}"

# Twilio (SMS/WhatsApp)
TWILIO_ACCOUNT_SID=
TWILIO_AUTH_TOKEN=
TWILIO_FROM_NUMBER=
TWILIO_WHATSAPP_FROM=
```

### Migraciones, seed y optimización

```bash
php artisan migrate --force
php artisan db:seed --force          # Solo en el primer despliegue
php artisan storage:link

# Cachear configuración, rutas y eventos para producción
php artisan config:cache
php artisan route:cache
php artisan event:cache
```

---

## 8. Permisos y SELinux

```bash
# Propietario de los archivos
sudo chown -R nginx:nginx /var/www/lendusfind
sudo chmod -R 775 /var/www/lendusfind/backend/storage \
                  /var/www/lendusfind/backend/bootstrap/cache

# Contextos SELinux para que Nginx/PHP escriba en storage
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/lendusfind/backend/storage(/.*)?"
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/lendusfind/backend/bootstrap/cache(/.*)?"
sudo restorecon -Rv /var/www/lendusfind/backend/storage /var/www/lendusfind/backend/bootstrap/cache

# Permitir que PHP-FPM se conecte a red (PostgreSQL, Redis, Twilio, S3...)
sudo setsebool -P httpd_can_network_connect 1
sudo setsebool -P httpd_can_network_connect_db 1
```

---

## 9. Nginx

Crea `/etc/nginx/conf.d/lendusfind.conf`:

```nginx
server {
    listen 80;
    server_name api.tudominio.mx;
    root /var/www/lendusfind/backend/public;

    index index.php;
    charset utf-8;

    client_max_body_size 30M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Proxy WebSocket hacia Reverb
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

```bash
sudo dnf install -y nginx
sudo nginx -t
sudo systemctl enable --now nginx
```

---

## 10. SSL con Certbot

```bash
sudo dnf install -y certbot python3-certbot-nginx
sudo certbot --nginx -d api.tudominio.mx
# Renovación automática (timer ya queda activo); probar con:
sudo certbot renew --dry-run
```

---

## 11. Supervisor (queue worker + Reverb)

```bash
sudo dnf install -y supervisor
sudo systemctl enable --now supervisord
```

Crea `/etc/supervisord.d/lendusfind.ini`:

```ini
[program:lendusfind-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/lendusfind/backend/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=nginx
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/lendusfind/queue.log
stopwaitsecs=3600

[program:lendusfind-reverb]
command=php /var/www/lendusfind/backend/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
user=nginx
redirect_stderr=true
stdout_logfile=/var/log/lendusfind/reverb.log
```

```bash
sudo mkdir -p /var/log/lendusfind
sudo chown nginx:nginx /var/log/lendusfind

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

> Tras cada despliegue ejecuta `sudo supervisorctl restart lendusfind-queue:*` para que el worker tome el código nuevo.

---

## 12. Scheduler (cron)

Agrega al crontab del usuario `nginx` (o root):

```bash
sudo crontab -u nginx -e
```

```cron
* * * * * cd /var/www/lendusfind/backend && php artisan schedule:run >> /dev/null 2>&1
```

---

## 13. Firewall

```bash
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

> El puerto 8080 de Reverb **no** se expone directamente: Nginx hace de proxy en `/app` sobre 443.

---

## 14. Verificación

```bash
# API responde
curl -s https://api.tudominio.mx/api/v2/config -H "X-Tenant-ID: demo" | head -c 200

# Servicios activos
systemctl status php-fpm postgresql-15 redis nginx supervisord
sudo supervisorctl status
```

---

## 15. Re-despliegues (actualizar código)

```bash
cd /var/www/lendusfind/backend
git pull origin main

composer install --no-dev --optimize-autoloader
php artisan migrate --force

php artisan config:cache
php artisan route:cache
php artisan event:cache

sudo supervisorctl restart lendusfind-queue:* lendusfind-reverb
```

> Para activar/desactivar el modo mantenimiento durante el despliegue:
> `php artisan down` antes y `php artisan up` después.
