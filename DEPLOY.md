# Deploy de LendusFind a producción

Guía rápida para alguien que llega a deployar por primera vez. El runbook
detallado paso a paso (con comandos copy-paste) vive en
[`.claude/skills/deploy-ops/SKILL.md`](.claude/skills/deploy-ops/SKILL.md).

---

## ¿Qué se deploya?

| Componente | Tecnología | Dónde corre |
|-----------|------------|-------------|
| Backend | Laravel 12 + PHP 8.2 | nginx **o** Apache (httpd) + php-fpm (un solo dominio: `apifind.lendus.app`) |
| WebSocket | Laravel Reverb | systemd service en `127.0.0.1:8080` (proxy `/app` en nginx o `mod_proxy_wstunnel` en Apache) |
| Queue worker | Laravel queue Redis | systemd service |
| Frontend (3 bundles) | Vue 3 + Vite (estáticos) | nginx **o** Apache — un subdominio por SOFOM (`moneycapital.lendus.app`, `finatea.lendus.app`, `demo.lendus.app`) |
| Base de datos | PostgreSQL 15 | `127.0.0.1:5432` (single DB con scoping lógico por tenant) |
| Cache / queue / sessions | Redis | `127.0.0.1:6379` |

> **Variante del web server**: el runbook soporta tanto **Nginx** (standalone)
> como **Apache** (cuando el server ya tiene Apache con cPanel/WHM/Plesk).
> Las secciones 10–11 de la skill cubren Nginx; la sección 21 cubre Apache
> (incluyendo `mod_proxy_wstunnel`, cache headers, y paths típicos de cPanel).
> El resto del runbook (Postgres, Redis, env vars, systemd, SELinux) es idéntico.

---

## Arquitectura en 30 segundos

```
                  ┌─────────────────────────────────────────────┐
   Usuario ──────▶│ moneycapital.lendus.app  ◀── nginx          │
                  │ finatea.lendus.app       ◀── sirve dist/    │
                  │ demo.lendus.app          ◀── per-tenant     │
                  └────────────────┬────────────────────────────┘
                                   │ AJAX (X-Tenant-ID: moneycapital)
                                   ▼
                  ┌─────────────────────────────────────────────┐
                  │ apifind.lendus.app ◀── nginx                │
                  │ ├─ /api/v2/...     ─▶ php-fpm ─▶ Laravel    │
                  │ └─ /app/...        ─▶ Reverb (WebSocket)    │
                  └────────────────┬────────────────────────────┘
                                   │
                  ┌────────────────┴───────┬──────────────────┐
                  ▼                        ▼                  ▼
            PostgreSQL                  Redis            Queue worker
            (lendusfind)               (cache+queue)    (artisan queue:work)
```

- **Un solo backend** (`apifind.lendus.app`) sirve a todos los tenants.
- El tenant viaja como **header `X-Tenant-ID`** en cada request — lo agrega el frontend automáticamente.
- Cada tenant tiene su propio bundle de frontend estático, generado con `npm run tenant:build -- <slug>`.

---

## Pre-requisitos

Antes de tocar el server:

- [ ] **Server AlmaLinux 9** con acceso SSH como root (1 vCPU + 2 GB RAM mínimo, 4 vCPU + 8 GB recomendado).
- [ ] **DNS configurado** y propagado para:
  - `apifind.lendus.app` → IP del server
  - `moneycapital.lendus.app`, `finatea.lendus.app`, `demo.lendus.app` → IP del server
  - `lendus.app` (apex) → IP del server (landing)
- [ ] **Acceso a GitHub** (clonar el repo) o repo espejo en GitLab.
- [ ] **Credenciales reales** listas para pegar en `/etc/sysconfig/lendusfind-backend`:
  - PostgreSQL: password del usuario `lendusfind` (lo creás vos en el paso 3)
  - Reverb: `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` (generados con `php artisan reverb:install` o a mano)
  - Twilio (SMS/WhatsApp): `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_FROM_NUMBER`, `TWILIO_WHATSAPP_FROM`
  - Mail: SMTP host/port/user/password (o SendGrid/Mailgun API key)
  - AWS S3 (storage de docs): `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`
  - Nubarium (KYC): credenciales (van en `TenantApiConfig` por tenant, no en `.env`)
- [ ] **(Opcional pero recomendado)**: GitHub Actions con secrets configurados para automatizar el build del frontend (`REVERB_APP_KEY`, `DEPLOY_SSH_KEY`, `DEPLOY_SERVER`).

---

## Pasos del primer deploy (high-level)

Cada paso corresponde a una sección de
[`.claude/skills/deploy-ops/SKILL.md`](.claude/skills/deploy-ops/SKILL.md).
Seguilas en orden. Tiempo estimado total: 1.5–2 horas.

| Paso | Qué hacés | Sección de la skill | Tiempo |
|------|-----------|---------------------|--------|
| 1 | Instalar paquetes del SO (PHP 8.2, Node 20, nginx, Postgres, Redis, Composer, certbot) | § 1 | 15 min |
| 2 | Crear usuario `deploy` y estructura `/var/www/lendusfind/` | § 2 | 2 min |
| 3 | Crear DB `lendusfind` y usuario en PostgreSQL | § 3 | 5 min |
| 4 | **Configurar env vars** en `/etc/sysconfig/lendusfind-backend` (root:root 600) | § 4 | 10 min |
| 5 | Clonar repo + `composer install` + primer `migrate` + seed | § 5 | 10 min |
| 6 | Crear pool de PHP-FPM dedicado | § 6 | 5 min |
| 7 | Instalar script de deploy `/usr/local/bin/lendusfind-deploy.sh` | § 7 | 5 min |
| 8-9 | Crear systemd units para Reverb y queue worker | § 8-9 | 5 min |
| 10 | Configurar nginx **o** Apache para el backend (`apifind.lendus.app`) | § 10 (nginx) **o** § 21.2 (Apache) | 5–10 min |
| 11 | Buildear el frontend (3 tenants) y subir el `dist/` por scp/rsync. Configurar nginx **o** Apache para los 3 subdominios | § 11 (nginx) **o** § 21.3 (Apache) | 15-30 min |
| 12 | Emitir certificados Let's Encrypt para los 5 dominios | § 12 | 5 min |
| 13 | Configurar SELinux (contextos + booleans) | § 13 | 5 min |
| 14 | Configurar firewalld (solo 80/443/22) | § 14 | 2 min |
| 15 | Correr health-checks finales | § 17 | 5 min |

