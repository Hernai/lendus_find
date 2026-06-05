#!/usr/bin/env bash
# =============================================================================
# Mide tiempos de respuesta de los endpoints principales de la API LendusFind.
#
# Cada request es una invocacion independiente de curl: nueva conexion
# TCP+TLS por request. Eso simula a un cliente que NO esta reusando
# conexion (worst case desde la perspectiva del usuario). Si tu cliente
# usa keep-alive (browser, axios reutilizando socket), tus tiempos
# reales seran MENORES.
#
# El piso fisico de esta medicion = RTT + handshake TCP + handshake TLS.
# Para mi RTT de 226ms, eso da ~700ms minimo por endpoint. Cualquier
# tiempo significativamente sobre eso es backend procesando.
#
# Para endpoints con ETag, se hace una ronda extra con If-None-Match —
# eso mide cuanto tarda un cliente que ya tiene el payload en local
# (debe responder 304 sin body).
#
# Uso:
#   ./scripts/measure-api.sh                            # contra produccion (default)
#   API_BASE=http://localhost:8000/api ./scripts/measure-api.sh    # contra local
#   TENANT=finatea ./scripts/measure-api.sh             # otro tenant
#   RUNS=10 ./scripts/measure-api.sh                    # 10 mediciones por endpoint
#   SKIP_LOGIN=1 ./scripts/measure-api.sh               # no testear /login (throttle)
#
# Output: tabla con endpoint, status, min, avg, max, p95 (en ms).
# Endpoints con ETag muestran una segunda fila "+304" con el beneficio del cache.
# =============================================================================
set -uo pipefail

API_BASE="${API_BASE:-https://apifind.lendus.app/api}"
TENANT="${TENANT:-moneycapital}"
EMAIL="${EMAIL:-superadmin@lendus.mx}"
PASSWORD="${PASSWORD:-password}"
RUNS="${RUNS:-5}"
SKIP_LOGIN="${SKIP_LOGIN:-0}"

# Colores
RED=$'\033[0;31m'
GREEN=$'\033[0;32m'
YELLOW=$'\033[1;33m'
BLUE=$'\033[0;34m'
GRAY=$'\033[0;90m'
BOLD=$'\033[1m'
NC=$'\033[0m'

echo
echo "${BOLD}Medicion de tiempos de API LendusFind${NC}"
echo "${GRAY}Base:    ${API_BASE}${NC}"
echo "${GRAY}Tenant:  ${TENANT}${NC}"
echo "${GRAY}Runs:    ${RUNS} por endpoint, conexion nueva cada request${NC}"
echo

# -----------------------------------------------------------------------------
# Helpers de calculo
# -----------------------------------------------------------------------------
stats_from_times() {
  # stdin: una linea por ms. Devuelve "min|avg|max|p95"
  local sorted min max sum count avg p95 p95_idx
  sorted=$(sort -n)
  min=$(head -1 <<< "$sorted")
  max=$(tail -1 <<< "$sorted")
  count=$(wc -l <<< "$sorted" | tr -d ' ')
  sum=$(awk '{ s += $1 } END { print s }' <<< "$sorted")
  avg=$(( count > 0 ? sum / count : 0 ))
  p95_idx=$(awk -v c="$count" 'BEGIN { v=int(c*0.95); print (v<1)?1:v }')
  p95=$(sed -n "${p95_idx}p" <<< "$sorted")
  echo "${min}|${avg}|${max}|${p95}"
}

