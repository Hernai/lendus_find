#!/usr/bin/env bash
# =============================================================================
# Deploy del backend Laravel de LendusFind.
#
# Idempotente. Hace pull + composer install + cachés Laravel + restart de
# servicios. Diseñado para correr desde Jenkins o manual en el server.
#
# Uso:
#   ./scripts/deploy-backend.sh                  # deploy del branch actual
#   ./scripts/deploy-backend.sh main             # deploy de un branch/tag
#   ./scripts/deploy-backend.sh v1.2.3           # rollback a un tag
#
# Variables de entorno:
#   APP_DIR             default: /home/lendus/laravelfiles_moneycapital
#   PHP_BIN             default: ea-php82
#   COMPOSER_BIN        default: /usr/local/bin/composer (autodetect)
#   SKIP_COMPOSER       si =1, salta composer install
#   SKIP_RESTART        si =1, salta systemctl restart
#   SKIP_GIT            si =1, salta git fetch + reset (útil cuando el
#                       directorio NO es un repo git y Jenkins ya hizo
#                       rsync del workspace antes de invocar el script)
#   SKIP_SEED           si =1, salta db:seed (útil para deploys urgentes
#                       o cuando hubo cambios destructivos en seeders)
# =============================================================================
set -euo pipefail

REF="${1:-origin/feat/moneycapital-onboarding}"
APP_DIR="${APP_DIR:-/home/lendus/laravelfiles_moneycapital}"
PHP_BIN="${PHP_BIN:-ea-php82}"
SKIP_COMPOSER="${SKIP_COMPOSER:-0}"
SKIP_RESTART="${SKIP_RESTART:-0}"
SKIP_GIT="${SKIP_GIT:-0}"
SKIP_SEED="${SKIP_SEED:-0}"

# Autodetect composer
if [[ -z "${COMPOSER_BIN:-}" ]]; then
    for cand in /usr/local/bin/composer /opt/cpanel/composer/bin/composer "$(command -v composer 2>/dev/null || true)"; do
        if [[ -x "$cand" ]]; then
            COMPOSER_BIN="$cand"
            break
        fi
    done
fi

log() { echo "[deploy] $*"; }
fail() { echo "[deploy] ERROR: $*" >&2; exit 1; }

[[ -d "$APP_DIR" ]] || fail "APP_DIR no existe: $APP_DIR"
command -v "$PHP_BIN" >/dev/null || fail "PHP binary no encontrado: $PHP_BIN"

cd "$APP_DIR"
log "Deploy → $REF en $APP_DIR"

# -----------------------------------------------------------------------------
# 1. Git pull (saltable cuando el directorio no es un repo git)
# -----------------------------------------------------------------------------
if [[ "$SKIP_GIT" != "1" ]] && [[ -d .git ]]; then
    log "git fetch + reset"
    git fetch --all --tags --prune
    git reset --hard "$REF"
    HEAD=$(git rev-parse --short HEAD)
    log "HEAD ahora: $HEAD"
else
    log "SKIP_GIT=$SKIP_GIT, .git $([[ -d .git ]] && echo presente || echo ausente) — salto pull/reset"
    HEAD="${REF}"
fi

# -----------------------------------------------------------------------------
# 2. Composer install (solo si cambió composer.lock o vendor/ está vacío)
# -----------------------------------------------------------------------------
if [[ "$SKIP_COMPOSER" != "1" ]]; then
    if [[ -z "${COMPOSER_BIN:-}" ]]; then
        log "WARN: composer no detectado, salto composer install"
    elif [[ ! -d vendor/laravel ]]; then
        log "vendor/laravel no existe — composer install"
        "$PHP_BIN" "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction
    elif [[ -d .git ]] && git diff "${HEAD}^" "${HEAD}" -- composer.lock 2>/dev/null | grep -q .; then
        log "composer.lock cambió — composer install"
        "$PHP_BIN" "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction
    else
        log "composer.lock sin cambios, salto install (usa SKIP_COMPOSER=0 + tocar composer.lock para forzar)"
    fi
else
    log "SKIP_COMPOSER=1, salto composer install"
fi

# Regenerar autoload: el rsync de Jenkins puede haber agregado archivos
# PHP nuevos (seeders, controllers, modelos) que composer aún no conoce
# en su classmap. Sin esto, `artisan db:seed --class=FinateaSeeder` falla
# con "Class not found". Es rápido (~1s) e idempotente; lo corremos en
# cada deploy.
if [[ -n "${COMPOSER_BIN:-}" ]]; then
    log "composer dump-autoload (refresca classmap)"
    "$PHP_BIN" "$COMPOSER_BIN" dump-autoload --optimize --no-interaction
fi

# -----------------------------------------------------------------------------
# 3. Cachear Laravel
# -----------------------------------------------------------------------------
log "Laravel caches"
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:clear
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:clear
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan event:clear
"$PHP_BIN" artisan event:cache

# -----------------------------------------------------------------------------
# 4. Migraciones (idempotentes, --force porque es no-interactive)
# -----------------------------------------------------------------------------
log "Migrate"
"$PHP_BIN" artisan migrate --force

# -----------------------------------------------------------------------------
# 4b. Seed idempotente — sincroniza los tenants oficiales (demo,
#     moneycapital, finatea) + super admin global + notification templates.
#
#     Seguro en cada deploy: los seeders usan updateOrCreate por slug y
#     firstOrCreate para templates (respeta ediciones del cliente desde
#     la UI). Re-correrlos NO duplica ni pisa datos editados por usuarios.
# -----------------------------------------------------------------------------
if [[ "$SKIP_SEED" != "1" ]]; then
    # Llamar a cada seeder POR NOMBRE en lugar de `db:seed` genérico.
    # Razón: DatabaseSeeder.php puede no haberse actualizado en el rsync
    # (timestamps, --update, exclusiones) y entonces `db:seed` solo
    # ejecuta los que el DatabaseSeeder viejo conoce — los SOFOMs nuevos
    # nunca se crean. Llamando por nombre forzamos los que SÍ existen
    # en disco. Cada uno es idempotente (updateOrCreate por slug,
    # firstOrCreate para templates).
    log "Seed (idempotente, por nombre)"
    for SEEDER in DemoDataSeeder MoneyCapitalSeeder FinateaSeeder \
                  GlobalSuperAdminSeeder NotificationTemplateSeeder \
                  MoneyCapitalNotificationSeeder; do
        if [[ -f "database/seeders/${SEEDER}.php" ]]; then
            log "  -> ${SEEDER}"
            "$PHP_BIN" artisan db:seed --class="$SEEDER" --force
        else
            log "  -> SKIP ${SEEDER} (no existe en disco)"
        fi
    done
    # Invalida cache de IdentifyTenant (lookup por slug + domain) para
    # que los cambios del seed se vean inmediatamente.
    "$PHP_BIN" artisan cache:clear
else
    log "SKIP_SEED=1, salto db:seed"
fi

# -----------------------------------------------------------------------------
# 5. Restart servicios
# -----------------------------------------------------------------------------
if [[ "$SKIP_RESTART" != "1" ]]; then
    log "Restart httpd + reverb"
    # Sudo sin password (configurado en /etc/sudoers.d/lendusfind-deploy)
    sudo systemctl reload httpd 2>&1 || sudo systemctl restart httpd
    sudo systemctl restart lendusfind-reverb 2>&1 || log "WARN: reverb no se reinició (puede no estar instalado)"
else
    log "SKIP_RESTART=1, salto reload"
fi

log "✓ Deploy OK → $HEAD"
