#!/usr/bin/env bash
# =============================================================================
# Deploy del frontend Vue (per-tenant) de LendusFind.
#
# Idempotente. Hace `npm ci` + `npm run tenant:build -- <slug>` + rsync de
# `dist/` al docroot del subdominio del tenant. Disenado para correr desde
# Jenkins (como user lendus) o manual en el server.
#
# Uso:
#   ./scripts/deploy-frontend.sh                  # deploy de TODOS los tenants
#   ./scripts/deploy-frontend.sh moneycapital     # solo moneycapital
#   TENANTS="demo,acme" ./scripts/deploy-frontend.sh
#
# Variables de entorno:
#   WORKSPACE_DIR    default: cwd. Root del repo (debe tener frontend/ dentro).
#   PUBLIC_HTML_DIR  default: /home/lendus/public_html
#                    Cada tenant se despliega a $PUBLIC_HTML_DIR/<slug>.lendus.app
#   NODE_BIN         default: node (en PATH)
#   NPM_BIN          default: npm (en PATH)
#   SKIP_INSTALL     si =1, salta `npm ci` (asume node_modules listo)
#
# Pre-requisitos en el server:
#   - Node 20.19+ o 22.12+ (ver frontend/package.json engines). Como CentOS 7
#     no soporta binarios oficiales, instalar via unofficial-builds:
#     ver deploy-ops/SKILL.md seccion 24.8.
#   - npm con acceso a registry publico
#   - Acceso de escritura a /home/lendus/public_html/<slug>.lendus.app
# =============================================================================
set -euo pipefail

# Cargar nvm si esta disponible. sudo -u lendus en shells no-interactivos
# NO carga ~/.bashrc, asi que nvm no esta en PATH por default. Lo cargamos
# explicitamente aqui para que `node`/`npm` esten disponibles.
export NVM_DIR="${NVM_DIR:-$HOME/.nvm}"
if [[ -s "$NVM_DIR/nvm.sh" ]]; then
    # shellcheck disable=SC1091
    source "$NVM_DIR/nvm.sh" || true
    nvm use default > /dev/null 2>&1 || nvm use --lts > /dev/null 2>&1 || true
fi

# Verificacion early: si no hay node usable, fallar con mensaje claro
if ! command -v node > /dev/null 2>&1; then
    echo "ERROR: 'node' no encontrado en PATH"
    echo "  PATH actual: $PATH"
    echo "  NVM_DIR:     ${NVM_DIR:-no seteado}"
    echo "  HOME:        $HOME"
    echo "  Instalar Node 20 LTS via unofficial-builds (ver deploy-ops 24.8)"
    exit 1
fi

echo "▶ Node:    $(node --version)  ($(command -v node))"
echo "▶ npm:     $(npm --version)"

WORKSPACE_DIR="${WORKSPACE_DIR:-$(pwd)}"
PUBLIC_HTML_DIR="${PUBLIC_HTML_DIR:-/home/lendus/public_html}"
NODE_BIN="${NODE_BIN:-node}"
NPM_BIN="${NPM_BIN:-npm}"
SKIP_INSTALL="${SKIP_INSTALL:-0}"

# Detecta tenants a deployar:
# 1. Argumento posicional (un solo slug)
# 2. Var de entorno TENANTS=slug1,slug2
# 3. Auto-detect: todos los archivos `frontend/tenants/<slug>.tenant.ts`
#    (excluyendo `_template.tenant.ts`)
FRONTEND_SRC="$WORKSPACE_DIR/frontend"
if [[ ! -d "$FRONTEND_SRC" ]]; then
    echo "ERROR: no existe $FRONTEND_SRC. WORKSPACE_DIR mal configurado?"
    exit 1
fi

# El workspace de Jenkins suele ser propiedad de `jenkins` con ACL `r-x`
# para `lendus`. `npm ci` necesita escribir `node_modules/` en el cwd, asi
# que copiamos a un staging dir bajo $HOME donde si tenemos write. El
# build genera `dist/` ahi y desde alli hacemos el rsync final.
STAGING_DIR="${STAGING_DIR:-$HOME/.cache/lendus-frontend-build}"
echo "▶ Staging dir: $STAGING_DIR"

mkdir -p "$STAGING_DIR"
# rsync con --delete para mantener staging limpio entre runs. Excluimos
# node_modules y dist del source para no copiar basura de devs locales.
rsync -a --delete \
    --exclude='node_modules/' \
    --exclude='dist/' \
    --exclude='.cache/' \
    "$FRONTEND_SRC/" "$STAGING_DIR/"

