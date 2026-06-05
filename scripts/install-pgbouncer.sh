#!/usr/bin/env bash
# =============================================================================
# Instala y configura PgBouncer en el server cPanel/CloudLinux apuntando a la
# DB remota 192.168.0.100:6927. Despues de correr este script, la app Laravel
# conecta a 127.0.0.1:6432 en vez de directamente a 192.168.0.100, eliminando
# el handshake TCP de ~280ms en cada query.
#
# IMPORTANTE: corre este script COMO ROOT en el server, NO en tu Mac.
#   sudo bash install-pgbouncer.sh
#
# Para validar antes de instalar (dry run):
#   DRY_RUN=1 bash install-pgbouncer.sh
#
# Variables sobreescribibles:
#   DB_HOST       (default 192.168.0.100)
#   DB_PORT       (default 6927)
#   DB_NAME       (default lendusfind)
#   DB_USER       (default postgres - lo que use tu Laravel)
#   DB_PASSWORD   (REQUERIDO - misma que en /home/lendus/.../laravelfiles_moneycapital/.env)
#   POOL_MODE     (default transaction)
#   POOL_SIZE     (default 25 - conexiones reusadas hacia Postgres)
#   MAX_CLIENT    (default 200 - clientes que pueden conectar a PgBouncer)
# =============================================================================

set -euo pipefail

DB_HOST="${DB_HOST:-192.168.0.100}"
DB_PORT="${DB_PORT:-6927}"
DB_NAME="${DB_NAME:-lendusfind}"
DB_USER="${DB_USER:-postgres}"
DB_PASSWORD="${DB_PASSWORD:-}"
POOL_MODE="${POOL_MODE:-transaction}"
POOL_SIZE="${POOL_SIZE:-25}"
MAX_CLIENT="${MAX_CLIENT:-200}"
LISTEN_PORT="${LISTEN_PORT:-6432}"
DRY_RUN="${DRY_RUN:-0}"

# --- Pre-flight checks ---------------------------------------------------------

if [[ $EUID -ne 0 ]]; then
  echo "ERROR: este script debe correr como root (sudo)" >&2
  exit 1
fi

if [[ -z "$DB_PASSWORD" ]]; then
  echo "ERROR: debes setear DB_PASSWORD." >&2
  echo "Buscalo en /home/lendus/laravelfiles_moneycapital/.env (DB_PASSWORD=)" >&2
  exit 1
fi

run() {
  echo "+ $*"
  if [[ "$DRY_RUN" == "0" ]]; then
    eval "$@"
  fi
}

# --- 1. Instalar PgBouncer -----------------------------------------------------

echo
echo "==> Paso 1: instalar pgbouncer desde repos de CentOS/EPEL"
if rpm -q pgbouncer >/dev/null 2>&1; then
  echo "  pgbouncer ya esta instalado, saltando install"
else
  run yum install -y pgbouncer
fi

# --- 2. Generar userlist.txt (formato esperado por scram-sha-256) -------------

echo
echo "==> Paso 2: crear /etc/pgbouncer/userlist.txt"
# PgBouncer espera "username" "password_hash". Para scram-sha-256, el hash
# debe ser el mismo string que tiene Postgres en pg_authid.rolpassword.
# Para simplicidad usamos auth_type=plain con el password en texto plano —
# el archivo es chmod 600 root:pgbouncer, no es legible por la app.
USERLIST="/etc/pgbouncer/userlist.txt"
run "echo '\"${DB_USER}\" \"${DB_PASSWORD}\"' > ${USERLIST}"
run chown root:pgbouncer "$USERLIST"
run chmod 600 "$USERLIST"

# --- 3. Escribir pgbouncer.ini -------------------------------------------------

echo
echo "==> Paso 3: configurar /etc/pgbouncer/pgbouncer.ini"
PGINI="/etc/pgbouncer/pgbouncer.ini"

if [[ -f "$PGINI" ]] && [[ ! -f "${PGINI}.bak" ]]; then
  run cp "$PGINI" "${PGINI}.bak"
fi

cat > /tmp/pgbouncer.ini.new <<EOF
[databases]
${DB_NAME} = host=${DB_HOST} port=${DB_PORT} dbname=${DB_NAME}

[pgbouncer]
listen_addr = 127.0.0.1
listen_port = ${LISTEN_PORT}
auth_type = plain
auth_file = /etc/pgbouncer/userlist.txt

# pool_mode=transaction es lo mas eficiente para Laravel (cada transaccion
# usa una conexion del pool, libera al commit/rollback). NO usa session
# para que Laravel pueda reusar agresivamente.
pool_mode = ${POOL_MODE}

