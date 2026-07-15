<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import AppButton from '@/components/common/AppButton.vue'

const router = useRouter()
const docRoot = ref<HTMLElement | null>(null)
type Lang = 'php' | 'node' | 'java' | 'csharp' | 'python'
const lang = ref<Lang>('php')
const langLabels: Record<Lang, string> = { php: 'PHP', node: 'Node.js', java: 'Java', csharp: 'C#', python: 'Python' }

function esc(s: string) {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

// Resaltado de JSON: llaves, strings, números, booleanos, null y comentarios.
function highlightJSON(src: string): string {
  return esc(src).replace(
    /(\/\/[^\n]*)|("(?:\\.|[^"\\])*"\s*:?)|(\b(?:true|false)\b)|(\bnull\b)|(-?\d+(?:\.\d+)?)/g,
    (_m, cmt, str, boo, nul, num) => {
      if (cmt) return `<span class="t-cmt">${cmt}</span>`
      if (str) return `<span class="t-${/:\s*$/.test(str) ? 'key' : 'str'}">${str}</span>`
      if (boo) return `<span class="t-bool">${boo}</span>`
      if (nul) return `<span class="t-null">${nul}</span>`
      if (num) return `<span class="t-num">${num}</span>`
      return _m
    },
  )
}

// Resaltado ligero de código: strings y comentarios (// y #).
function highlightCode(src: string): string {
  return esc(src).replace(
    /("(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*')|(\/\/[^\n]*|#[^\n]*)/g,
    (_m, str, cmt) => (str ? `<span class="t-str">${str}</span>` : `<span class="t-cmt">${cmt}</span>`),
  )
}

onMounted(() => {
  docRoot.value?.querySelectorAll('pre').forEach((pre) => {
    const raw = pre.textContent ?? ''
    const isJson = /^\s*[{[]/.test(raw)
    const code = document.createElement('code')
    code.innerHTML = isJson ? highlightJSON(raw) : highlightCode(raw)
    pre.textContent = ''
    pre.appendChild(code)
    pre.classList.add('has-copy')

    const btn = document.createElement('button')
    btn.type = 'button'
    btn.className = 'copy-btn'
    btn.textContent = 'Copiar'
    btn.addEventListener('click', () => {
      navigator.clipboard.writeText(raw).then(() => {
        btn.textContent = '¡Copiado!'
        window.setTimeout(() => (btn.textContent = 'Copiar'), 1400)
      })
    })
    pre.appendChild(btn)
  })
})

const sections = [
  { id: 'intro', n: '00', label: 'Introducción' },
  { id: 'eventos', n: '01', label: 'Catálogo de eventos' },
  { id: 'sobre', n: '02', label: 'El sobre (envelope)' },
  { id: 'payloads', n: '03', label: 'Ejemplos de payload' },
  { id: 'firma', n: '04', label: 'Verificar la firma' },
  { id: 'entrante', n: '05', label: 'API entrante (cartera)' },
  { id: 'reconsulta', n: '06', label: 'Re-consulta' },
  { id: 'errores', n: '07', label: 'Errores y códigos' },
]

function goto(id: string) {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
</script>

<template>
  <div ref="docRoot" class="p-6 max-w-6xl mx-auto int-docs">
    <!-- Header -->
    <div class="flex items-start justify-between gap-4 flex-wrap mb-6">
      <div>
        <button type="button" class="text-sm text-primary-600 hover:underline mb-1" @click="router.push({ name: 'admin-webhooks' })">
          ← Volver a Webhooks
        </button>
        <h1 class="text-2xl font-bold text-gray-900">Guía del integrador</h1>
        <p class="text-sm text-gray-500 mt-1 max-w-2xl">
          Conecta tu sistema de cartera o core bancario con LendusFind. Webhooks salientes,
          API entrante para confirmar pagos y dispersión, firma HMAC-SHA256 y ejemplos de
          peticiones/respuestas.
        </p>
      </div>
      <span class="text-xs font-mono px-2.5 py-1 rounded-full bg-primary-50 text-primary-700 border border-primary-100">contrato v1</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[13rem_1fr] gap-8">
      <!-- TOC -->
      <nav class="hidden lg:block sticky top-6 self-start space-y-0.5">
        <button
          v-for="s in sections" :key="s.id" type="button"
          class="w-full text-left flex items-baseline gap-2 px-3 py-1.5 rounded-lg text-sm text-gray-600 hover:bg-gray-100 hover:text-gray-900"
          @click="goto(s.id)"
        >
          <span class="font-mono text-xs text-gray-400">{{ s.n }}</span>
          <span>{{ s.label }}</span>
        </button>
      </nav>

      <!-- Content -->
      <div class="min-w-0 space-y-12">
        <!-- 00 INTRO -->
        <section id="intro" class="scroll-mt-6">
          <div class="grid sm:grid-cols-3 gap-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4">
              <p class="font-mono text-xs text-primary-600 font-semibold">SALIENTES</p>
              <p class="text-sm text-gray-600 mt-1">Recibes webhooks del ciclo del crédito con el payload completo.</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4">
              <p class="font-mono text-xs text-primary-600 font-semibold">ENTRANTE</p>
              <p class="text-sm text-gray-600 mt-1">Confirmas a LendusFind los pagos aplicados y la dispersión.</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4">
              <p class="font-mono text-xs text-primary-600 font-semibold">RE-CONSULTA</p>
              <p class="text-sm text-gray-600 mt-1">Relees el expediente/crédito para reconciliar.</p>
            </div>
          </div>

          <!-- flow -->
          <div class="mt-4 rounded-xl border border-gray-200 bg-white p-4">
            <p class="font-semibold text-gray-900 text-sm">El ciclo de dispersión</p>
            <p class="text-xs text-gray-500 mb-3">Tu cartera dispersa; LendusFind autoriza y activa.</p>
            <div class="flex items-stretch gap-1.5 overflow-x-auto pb-1">
              <div class="shrink-0 min-w-[8.5rem] rounded-lg border border-gray-200 p-2.5">
                <span class="text-[10px] font-mono font-semibold text-primary-600">LENDUSFIND</span>
                <p class="font-mono text-xs text-gray-900 mt-0.5">application.approved</p>
                <p class="text-[11px] text-gray-400">Autorizado + CLABE</p>
              </div>
              <span class="self-center text-gray-300 font-mono">→</span>
              <div class="shrink-0 min-w-[8.5rem] rounded-lg border border-gray-200 p-2.5">
                <span class="text-[10px] font-mono font-semibold text-emerald-600">TÚ</span>
                <p class="font-mono text-xs text-gray-900 mt-0.5">POST /disbursement</p>
                <p class="text-[11px] text-gray-400">confirmas</p>
              </div>
              <span class="self-center text-gray-300 font-mono">→</span>
              <div class="shrink-0 min-w-[8.5rem] rounded-lg border border-gray-200 p-2.5">
                <span class="text-[10px] font-mono font-semibold text-primary-600">LENDUSFIND</span>
                <p class="font-mono text-xs text-gray-900 mt-0.5">loan.disbursed</p>
                <p class="text-[11px] text-gray-400">crédito ACTIVO</p>
              </div>
              <span class="self-center text-gray-300 font-mono">→</span>
              <div class="shrink-0 min-w-[8.5rem] rounded-lg border border-gray-200 p-2.5">
                <span class="text-[10px] font-mono font-semibold text-emerald-600">TÚ</span>
                <p class="font-mono text-xs text-gray-900 mt-0.5">POST /payments</p>
                <p class="text-[11px] text-gray-400">pago aplicado</p>
              </div>
              <span class="self-center text-gray-300 font-mono">→</span>
              <div class="shrink-0 min-w-[8.5rem] rounded-lg border border-gray-200 p-2.5">
                <span class="text-[10px] font-mono font-semibold text-primary-600">LENDUSFIND</span>
                <p class="font-mono text-xs text-gray-900 mt-0.5">loan.completed</p>
                <p class="text-[11px] text-gray-400">liquidado</p>
              </div>
            </div>
          </div>
        </section>

        <!-- 01 EVENTOS -->
        <section id="eventos" class="scroll-mt-6">
          <h2 class="text-lg font-bold text-gray-900 mb-3"><span class="font-mono text-primary-600 text-sm mr-2">01</span>Catálogo de eventos <span class="font-mono text-xs text-gray-400 font-normal">v1</span></h2>
          <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr><th class="text-left px-4 py-2.5">Evento</th><th class="text-left px-4 py-2.5">Cuándo se dispara</th><th class="text-left px-4 py-2.5">Recurso</th></tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr><td class="px-4 py-2.5 font-mono text-primary-700 text-xs whitespace-nowrap">application.approved</td><td class="px-4 py-2.5 text-gray-600">Crédito autorizado. <b>Señal de "dispersa"</b>: trae la CLABE y el monto.</td><td class="px-4 py-2.5 font-mono text-xs">application</td></tr>
                <tr><td class="px-4 py-2.5 font-mono text-primary-700 text-xs whitespace-nowrap">application.rejected</td><td class="px-4 py-2.5 text-gray-600">La solicitud fue rechazada.</td><td class="px-4 py-2.5 font-mono text-xs">application</td></tr>
                <tr><td class="px-4 py-2.5 font-mono text-primary-700 text-xs whitespace-nowrap">loan.disbursed</td><td class="px-4 py-2.5 text-gray-600">Confirmaste la dispersión y LendusFind activó el crédito.</td><td class="px-4 py-2.5 font-mono text-xs">loan</td></tr>
                <tr><td class="px-4 py-2.5 font-mono text-primary-700 text-xs whitespace-nowrap">payment.received</td><td class="px-4 py-2.5 text-gray-600">Se registró un pago sobre el crédito.</td><td class="px-4 py-2.5 font-mono text-xs">loan</td></tr>
                <tr><td class="px-4 py-2.5 font-mono text-primary-700 text-xs whitespace-nowrap">loan.completed</td><td class="px-4 py-2.5 text-gray-600">El crédito quedó liquidado.</td><td class="px-4 py-2.5 font-mono text-xs">loan</td></tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- 02 SOBRE -->
        <section id="sobre" class="scroll-mt-6">
          <h2 class="text-lg font-bold text-gray-900 mb-3"><span class="font-mono text-primary-600 text-sm mr-2">02</span>El sobre (envelope)</h2>
          <p class="text-sm text-gray-600 mb-3 max-w-2xl">Todos los webhooks comparten esta estructura. El <code class="text-xs bg-gray-100 px-1 py-0.5 rounded">id</code> es tu clave de idempotencia. Solo cambia <code class="text-xs bg-gray-100 px-1 py-0.5 rounded">data</code> según el recurso.</p>
          <pre v-pre class="bg-gray-900 text-gray-100 rounded-xl p-4 overflow-x-auto text-xs font-mono leading-relaxed">{
  "id": "evt_01J9Z8Q4C7M2X5T3B6N0K1P8RA",
  "event": "application.approved",
  "version": "1",
  "occurred_at": "2026-07-15T18:04:22Z",
  "tenant": { "id": "af428ded-...", "slug": "moneycapital" },
  "data": { }
}</pre>
        </section>

        <!-- 03 PAYLOADS -->
        <section id="payloads" class="scroll-mt-6">
          <h2 class="text-lg font-bold text-gray-900 mb-3"><span class="font-mono text-primary-600 text-sm mr-2">03</span>Ejemplos de payload</h2>

          <h3 class="text-sm font-semibold mb-2 font-mono text-primary-700">application.approved</h3>
          <p class="text-xs text-gray-500 mb-2">El nombre viaja <b>desglosado</b> (paterno/materno por separado) y también como <code class="bg-gray-100 px-1 rounded">full_name</code>. La CLABE va completa: trátala como PII.</p>
          <pre v-pre class="bg-gray-900 text-gray-100 rounded-xl p-4 overflow-x-auto text-xs font-mono leading-relaxed">{
  "id": "evt_01J9Z8Q4C7M2X5T3B6N0K1P8RA",
  "event": "application.approved",
  "version": "1",
  "occurred_at": "2026-07-15T18:04:22Z",
  "tenant": { "id": "af428ded-...", "slug": "moneycapital" },
  "data": {
    "application": {
      "id": "019f6404-1a2b-7c3d-8e4f-5a6b7c8d9e0f",
      "folio": "20260715-019F",
      "status": "APPROVED",
      "product": { "code": "MC-SIN-BURO", "name": "Préstamo Sin Buró" },
      "approved": {
        "amount": 350.00, "term_days": 7, "interest_rate": 36.0,
        "opening_commission_rate": 13.0, "currency": "MXN"
      },
      "person": {
        "id": "019f6404-2b3c-7d4e-9f5a-6b7c8d9e0f1a",
        "first_name": "Juan",
        "last_name_1": "Pérez",
        "last_name_2": "López",
        "full_name": "Juan Pérez López",
        "curp": "PELJ950115HSLRPN01",
        "rfc": "PELJ950115AB1",
        "birth_date": "1995-01-15",
        "kyc_status": "VERIFIED",
        "identifications": [
          {
            "type": "INE",
            "value": "PRLPJN95011509H100",
            "status": "VERIFIED",
            "expires_at": "2030-12-31",
            "ocr": { "clave_elector": "PRLPJN95011509H100", "vigencia": "2030" }
          }
        ]
      },
      "disbursement_account": {
        "bank_name": "STP",
        "clabe": "646180157099999993",
        "holder_name": "Juan Pérez López"
      },
      "documents": [
        { "id": "019f6404-a1", "type": "INE_FRONT", "category": "IDENTITY", "mime_type": "image/jpeg", "download_url": "https://api.lendus.app/api/v2/integration/documents/019f6404-a1/download" },
        { "id": "019f6404-a2", "type": "INE_BACK", "category": "IDENTITY", "mime_type": "image/jpeg", "download_url": "https://api.lendus.app/api/v2/integration/documents/019f6404-a2/download" },
        { "id": "019f6404-a3", "type": "PROOF_OF_ADDRESS", "category": "ADDRESS", "mime_type": "application/pdf", "download_url": "https://api.lendus.app/api/v2/integration/documents/019f6404-a3/download" }
      ],
      "approved_at": "2026-07-15T18:04:22Z"
    }
  }
}</pre>

          <h3 class="text-sm font-semibold mb-2 mt-6 font-mono text-primary-700">loan.disbursed</h3>
          <pre v-pre class="bg-gray-900 text-gray-100 rounded-xl p-4 overflow-x-auto text-xs font-mono leading-relaxed">{
  "id": "evt_01J9ZC7...", "event": "loan.disbursed", "version": "1",
  "occurred_at": "2026-07-15T18:05:10Z",
  "tenant": { "id": "af428ded-...", "slug": "moneycapital" },
  "data": {
    "loan": {
      "id": "019f6404-9a8b-...", "application_id": "019f6404-1a2b-...",
      "status": "ACTIVE",
      "principal_amount": 350.00, "interest_rate": 36.0, "term_days": 7,
      "opening_commission_amount": 45.50,
      "total_to_pay": 405.59, "outstanding_balance": 405.59, "currency": "MXN",
      "disbursed_at": "2026-07-15T18:05:10Z", "due_date": "2026-07-22",
      "disbursement": { "provider": "STP", "reference": "STP-2026071500012345" }
    }
  }
}</pre>

          <h3 class="text-sm font-semibold mb-2 mt-6 font-mono text-primary-700">payment.received</h3>
          <pre v-pre class="bg-gray-900 text-gray-100 rounded-xl p-4 overflow-x-auto text-xs font-mono leading-relaxed">{
  "id": "evt_01J9ZD1...", "event": "payment.received", "version": "1",
  "occurred_at": "2026-07-18T12:30:00Z",
  "tenant": { "id": "af428ded-...", "slug": "moneycapital" },
  "data": {
    "loan": {
      "id": "019f6404-9a8b-...", "status": "ACTIVE",
      "total_to_pay": 405.59, "paid_amount": 200.00, "outstanding_balance": 205.59,
      "currency": "MXN",
      "last_payment": {
        "amount": 200.00, "channel": "STP", "provider_reference": "STP-88213",
        "paid_at": "2026-07-18T12:29:41Z"
      }
    }
  }
}</pre>
        </section>

        <!-- 04 FIRMA -->
        <section id="firma" class="scroll-mt-6">
          <h2 class="text-lg font-bold text-gray-900 mb-3"><span class="font-mono text-primary-600 text-sm mr-2">04</span>Verificar la firma <span class="text-xs text-red-500 font-mono font-normal">obligatorio</span></h2>
          <p class="text-sm text-gray-600 mb-3 max-w-2xl">Verifica siempre la firma antes de procesar. Rechaza si no valida o si el timestamp está fuera de la ventana de ±5 minutos.</p>
          <div class="overflow-x-auto rounded-xl border border-gray-200 mb-4">
            <table class="w-full text-sm">
              <tbody class="divide-y divide-gray-100">
                <tr><td class="px-4 py-2 font-mono text-xs whitespace-nowrap">X-LendusFind-Signature</td><td class="px-4 py-2 text-gray-600 font-mono text-xs">sha256=&lt;hex&gt; — HMAC-SHA256 de "{timestamp}.{body}"</td></tr>
                <tr><td class="px-4 py-2 font-mono text-xs whitespace-nowrap">X-LendusFind-Timestamp</td><td class="px-4 py-2 text-gray-600">Unix epoch (segundos) usado en la firma</td></tr>
                <tr><td class="px-4 py-2 font-mono text-xs whitespace-nowrap">X-LendusFind-Event</td><td class="px-4 py-2 text-gray-600">Nombre del evento</td></tr>
                <tr><td class="px-4 py-2 font-mono text-xs whitespace-nowrap">X-LendusFind-Delivery</td><td class="px-4 py-2 text-gray-600">ID de la entrega (para soporte)</td></tr>
              </tbody>
            </table>
          </div>
          <div class="flex flex-wrap gap-1 mb-2">
            <button type="button" v-for="l in (['php','node','java','csharp','python'] as const)" :key="l"
              class="px-3 py-1.5 rounded-lg text-xs font-mono"
              :class="lang === l ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
              @click="lang = l">{{ langLabels[l] }}</button>
          </div>
          <pre v-show="lang === 'php'" class="bg-gray-900 text-gray-100 rounded-xl p-4 overflow-x-auto text-xs font-mono leading-relaxed">function verifyLendusSignature(string $rawBody, array $headers, string $secret): bool
{
    $signature = $headers['X-LendusFind-Signature'] ?? '';
    $timestamp = $headers['X-LendusFind-Timestamp'] ?? '';

    if (abs(time() - (int) $timestamp) > 300) return false; // ventana +-5 min

    $expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
    return hash_equals($expected, $signature);
}</pre>
          <pre v-show="lang === 'node'" class="bg-gray-900 text-gray-100 rounded-xl p-4 overflow-x-auto text-xs font-mono leading-relaxed">const crypto = require('crypto');

function verifyLendusSignature(rawBody, headers, secret) {
  const signature = headers['x-lendusfind-signature'] || '';
  const timestamp = headers['x-lendusfind-timestamp'] || '';

  if (Math.abs(Date.now() / 1000 - Number(timestamp)) > 300) return false;

  const expected = 'sha256=' +
    crypto.createHmac('sha256', secret).update(timestamp + '.' + rawBody).digest('hex');

  const a = Buffer.from(expected), b = Buffer.from(signature);
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}</pre>
          <pre v-show="lang === 'java'" class="bg-gray-900 text-gray-100 rounded-xl p-4 overflow-x-auto text-xs font-mono leading-relaxed">import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;

boolean verifyLendusSignature(String rawBody, Map&lt;String, String&gt; headers, String secret) throws Exception {
    String signature = headers.getOrDefault("X-LendusFind-Signature", "");
    String timestamp = headers.getOrDefault("X-LendusFind-Timestamp", "");

    if (Math.abs(System.currentTimeMillis() / 1000 - Long.parseLong(timestamp)) > 300) return false;

    Mac mac = Mac.getInstance("HmacSHA256");
    mac.init(new SecretKeySpec(secret.getBytes(StandardCharsets.UTF_8), "HmacSHA256"));
    byte[] raw = mac.doFinal((timestamp + "." + rawBody).getBytes(StandardCharsets.UTF_8));

    StringBuilder hex = new StringBuilder();
    for (byte b : raw) hex.append(String.format("%02x", b));
    String expected = "sha256=" + hex;

    return MessageDigest.isEqual(
        expected.getBytes(StandardCharsets.UTF_8),
        signature.getBytes(StandardCharsets.UTF_8));
}</pre>
          <pre v-show="lang === 'csharp'" class="bg-gray-900 text-gray-100 rounded-xl p-4 overflow-x-auto text-xs font-mono leading-relaxed">using System;
using System.Security.Cryptography;
using System.Text;

bool VerifyLendusSignature(string rawBody, IDictionary&lt;string, string&gt; headers, string secret)
{
    var signature = headers.TryGetValue("X-LendusFind-Signature", out var s) ? s : "";
    var timestamp = headers.TryGetValue("X-LendusFind-Timestamp", out var t) ? t : "";

    var now = DateTimeOffset.UtcNow.ToUnixTimeSeconds();
    if (Math.Abs(now - long.Parse(timestamp)) > 300) return false;

    using var hmac = new HMACSHA256(Encoding.UTF8.GetBytes(secret));
    var hash = hmac.ComputeHash(Encoding.UTF8.GetBytes($"{timestamp}.{rawBody}"));
    var expected = "sha256=" + Convert.ToHexString(hash).ToLowerInvariant();

    return CryptographicOperations.FixedTimeEquals(
        Encoding.UTF8.GetBytes(expected),
        Encoding.UTF8.GetBytes(signature));
}</pre>
          <pre v-show="lang === 'python'" class="bg-gray-900 text-gray-100 rounded-xl p-4 overflow-x-auto text-xs font-mono leading-relaxed">import hashlib, hmac, time

def verify_lendus_signature(raw_body: bytes, headers: dict, secret: str) -> bool:
    signature = headers.get("X-LendusFind-Signature", "")
    timestamp = headers.get("X-LendusFind-Timestamp", "")

    if abs(time.time() - int(timestamp)) > 300:
        return False

    expected = "sha256=" + hmac.new(
        secret.encode(), f"{timestamp}.".encode() + raw_body, hashlib.sha256
    ).hexdigest()
    return hmac.compare_digest(expected, signature)</pre>
        </section>

        <!-- 05 ENTRANTE -->
        <section id="entrante" class="scroll-mt-6">
          <h2 class="text-lg font-bold text-gray-900 mb-3"><span class="font-mono text-primary-600 text-sm mr-2">05</span>API entrante — confirmar pagos y dispersión</h2>
          <p class="text-sm text-gray-600 mb-4 max-w-2xl">Tú le confirmas cosas a LendusFind. Misma firma HMAC (firmas tú), idempotente por <code class="bg-gray-100 px-1 rounded text-xs">external_event_id</code>.</p>

          <h3 class="text-sm font-semibold text-gray-800 mb-2">Confirmar la dispersión</h3>
          <div class="flex items-center gap-2 mb-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 font-mono text-xs">
            <span class="bg-indigo-600 text-white font-bold px-2 py-0.5 rounded">POST</span>
            <span class="text-gray-700">/api/webhooks/inbound/<span class="text-primary-600">{endpoint}</span>/disbursement</span>
          </div>
          <div class="grid md:grid-cols-2 gap-3">
            <div>
              <p class="text-[11px] font-mono uppercase tracking-wide text-gray-400 mb-1">Request</p>
              <pre v-pre class="bg-gray-900 text-gray-100 rounded-xl p-4 overflow-x-auto text-xs font-mono leading-relaxed">{
  "external_event_id": "cartera-disb-000123",
  "loan_id": "019f6404-9a8b-...",
  "external_id": "CARTERA-CTO-556677",
  "external_system": "CORE_CARTERA",
  "disbursement_reference": "STP-2026071500012345",
  "disbursed_at": "2026-07-15T18:05:10Z"
}</pre>
            </div>
            <div>
              <p class="text-[11px] font-mono uppercase tracking-wide text-gray-400 mb-1">Respuesta · 200</p>
              <pre v-pre class="bg-gray-900 text-gray-100 rounded-xl p-4 overflow-x-auto text-xs font-mono leading-relaxed">{
  "success": true,
  "data": {
    "status": "processed",
    "loan_status": "ACTIVE",
    "application_status": "SYNCED"
  }
}</pre>
            </div>
          </div>

          <h3 class="text-sm font-semibold text-gray-800 mb-2 mt-6">Confirmar un pago aplicado</h3>
          <div class="flex items-center gap-2 mb-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 font-mono text-xs">
            <span class="bg-indigo-600 text-white font-bold px-2 py-0.5 rounded">POST</span>
            <span class="text-gray-700">/api/webhooks/inbound/<span class="text-primary-600">{endpoint}</span>/payments</span>
          </div>
          <div class="grid md:grid-cols-2 gap-3">
            <div>
              <p class="text-[11px] font-mono uppercase tracking-wide text-gray-400 mb-1">Request</p>
              <pre v-pre class="bg-gray-900 text-gray-100 rounded-xl p-4 overflow-x-auto text-xs font-mono leading-relaxed">{
  "external_event_id": "cartera-pay-000123",
  "loan_id": "019f6404-9a8b-...",
  "amount": 200.00,
  "channel": "STP",
  "provider_reference": "STP-88213",
  "paid_at": "2026-07-18T12:29:41Z"
}</pre>
            </div>
            <div>
              <p class="text-[11px] font-mono uppercase tracking-wide text-gray-400 mb-1">Respuesta · 200</p>
              <pre v-pre class="bg-gray-900 text-gray-100 rounded-xl p-4 overflow-x-auto text-xs font-mono leading-relaxed">{
  "success": true,
  "data": { "status": "processed", "outstanding_balance": 205.59 }
}

// Reintento (mismo external_event_id):
{ "status": "duplicate", "outstanding_balance": 205.59 }</pre>
            </div>
          </div>
          <p class="text-xs text-gray-500 mt-3">Firma inválida o timestamp fuera de ventana → <span class="font-mono text-red-600">401</span>. Reintento con el mismo <code class="bg-gray-100 px-1 rounded">external_event_id</code> → <span class="font-mono text-amber-600">duplicate</span> (sin doble cargo).</p>
        </section>

        <!-- 06 RECONSULTA -->
        <section id="reconsulta" class="scroll-mt-6">
          <h2 class="text-lg font-bold text-gray-900 mb-3"><span class="font-mono text-primary-600 text-sm mr-2">06</span>API de re-consulta</h2>
          <p class="text-sm text-gray-600 mb-3 max-w-2xl">Si perdiste un webhook o necesitas reconciliar, relee el recurso. Devuelve el mismo <code class="bg-gray-100 px-1 rounded text-xs">data</code>. Auth: token con ability <code class="bg-gray-100 px-1 rounded text-xs">integration</code>, scoped por tenant.</p>
          <div class="space-y-2">
            <div class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 font-mono text-xs">
              <span class="bg-emerald-600 text-white font-bold px-2 py-0.5 rounded">GET</span>
              <span class="text-gray-700">/api/v2/integration/applications/<span class="text-primary-600">{id}</span></span>
            </div>
            <div class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 font-mono text-xs">
              <span class="bg-emerald-600 text-white font-bold px-2 py-0.5 rounded">GET</span>
              <span class="text-gray-700">/api/v2/integration/loans/<span class="text-primary-600">{id}</span></span>
            </div>
            <div class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 font-mono text-xs">
              <span class="bg-emerald-600 text-white font-bold px-2 py-0.5 rounded">GET</span>
              <span class="text-gray-700">/api/v2/integration/documents/<span class="text-primary-600">{id}</span>/download</span>
            </div>
          </div>
          <p class="text-xs text-gray-500 mt-3">
            Los <code class="bg-gray-100 px-1 rounded">download_url</code> de <code class="bg-gray-100 px-1 rounded">documents[]</code> apuntan al endpoint de descarga:
            pega con tu token de integración y recibes el archivo (INE, selfie, comprobante) con una URL firmada <b>fresca</b>,
            sin arriesgar URLs vencidas en el payload o el log de entregas.
          </p>
        </section>

        <!-- 07 ERRORES -->
        <section id="errores" class="scroll-mt-6">
          <h2 class="text-lg font-bold text-gray-900 mb-3"><span class="font-mono text-primary-600 text-sm mr-2">07</span>Errores y códigos</h2>
          <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                <tr><th class="text-left px-4 py-2.5">Situación</th><th class="text-left px-4 py-2.5">Respuesta</th></tr>
              </thead>
              <tbody class="divide-y divide-gray-100 text-gray-600">
                <tr><td class="px-4 py-2.5">Firma inválida o timestamp fuera de ventana (entrante)</td><td class="px-4 py-2.5 font-mono text-red-600">401</td></tr>
                <tr><td class="px-4 py-2.5"><code class="bg-gray-100 px-1 rounded text-xs">external_event_id</code> repetido (entrante)</td><td class="px-4 py-2.5 font-mono text-amber-600">200 · duplicate</td></tr>
                <tr><td class="px-4 py-2.5">Recurso de otro tenant (re-consulta)</td><td class="px-4 py-2.5 font-mono text-red-600">404</td></tr>
                <tr><td class="px-4 py-2.5">Token sin ability <code class="bg-gray-100 px-1 rounded text-xs">integration</code></td><td class="px-4 py-2.5 font-mono text-red-600">403</td></tr>
                <tr><td class="px-4 py-2.5">Tu endpoint responde no-2xx (saliente)</td><td class="px-4 py-2.5">reintenta; tras 5 fallos → FAILED</td></tr>
              </tbody>
            </table>
          </div>
          <div class="mt-6">
            <AppButton variant="outline" @click="router.push({ name: 'admin-webhooks' })">← Volver a Webhooks</AppButton>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* El resaltado y el botón se inyectan por JS (innerHTML), así que van con :deep(). */
.int-docs :deep(pre) { position: relative; }
.int-docs :deep(.copy-btn) {
  position: absolute; top: .5rem; right: .5rem;
  font-family: ui-monospace, "SF Mono", Menlo, monospace; font-size: .68rem; line-height: 1.4;
  background: rgba(255, 255, 255, .07); border: 1px solid rgba(255, 255, 255, .16);
  color: #cbd5e1; border-radius: 6px; padding: .25rem .55rem; cursor: pointer;
  transition: background .15s, color .15s;
}
.int-docs :deep(.copy-btn:hover) { background: rgba(255, 255, 255, .16); color: #fff; }
.int-docs :deep(.t-key)  { color: #c4b5fd; }
.int-docs :deep(.t-str)  { color: #9fe0c0; }
.int-docs :deep(.t-num)  { color: #f5b8a0; }
.int-docs :deep(.t-bool),
.int-docs :deep(.t-null) { color: #8ab4f8; }
.int-docs :deep(.t-cmt)  { color: #8b84a3; font-style: italic; }
</style>