# -----------------------------------------------------------------------------
# measure_get: GET con N invocaciones de curl. Captura el ETag de la primera
# respuesta para que el caller pueda medir el 304 despues.
# Devuelve "status|min|avg|max|p95|etag"
# -----------------------------------------------------------------------------
measure_get() {
  local url="$1"
  local extra_headers="${2:-}"
  local times=() last_status="" etag="" i

  # Primer request: dumpea headers para extraer ETag
  local first_headers first_stats
  first_headers=$(mktemp)
  first_stats=$(curl -sS --max-time 30 \
    -D "$first_headers" -o /dev/null \
    -w "%{http_code}\t%{time_starttransfer}" \
    -H "Accept: application/json" \
    -H "X-Tenant-ID: ${TENANT}" \
    ${extra_headers:+-H "${extra_headers}"} \
    "${API_BASE}${url}" 2>/dev/null) || first_stats="000\t30.0"

  etag=$(grep -i "^etag:" "$first_headers" 2>/dev/null | head -1 | sed 's/^[Ee][Tt][Aa][Gg]:[[:space:]]*//' | tr -d '\r')
  rm -f "$first_headers"

  last_status="${first_stats%%$'\t'*}"
  local first_ms
  first_ms=$(awk -v s="${first_stats##*$'\t'}" 'BEGIN { printf "%.0f", s * 1000 }')
  times+=("$first_ms")

  # Resto de requests
  for ((i=2; i<=RUNS; i++)); do
    local resp
    resp=$(curl -sS -o /dev/null \
      -w "%{http_code}\t%{time_starttransfer}" \
      -H "Accept: application/json" \
      -H "X-Tenant-ID: ${TENANT}" \
      ${extra_headers:+-H "${extra_headers}"} \
      --max-time 30 \
      "${API_BASE}${url}" 2>/dev/null) || resp="000\t30.0"
    last_status="${resp%%$'\t'*}"
    local ms
    ms=$(awk -v s="${resp##*$'\t'}" 'BEGIN { printf "%.0f", s * 1000 }')
    times+=("$ms")
  done

  local stats
  stats=$(printf '%s\n' "${times[@]}" | stats_from_times)
  echo "${last_status}|${stats}|${etag}"
}

# -----------------------------------------------------------------------------
# measure_cold: legacy (POST tambien lo usa).
# -----------------------------------------------------------------------------
measure_cold() {
  local method="$1"
  local url="$2"
  local extra_headers="${3:-}"
  local body="${4:-}"
  local times=() last_status="" i

  for ((i=1; i<=RUNS; i++)); do
    local resp
    if [[ "$method" == "POST" ]]; then
      resp=$(curl -sS -o /dev/null \
        -w "%{http_code}\t%{time_starttransfer}" \
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
        -w "%{http_code}\t%{time_starttransfer}" \
        -H "Accept: application/json" \
        -H "X-Tenant-ID: ${TENANT}" \
        ${extra_headers:+-H "${extra_headers}"} \
        --max-time 30 \
        "${API_BASE}${url}" 2>/dev/null) || resp="000\t30.0"
    fi
    last_status="${resp%%$'\t'*}"
    local secs="${resp##*$'\t'}"
    local ms
    ms=$(awk -v s="$secs" 'BEGIN { printf "%.0f", s * 1000 }')
    times+=("$ms")
  done

  local stats
  stats=$(printf '%s\n' "${times[@]}" | stats_from_times)
  echo "${last_status}|${stats}|"
}

# -----------------------------------------------------------------------------
# Mide el 304 Not Modified de un endpoint con ETag.
# -----------------------------------------------------------------------------
measure_304() {
  local url="$1"
  local etag="$2"
  local extra_headers="${3:-}"
  local times=() last_status="" i

  [[ -z "$etag" ]] && { echo ""; return; }

  for ((i=1; i<=RUNS; i++)); do
    local resp
    resp=$(curl -sS -o /dev/null \
      -w "%{http_code}\t%{time_starttransfer}" \
      -H "Accept: application/json" \
      -H "X-Tenant-ID: ${TENANT}" \
      -H "If-None-Match: ${etag}" \
      ${extra_headers:+-H "${extra_headers}"} \
      --max-time 30 \
      "${API_BASE}${url}" 2>/dev/null) || resp="000\t30.0"
    last_status="${resp%%$'\t'*}"
    local ms
    ms=$(awk -v s="${resp##*$'\t'}" 'BEGIN { printf "%.0f", s * 1000 }')
    times+=("$ms")
  done

  local stats
  stats=$(printf '%s\n' "${times[@]}" | stats_from_times)
  echo "${last_status}|${stats}|"
}

