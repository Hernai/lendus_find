#!/usr/bin/env bash
#
# Mide /v2/staff/auth/login simulando lo que hace un browser/app real:
# UNA conexion TCP+TLS reusada para todos los hits (keep-alive HTTP/1.1).
#
# A diferencia de measure-api.sh -- que lanza curl independientes y paga
# TCP+TLS handshake (~500-700ms) y posiblemente worker FPM cold en cada hit --
# este script muestra el TTFB que veria un usuario real navegando.
#
# Uso:
#   ./scripts/measure-login-real.sh              # 10 hits default
#   HITS=20 ./scripts/measure-login-real.sh      # 20 hits
#   HOST=otro.dominio.com ./scripts/measure-login-real.sh
#
# Salida: TTFB por hit, mas un resumen con promedio y mediana de los
# hits warm (excluye el primero que paga TLS handshake).

set -euo pipefail

HOST="${HOST:-apifind.lendus.app}"
TENANT="${TENANT:-moneycapital}"
EMAIL="${EMAIL:-admin@lendus.mx}"
PASSWORD="${PASSWORD:-password}"
HITS="${HITS:-10}"

# Construye URL con flags por hit: cada uno reusa la conexion TCP+TLS
# anterior via --next. Apache + Postman + browsers reales hacen esto siempre.
URL="https://${HOST}/api/v2/staff/auth/login"
JSON_BODY="{\"email\":\"${EMAIL}\",\"password\":\"${PASSWORD}\"}"
WFMT="hit=%{response_code} ttfb=%{time_starttransfer}s total=%{time_total}s\n"

# Cabeceras comunes a todos los hits
HEADERS=(
  -H "Content-Type: application/json"
  -H "X-Tenant-ID: ${TENANT}"
)

echo "================================================================"
echo "  POST ${URL}"
echo "  Tenant: ${TENANT}  | hits: ${HITS}  | conexion: UNA reusada"
echo "================================================================"

# Construye el array de argumentos de curl con HITS llamadas separadas por --next.
# La primera lleva todos los flags base; las siguientes se reutilizan via --next.
args=()
for i in $(seq 1 "${HITS}"); do
  if [ "${i}" -gt 1 ]; then
    args+=(--next)
  fi
  args+=(
    -X POST "${URL}"
    "${HEADERS[@]}"
    -d "${JSON_BODY}"
    -o /dev/null
    -w "h${i}: ${WFMT}"
  )
done

# Captura el output a un buffer para parsearlo despues
OUTPUT=$(curl -sk "${args[@]}")
echo "${OUTPUT}"

echo "----------------------------------------------------------------"

# Parsea TTFBs (en segundos) y reporta promedio + min/max del rango warm
# (excluye h1 que paga TLS handshake completo).
TTFBS_MS=$(echo "${OUTPUT}" | sed -nE 's/.*ttfb=([0-9.]+)s.*/\1/p' | awk '{print $1 * 1000}')

if [ -z "${TTFBS_MS}" ]; then
  echo "(no se pudo parsear ttfb)"
  exit 0
fi

# Separa h1 (cold) del resto (warm). Sin h1 si HITS == 1.
COLD_MS=$(echo "${TTFBS_MS}" | head -n1)
WARM_MS=$(echo "${TTFBS_MS}" | tail -n +2)

# Stats simple
if [ -n "${WARM_MS}" ]; then
  WARM_AVG=$(echo "${WARM_MS}" | awk '{s+=$1; n++} END{if(n>0) printf "%.0f", s/n}')
  WARM_MIN=$(echo "${WARM_MS}" | sort -n | head -n1 | awk '{printf "%.0f", $1}')
  WARM_MAX=$(echo "${WARM_MS}" | sort -n | tail -n1 | awk '{printf "%.0f", $1}')

  echo "Resumen:"
  printf "  cold (h1, paga TLS handshake): %.0fms\n" "${COLD_MS}"
  echo   "  warm (h2..h${HITS}): avg=${WARM_AVG}ms  min=${WARM_MIN}ms  max=${WARM_MAX}ms"
  echo
  echo "Esto es lo que veria tu usuario en una sesion de browser:"
  echo "  - primer hit del login: ~${COLD_MS%.*}ms"
  echo "  - hits posteriores en la misma sesion: ~${WARM_AVG}ms"
fi