---

## Deploy continuo (después del primer setup)

```bash
# Re-deploy del backend con el último commit de main
sudo /usr/local/bin/lendusfind-deploy.sh

# Rollback a una versión específica
sudo /usr/local/bin/lendusfind-deploy.sh v1.2.3
sudo /usr/local/bin/lendusfind-deploy.sh abc1234

# Frontend: si CI está configurado, push a main → buildea + sube automático.
# Si no, build local + rsync manual (ver skill § 11.2).
```

---

## ¿Cómo se pasan las variables de entorno?

En LendusFind, los env vars **no están hardcoded** en el código — viven en archivos `.env*` o `/etc/sysconfig/...` y se inyectan en cada componente como corresponde:

| Componente | Mecanismo | Archivo / origen |
|------------|-----------|------------------|
| **Backend HTTP** (php-fpm) | Laravel cachea env en `bootstrap/cache/config.php` durante `php artisan config:cache` | El deploy script hace `set -a; source /etc/sysconfig/lendusfind-backend; set +a` antes de `config:cache` |
| **Backend Reverb / queue** (systemd) | `EnvironmentFile=` del unit file | `/etc/sysconfig/lendusfind-backend` |
| **Frontend build** | Vite lee `VITE_*` de `process.env` o `.env*` durante `vite build` | `.env.production` en CI, o env vars del workflow (`env:` en GitHub Actions) |
| **Frontend runtime** | Las `VITE_*` quedan compiladas en el bundle. Para cambiarlas, rebuildar. | — |

⚠ **Gotcha crítico**: Laravel cachea el `.env` en `config:cache`. Si rotás un secret y no re-corrés `lendusfind-deploy.sh`, los workers viejos siguen con el valor cacheado. La skill explica el detalle en § 4.

---

## Si algo falla

Ver tablas de troubleshooting en
[`.claude/skills/deploy-ops/SKILL.md`](.claude/skills/deploy-ops/SKILL.md):

- **§ 19 (Nginx + comunes)**: `502 Bad Gateway`, `403 Forbidden`/SELinux,
  `500` tras cambiar `.env` (config:cache stale), `could not find driver pgsql`,
  `Class "Redis" not found`, Reverb connection refused, Queue stuck,
  Certbot timeout, Composer OOM, etc.
- **§ 21.9 (Apache-specific)**: `curl /app` devuelve 404 con HTML de
  Laravel (proxy `wstunnel` mal configurado), `502 Bad Gateway` en `/app`,
  `503` por `setsebool httpd_can_network_relay`, frontend devuelve HTML
  cuando un chunk JS no existe (cache stale o build incompleto),
  `mod_proxy_wstunnel` no carga, cPanel pisa el VirtualHost, etc.

---

## Documentación relacionada

| Documento | Para qué |
|-----------|----------|
| [`.claude/skills/deploy-ops/SKILL.md`](.claude/skills/deploy-ops/SKILL.md) | Runbook detallado paso a paso con todos los comandos |
| [`.claude/skills/multitenancy/SKILL.md`](.claude/skills/multitenancy/SKILL.md) | Cómo funciona la arquitectura multi-tenant (header `X-Tenant-ID`, scoping, branding) |
| [`.claude/skills/mobile-deploy/SKILL.md`](.claude/skills/mobile-deploy/SKILL.md) | Agregar un nuevo SOFOM (tenant), buildear apps nativas iOS/Android, configurar push |
| [`frontend/DEPLOYMENT.md`](frontend/DEPLOYMENT.md) | Detalles específicos del frontend (PWA, Capacitor, tenant configs) |
| [`backend/.env.exampleee`](backend/.env.example) | Plantilla de env vars del backend con valores de prod comentados |
| [`frontend/.env.production.example`](frontend/.env.production.example) | Plantilla de env vars del frontend para producción |

---

## Checklist final (post-deploy)

Antes de pasarle el link al cliente, verificá:

- [ ] `curl https://apifind.lendus.app/api/v2/public/health` → 200
- [ ] `https://moneycapital.lendus.app` carga la landing morada
- [ ] `https://finatea.lendus.app` carga la landing teal
- [ ] `https://demo.lendus.app` carga la landing azul
- [ ] El super admin puede loguearse en `https://apifind.lendus.app/api/v2/staff/auth/login` con header `X-Tenant-ID: moneycapital` (cualquier tenant válido)
- [ ] Se pueden registrar usuarios por SMS (Twilio responde)
- [ ] La queue worker está procesando (`sudo journalctl -u lendusfind-queue -f`)
- [ ] Reverb acepta WebSocket connections (`wss://apifind.lendus.app/app/<REVERB_APP_KEY>`)
- [ ] Los certificados TLS son válidos (`curl -fsI https://...`) y certbot timer está activo
- [ ] Backup automático de PostgreSQL configurado (cron o `pg_dump` script — no cubierto en esta guía, agregar si es crítico)
- [ ] Monitoreo / alertas configurados (no cubierto — Sentry para Laravel, UptimeRobot, etc.)