# -----------------------------------------------------------------------------
# Dispatcher: GET usa measure_get (con ETag); POST usa measure_cold.
# -----------------------------------------------------------------------------
measure() {
  local method="$1"
  local url="$2"
  local extra_headers="${3:-}"
  local body="${4:-}"

  if [[ "$method" == "GET" ]]; then
    measure_get "$url" "$extra_headers"
  else
    measure_cold "$method" "$url" "$extra_headers" "$body"
  fi
}

# -----------------------------------------------------------------------------
# Render
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
  elif [[ "$st" =~ ^3 ]]; then
    printf "${BLUE}%s${NC}" "$st"
  elif [[ "$st" =~ ^[45] ]]; then
    printf "${RED}%s${NC}" "$st"
  else
    printf "${GRAY}%s${NC}" "$st"
  fi
}

print_row() {
  local label="$1"
  local result="$2"
  local status min avg max p95 etag
  IFS='|' read -r status min avg max p95 etag <<< "$result"
  printf "  %-45s  " "$label"
  color_status "$status"; printf "  "
  color_time "$min"; printf "  "
  color_time "$avg"; printf "  "
  color_time "$max"; printf "  "
  color_time "$p95"; printf "\n"
  # Si vino con ETag, medir el 304
  if [[ -n "$etag" ]]; then
    local url="${label##* }"
    local result304
    result304=$(measure_304 "$url" "$etag" "${LAST_AUTH_HEADER:-}")
    if [[ -n "$result304" ]]; then
      IFS='|' read -r status304 min304 avg304 max304 p95304 _ <<< "$result304"
      printf "  ${GRAY}└─ +304 (If-None-Match)${NC}                       "
      color_status "$status304"; printf "  "
      color_time "$min304"; printf "  "
      color_time "$avg304"; printf "  "
      color_time "$max304"; printf "  "
      color_time "$p95304"; printf "\n"
    fi
  fi
}

print_header() {
  printf "${BOLD}  %-45s  %-3s   %5s  %5s  %5s  %5s${NC}\n" "ENDPOINT" "STA" "min" "avg" "max" "p95"
  printf "  %-45s  %-3s   %5s  %5s  %5s  %5s\n" "$(printf '%.0s-' {1..45})" "---" "-----" "-----" "-----" "-----"
}

# -----------------------------------------------------------------------------
# 1. Endpoints publicos (sin auth)
# -----------------------------------------------------------------------------
LAST_AUTH_HEADER=""
echo "${BLUE}${BOLD}-- Public (sin auth) --${NC}"
print_header
print_row "GET /v2/public/health"     "$(measure GET '/v2/public/health')"
print_row "GET /v2/config"            "$(measure GET '/v2/config')"
print_row "GET /v2/public/manifest"   "$(measure GET '/v2/public/manifest')"
print_row "GET /v2/public/version"    "$(measure GET '/v2/public/version')"
echo

# -----------------------------------------------------------------------------
# 2. Login (siempre cold porque throttle:5,1)
# -----------------------------------------------------------------------------
if [[ "$SKIP_LOGIN" == "1" ]]; then
  echo "${GRAY}-- Auth skipped (SKIP_LOGIN=1) --${NC}"
  echo
  exit 0
fi

echo "${BLUE}${BOLD}-- Auth --${NC}"
echo "${GRAY}Nota: /login tiene throttle 5/min. RUNS=${RUNS} consume el cupo.${NC}"
print_header
LOGIN_BODY="{\"email\":\"${EMAIL}\",\"password\":\"${PASSWORD}\"}"
LOGIN_RUNS=$RUNS
RUNS=$(( LOGIN_RUNS < 3 ? LOGIN_RUNS : 3 ))
print_row "POST /v2/staff/auth/login"  "$(measure_cold POST '/v2/staff/auth/login' '' "${LOGIN_BODY}")"
RUNS=$LOGIN_RUNS

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
  echo "${RED}* No se pudo obtener token (probablemente throttle). Response:${NC}"
  echo "$LOGIN_RESPONSE" | head -3
  echo "${GRAY}Tip: corre con SKIP_LOGIN=1 o espera 1 min y reintenta.${NC}"
  exit 1