# Maximo de clientes que pueden conectar a PgBouncer simultaneamente.
# 200 cubre 16 workers FPM x cada uno con un par de conexiones extra.
max_client_conn = ${MAX_CLIENT}

# Conexiones reusadas hacia Postgres (default 20). 25 es comodo para
# 16 workers FPM (no todos conectan simultaneo).
default_pool_size = ${POOL_SIZE}
reserve_pool_size = 5

# Timeouts conservadores
server_idle_timeout = 60
server_lifetime = 3600
query_timeout = 30

# Logging minimo (sin esto, logs masivos)
log_connections = 0
log_disconnections = 0
log_pooler_errors = 1

# Stats
admin_users = ${DB_USER}
stats_users = ${DB_USER}
EOF

run mv /tmp/pgbouncer.ini.new "$PGINI"
run chown root:pgbouncer "$PGINI"
run chmod 640 "$PGINI"

# --- 4. SELinux: permitir conexion saliente a Postgres ------------------------

echo
echo "==> Paso 4: SELinux permite a pgbouncer conectar a TCP a Postgres remoto"
if command -v getenforce >/dev/null && [[ "$(getenforce)" == "Enforcing" ]]; then
  # Permitir a postgresql_t/proxy_t conectar a postgres en puerto custom
  run setsebool -P httpd_can_network_connect 1
  run "semanage port -a -t postgresql_port_t -p tcp ${DB_PORT} 2>/dev/null || semanage port -m -t postgresql_port_t -p tcp ${DB_PORT}"
else
  echo "  SELinux no esta enforcing, saltando"
fi

# --- 5. Habilitar y arrancar systemd ------------------------------------------

echo
echo "==> Paso 5: habilitar y arrancar pgbouncer.service"
run systemctl enable pgbouncer
run systemctl restart pgbouncer

# --- 6. Smoke test -------------------------------------------------------------

echo
echo "==> Paso 6: smoke test (conexion vacia a 127.0.0.1:${LISTEN_PORT})"
if [[ "$DRY_RUN" == "0" ]]; then
  if PGPASSWORD="$DB_PASSWORD" psql -h 127.0.0.1 -p "$LISTEN_PORT" -U "$DB_USER" -d "$DB_NAME" -c "SELECT 1 AS pgbouncer_ok;" >/dev/null 2>&1; then
    echo "  ✓ Conexion exitosa via PgBouncer"
  else
    echo "  ✗ FAIL — revisa /var/log/pgbouncer/pgbouncer.log" >&2
    journalctl -u pgbouncer --no-pager | tail -20
    exit 1
  fi
fi

# --- 7. Mostrar stats ----------------------------------------------------------

echo
echo "==> Paso 7: stats actuales"
if [[ "$DRY_RUN" == "0" ]]; then
  PGPASSWORD="$DB_PASSWORD" psql -h 127.0.0.1 -p "$LISTEN_PORT" -U "$DB_USER" -d pgbouncer -c "SHOW POOLS;" 2>/dev/null || true
fi

echo
echo "===================================================================="
echo "  PgBouncer instalado y corriendo en 127.0.0.1:${LISTEN_PORT}"
echo "===================================================================="
echo
echo "PROXIMOS PASOS (manual):"
echo
echo "1) Edita el .env del backend Laravel:"
echo "     /home/lendus/laravelfiles_moneycapital/.env"
echo "   Cambia:"
echo "     DB_HOST=127.0.0.1"
echo "     DB_PORT=${LISTEN_PORT}"
echo "   Manten el resto igual (DB_DATABASE, DB_USERNAME, DB_PASSWORD)."
echo
echo "2) Limpia cache de config y reload FPM:"
echo "     sudo -u lendus /opt/cpanel/ea-php82/root/usr/bin/php \\"
echo "       /home/lendus/laravelfiles_moneycapital/artisan config:clear"
echo "     sudo systemctl reload ea-php82-php-fpm"
echo
echo "3) Verifica que Laravel ahora conecta a PgBouncer:"
echo "     sudo ss -tnp state established '( dport = :${LISTEN_PORT} )' | head"
echo "   Debes ver workers php-fpm conectados a 127.0.0.1:${LISTEN_PORT}"
echo
echo "4) Si algo se rompe, rollback rapido:"
echo "     - Revierte DB_HOST=192.168.0.100 y DB_PORT=6927 en .env"
echo "     - artisan config:clear + reload FPM"
echo "     - sudo systemctl stop pgbouncer (opcional)"
echo
echo "5) Mide latencia despues:"
echo "     # Desde tu Mac:"
echo "     ./scripts/measure-api.sh"
echo "   Endpoints autenticados deberian bajar ~150-250ms por query"
echo "   (no es magia: Postgres remoto sigue lejos, pero la conexion"
echo "   esta ya abierta y se reusa)."