FRONTEND_DIR="$STAGING_DIR"

if [[ -n "${1:-}" ]]; then
    TENANT_LIST=("$1")
elif [[ -n "${TENANTS:-}" ]]; then
    IFS=',' read -ra TENANT_LIST <<< "$TENANTS"
else
    # Auto-detect: cada *.tenant.ts (sin _template)
    TENANT_LIST=()
    while IFS= read -r f; do
        slug=$(basename "$f" .tenant.ts)
        [[ "$slug" == "_template" ]] && continue
        TENANT_LIST+=("$slug")
    done < <(ls "$FRONTEND_DIR/tenants/"*.tenant.ts 2>/dev/null)
fi

if [[ ${#TENANT_LIST[@]} -eq 0 ]]; then
    echo "No hay tenants para deployar."
    exit 0
fi

echo "============================================================"
echo "Deploy Frontend"
echo "  Workspace : $WORKSPACE_DIR"
echo "  Tenants   : ${TENANT_LIST[*]}"
echo "  Docroot   : $PUBLIC_HTML_DIR/<slug>.lendus.app"
echo "============================================================"

cd "$FRONTEND_DIR"

# Instalar dependencias UNA vez para todos los tenants
if [[ "$SKIP_INSTALL" != "1" ]]; then
    echo "▶ npm ci (instala node_modules limpio)"
    $NPM_BIN ci --silent --no-audit --no-fund --prefer-offline
else
    echo "▶ Saltando npm ci (SKIP_INSTALL=1)"
fi

deploy_count=0
fail_count=0
failed_tenants=()

for slug in "${TENANT_LIST[@]}"; do
    echo
    echo "------------------------------------------------------------"
    echo "▶ Tenant: $slug"
    echo "------------------------------------------------------------"

    tenant_file="$FRONTEND_DIR/tenants/${slug}.tenant.ts"
    if [[ ! -f "$tenant_file" ]]; then
        echo "  ✗ No existe $tenant_file — saltando"
        ((fail_count++))
        failed_tenants+=("$slug:no_tenant_file")
        continue
    fi

    docroot="$PUBLIC_HTML_DIR/${slug}.lendus.app"
    if [[ ! -d "$docroot" ]]; then
        echo "  ✗ Docroot no existe: $docroot — saltando"
        ((fail_count++))
        failed_tenants+=("$slug:no_docroot")
        continue
    fi

    # Build
    echo "  ▶ npm run tenant:build -- $slug"
    if ! $NPM_BIN run tenant:build -- "$slug"; then
        echo "  ✗ Build falló para $slug"
        ((fail_count++))
        failed_tenants+=("$slug:build_failed")
        continue
    fi

    if [[ ! -d "$FRONTEND_DIR/dist" ]]; then
        echo "  ✗ dist/ no se genero después del build"
        ((fail_count++))
        failed_tenants+=("$slug:no_dist")
        continue
    fi

    # Rsync de dist/ al docroot:
    #   --delete elimina archivos viejos en docroot que ya no estan en dist/
    #     (chunks JS viejos con hashes obsoletos no se acumulan)
    #   Preserva 'cgi-bin/' (lo crea cPanel) y .htaccess existente al docroot
    #     vienen del .gitignore del docroot, no sobreescribir
    echo "  ▶ rsync dist/ → $docroot/"
    rsync -a --delete \
        --exclude='cgi-bin/' \
        --exclude='.well-known/' \
        --exclude='.htaccess.bak*' \
        "$FRONTEND_DIR/dist/" "$docroot/"

    # Asegurar permisos para que Apache pueda servir
    chmod -R a+rX "$docroot/assets" 2>/dev/null || true

    echo "  ✓ $slug desplegado en $docroot"
    ((deploy_count++))
done

echo
echo "============================================================"
echo "Deploy Frontend — Resumen"
echo "  OK  : $deploy_count"
echo "  FAIL: $fail_count"
if [[ ${#failed_tenants[@]} -gt 0 ]]; then
    echo "  Fallidos:"
    for f in "${failed_tenants[@]}"; do
        echo "    - $f"
    done
fi
echo "============================================================"

# Exit nonzero si hubo fallos para que Jenkins lo reporte
if [[ $fail_count -gt 0 ]]; then
    exit 1
fi
