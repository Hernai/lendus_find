#!/usr/bin/env bash
# =============================================================================
# Mide tiempos de respuesta de los endpoints principales de la API LendusFind.
#
# Uso:
#   ./scripts/measure-api.sh                            # contra producción (default)
#   API_BASE=http://localhost:8000/api ./scripts/measure-api.sh    # contra local
#   TENANT=finatea ./scripts/measure-api.sh             # otro tenant
#   RUNS=10 ./scripts/measure-api.sh                    # 10 mediciones por endpoint
#
# Output: tabla con endpoint, status, min, avg, max, p95 (en ms).
# Usa los endpoints del super admin global.
# =============================================================================
set -uo pipefail

API_BASE="${API_BASE:-https://apifind.lendus.app/api}"
TENANT="${TENANT:-moneycapital}"
EMAIL="${EMAIL:-superadmin@lendus.mx}"
PASSWORD="${PASSWORD:-password}"
RUNS="${RUNS:-5}"

# Colores
RED=$'\033[0;31m'
GREEN=$'\033[0;32m'
YELLOW=$'\033[1;33m'
BLUE=$'\033[0;34m'
GRAY=$'\033[0;90m'
BOLD=$'\033[1m'
NC=$'\033[0m'

echo
echo "${BOLD}Medición de tiempos de API LendusFind${NC}"
echo "${GRAY}Base:    ${API_BASE}${NC}"
echo "${GRAY}Tenant:  ${TENANT}${NC}"
echo "${GRAY}Runs:    ${RUNS} por endpoint${NC}"
echo

