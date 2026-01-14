#!/bin/bash

# Script para probar el flujo completo de OTP con SMS real
# Uso: ./test-otp-sms.sh [numero_telefono]

PHONE=${1:-"5512345678"}
BASE_URL="http://localhost:8000/api"
TENANT_SLUG="lendusdemosii"

echo "================================================"
echo "🧪 PRUEBA DE FLUJO OTP CON SMS REAL"
echo "================================================"
echo ""
echo "📱 Teléfono: $PHONE"
echo "🏢 Tenant: $TENANT_SLUG"
echo "🌐 URL Base: $BASE_URL"
echo ""

# Paso 1: Solicitar OTP
echo "📤 Paso 1: Solicitando código OTP..."
echo ""

RESPONSE=$(curl -s -X POST "$BASE_URL/auth/otp/request" \
  -H "Content-Type: application/json" \
  -H "X-Tenant-Slug: $TENANT_SLUG" \
  -d "{\"phone\": \"$PHONE\", \"channel\": \"SMS\"}")

echo "Respuesta del servidor:"
echo "$RESPONSE" | jq '.'
echo ""

# Extraer el código si está en la respuesta (solo en dev)
CODE=$(echo "$RESPONSE" | jq -r '.code // empty')

if [ -n "$CODE" ]; then
  echo "⚠️  Código visible en respuesta (modo dev): $CODE"
  echo ""
  echo "En producción, el usuario debe ingresar el código recibido por SMS."
else
  echo "✅ El código NO está visible en la respuesta (modo producción correcto)"
  echo ""
  echo "👉 Por favor, ingresa el código de 6 dígitos que recibiste por SMS:"
  read -r CODE
fi

echo ""
echo "================================================"
echo "📥 Paso 2: Verificando código OTP: $CODE"
echo ""

VERIFY_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/otp/verify" \
  -H "Content-Type: application/json" \
  -H "X-Tenant-Slug: $TENANT_SLUG" \
  -d "{\"phone\": \"$PHONE\", \"code\": \"$CODE\"}")

echo "Respuesta del servidor:"
echo "$VERIFY_RESPONSE" | jq '.'
echo ""

# Verificar si fue exitoso
SUCCESS=$(echo "$VERIFY_RESPONSE" | jq -r '.success // empty')
TOKEN=$(echo "$VERIFY_RESPONSE" | jq -r '.token // empty')

if [ "$SUCCESS" = "true" ] && [ -n "$TOKEN" ]; then
  echo "================================================"
  echo "✅ ¡AUTENTICACIÓN EXITOSA!"
  echo "================================================"
  echo ""
  echo "🎫 Token generado:"
  echo "$TOKEN" | head -c 50
  echo "..."
  echo ""
  echo "🔍 Información del usuario:"
  echo "$VERIFY_RESPONSE" | jq '.user'
else
  echo "================================================"
  echo "❌ AUTENTICACIÓN FALLIDA"
  echo "================================================"
  echo ""
  ERROR=$(echo "$VERIFY_RESPONSE" | jq -r '.error // .message // "Error desconocido"')
  echo "Error: $ERROR"
fi

echo ""