fi

LAST_AUTH_HEADER="Authorization: Bearer ${TOKEN}"
print_row "GET  /v2/staff/auth/me"    "$(measure GET '/v2/staff/auth/me' "${LAST_AUTH_HEADER}")"
echo

# -----------------------------------------------------------------------------
# 3. Endpoints Staff (con token, warm)
# -----------------------------------------------------------------------------
echo "${BLUE}${BOLD}-- Staff: dashboards y listados --${NC}"
print_header
print_row "GET /v2/staff/me/tenants"                    "$(measure GET '/v2/staff/me/tenants'              "${LAST_AUTH_HEADER}")"
print_row "GET /v2/staff/applications"                  "$(measure GET '/v2/staff/applications?page=1&per_page=20' "${LAST_AUTH_HEADER}")"
print_row "GET /v2/staff/applications/board"            "$(measure GET '/v2/staff/applications/board'      "${LAST_AUTH_HEADER}")"
print_row "GET /v2/staff/applications/statistics"       "$(measure GET '/v2/staff/applications/statistics' "${LAST_AUTH_HEADER}")"
print_row "GET /v2/staff/applications/unassigned"       "$(measure GET '/v2/staff/applications/unassigned' "${LAST_AUTH_HEADER}")"
print_row "GET /v2/staff/applications/my-queue"         "$(measure GET '/v2/staff/applications/my-queue'   "${LAST_AUTH_HEADER}")"
echo

echo "${BLUE}${BOLD}-- Staff: catalogos --${NC}"
print_header
print_row "GET /v2/staff/products"                      "$(measure GET '/v2/staff/products'                "${LAST_AUTH_HEADER}")"
print_row "GET /v2/staff/users"                         "$(measure GET '/v2/staff/users?page=1&per_page=20' "${LAST_AUTH_HEADER}")"
print_row "GET /v2/staff/loans"                         "$(measure GET '/v2/staff/loans?page=1&per_page=20' "${LAST_AUTH_HEADER}")"
echo

echo "${BLUE}${BOLD}-- Staff: configuracion del tenant --${NC}"
print_header
print_row "GET /v2/staff/config"                        "$(measure GET '/v2/staff/config'                  "${LAST_AUTH_HEADER}")"
print_row "GET /v2/staff/integrations"                  "$(measure GET '/v2/staff/integrations'            "${LAST_AUTH_HEADER}")"
print_row "GET /v2/staff/notification-templates"        "$(measure GET '/v2/staff/notification-templates'  "${LAST_AUTH_HEADER}")"
print_row "GET /v2/staff/notification-templates/config" "$(measure GET '/v2/staff/notification-templates/config' "${LAST_AUTH_HEADER}")"
echo

echo "${BLUE}${BOLD}-- Staff: logs y auditoria --${NC}"
print_header
print_row "GET /v2/staff/api-logs"                      "$(measure GET '/v2/staff/api-logs?page=1&per_page=20' "${LAST_AUTH_HEADER}")"
print_row "GET /v2/staff/api-logs/stats"                "$(measure GET '/v2/staff/api-logs/stats'          "${LAST_AUTH_HEADER}")"
print_row "GET /v2/staff/api-logs/providers"            "$(measure GET '/v2/staff/api-logs/providers'      "${LAST_AUTH_HEADER}")"
echo

# -----------------------------------------------------------------------------
# Leyenda
# -----------------------------------------------------------------------------
echo "${BOLD}Leyenda:${NC}  ${GREEN}<100ms${NC}  ${YELLOW}100-1500ms${NC}  ${RED}>1500ms${NC}"
echo "${GRAY}p95 = el 95% de las requests termina por debajo de ese tiempo${NC}"
echo "${GRAY}+304 = mismo endpoint con If-None-Match (Cache hit del cliente)${NC}"
echo