# -----------------------------------------------------------------------------
# Helper: mide N veces un curl y devuelve "min avg max p95"
# -----------------------------------------------------------------------------
measure() {
  local method="$1"
  local url="$2"
  local extra_headers="${3:-}"
  local body="${4:-}"
  local times=()
  local last_status=""

  for ((i=1; i<=RUNS; i++)); do
    local resp
    # %{http_code} + tab + %{time_total} (segundos con decimales)
    if [[ "$method" == "POST" ]]; then
      resp=$(curl -sS -o /dev/null \
        -w "%{http_code}\t%{time_total}" \
        -X POST \
        -H "Content-Type: application/json" \
        -H "Accept: application/json" \
        -H "X-Tenant-ID: ${TENANT}" \
        ${extra_headers:+-H "${extra_headers}"} \
        ${body:+-d "${body}"} \
        --max-time 30 \
        "${API_BASE}${url}" 2>/dev/null) || resp="000\t30.0"
    else
      resp=$(curl -sS -o /dev/null \
        -w "%{http_code}\t%{time_total}" \
        -H "Accept: application/json" \
        -H "X-Tenant-ID: ${TENANT}" \
        ${extra_headers:+-H "${extra_headers}"} \
        --max-time 30 \
        "${API_BASE}${url}" 2>/dev/null) || resp="000\t30.0"
    fi
    last_status="${resp%%$'\t'*}"
    local secs="${resp##*$'\t'}"
    # secs es algo como "0.234567", multiplicar por 1000 para ms
    local ms
    ms=$(awk -v s="$secs" 'BEGIN { printf "%.0f", s * 1000 }')
    times+=("$ms")
  done

  # Min, avg, max, p95
  local sorted
  sorted=$(printf '%s\n' "${times[@]}" | sort -n)
  local min max sum count avg p95 p95_idx
  min=$(echo "$sorted" | head -1)
  max=$(echo "$sorted" | tail -1)
  count=${#times[@]}
  sum=$(printf '%s\n' "${times[@]}" | awk '{ s += $1 } END { print s }')
  avg=$(( sum / count ))
  p95_idx=$(awk -v c="$count" 'BEGIN { v=int(c*0.95); print (v<1)?1:v }')
  p95=$(echo "$sorted" | sed -n "${p95_idx}p")

  echo "${last_status}|${min}|${avg}|${max}|${p95}"
}

# -----------------------------------------------------------------------------
# Colorea el tiempo según umbrales
# -----------------------------------------------------------------------------
color_time() {
  local ms="$1"
  if (( ms < 100 )); then
    printf "${GREEN}%5d${NC}" "$ms"
  elif (( ms < 500 )); then
    printf "${YELLOW}%5d${NC}" "$ms"
  elif (( ms < 1500 )); then
    printf "${YELLOW}%5d${NC}" "$ms"
  else
    printf "${RED}%5d${NC}" "$ms"
  fi
}

color_status() {
  local st="$1"
  if [[ "$st" =~ ^2 ]]; then
    printf "${GREEN}%s${NC}" "$st"
  elif [[ "$st" =~ ^[45] ]]; then
    printf "${RED}%s${NC}" "$st"
  else
    printf "${GRAY}%s${NC}" "$st"
  fi
}

print_row() {
  local label="$1"
  local result="$2"
  local status min avg max p95
  IFS='|' read -r status min avg max p95 <<< "$result"
  printf "  %-45s  " "$label"
  color_status "$status"; printf "  "
  color_time "$min"; printf "  "
  color_time "$avg"; printf "  "
  color_time "$max"; printf "  "
  color_time "$p95"; printf "\n"
}

print_header() {
  printf "${BOLD}  %-45s  %-3s   %5s  %5s  %5s  %5s${NC}\n" "ENDPOINT" "STA" "min" "avg" "max" "p95"
  printf "  %-45s  %-3s   %5s  %5s  %5s  %5s\n" "$(printf '%.0s-' {1..45})" "---" "-----" "-----" "-----" "-----"
}

# -----------------------------------------------------------------------------
# 1. Endpoints públicos (sin auth)
# -----------------------------------------------------------------------------
echo "${BLUE}${BOLD}━━ Public (sin auth) ━━${NC}"
print_header
print_row "GET /v2/public/health"     "$(measure GET '/v2/public/health')"
print_row "GET /v2/public/config"     "$(measure GET '/v2/public/config')"
print_row "GET /v2/public/manifest"   "$(measure GET '/v2/public/manifest')"
echo

# -----------------------------------------------------------------------------
# 2. Login del super admin (POST)
# -----------------------------------------------------------------------------
echo "${BLUE}${BOLD}━━ Auth ━━${NC}"
print_header
LOGIN_BODY="{\"email\":\"${EMAIL}\",\"password\":\"${PASSWORD}\"}"
print_row "POST /v2/staff/auth/login"  "$(measure POST '/v2/staff/auth/login' '' "${LOGIN_BODY}")"

# Hacer un login real para obtener el token
LOGIN_RESPONSE=$(curl -sS \
  -X POST \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -H "X-Tenant-ID: ${TENANT}" \
  -d "${LOGIN_BODY}" \
  --max-time 30 \
  "${API_BASE}/v2/staff/auth/login" 2>/dev/null)

TOKEN=$(echo "$LOGIN_RESPONSE" | grep -o '"token":"[^"]*"' | head -1 | sed 's/"token":"\(.*\)"/\1/')

if [[ -z "$TOKEN" ]]; then
  echo "${RED}✖ No se pudo obtener token. Response:${NC}"
  echo "$LOGIN_RESPONSE" | head -3
  exit 1
fi

print_row "GET  /v2/staff/auth/me"    "$(measure GET '/v2/staff/auth/me' "Authorization: Bearer ${TOKEN}")"
echo

# -----------------------------------------------------------------------------
# 3. Endpoints Staff (con token)
# -----------------------------------------------------------------------------
AUTH_HEADER="Authorization: Bearer ${TOKEN}"

echo "${BLUE}${BOLD}━━ Staff Admin ━━${NC}"
print_header
print_row "GET /v2/staff/tenants"             "$(measure GET '/v2/staff/tenants'                 "${AUTH_HEADER}")"
print_row "GET /v2/staff/applications"        "$(measure GET '/v2/staff/applications?page=1&per_page=20' "${AUTH_HEADER}")"
print_row "GET /v2/staff/applications/stats"  "$(measure GET '/v2/staff/applications/stats'      "${AUTH_HEADER}")"
print_row "GET /v2/staff/products"            "$(measure GET '/v2/staff/products'                "${AUTH_HEADER}")"
print_row "GET /v2/staff/users"               "$(measure GET '/v2/staff/users?page=1&per_page=20' "${AUTH_HEADER}")"
print_row "GET /v2/staff/api-logs"            "$(measure GET '/v2/staff/api-logs?page=1&per_page=20' "${AUTH_HEADER}")"
print_row "GET /v2/staff/api-logs/stats"      "$(measure GET '/v2/staff/api-logs/stats'           "${AUTH_HEADER}")"
print_row "GET /v2/staff/api-logs/providers"  "$(measure GET '/v2/staff/api-logs/providers'       "${AUTH_HEADER}")"
print_row "GET /v2/staff/config/tenant"       "$(measure GET '/v2/staff/config/tenant'            "${AUTH_HEADER}")"
echo

# -----------------------------------------------------------------------------
# 4. Resumen
# -----------------------------------------------------------------------------
echo "${BOLD}Leyenda:${NC}  ${GREEN}<100ms${NC}  ${YELLOW}100-1500ms${NC}  ${RED}>1500ms${NC}"
echo "${GRAY}p95 = el 95% de las requests termina por debajo de ese tiempo${NC}"
echo
