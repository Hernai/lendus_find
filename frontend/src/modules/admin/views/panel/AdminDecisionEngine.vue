<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores'
import { getErrorMessage, isAxiosError } from '@/types/api'
import AppButton from '@/components/common/AppButton.vue'
import decisionPolicyService, {
  type DecisionMode,
  type DecisionPolicyRules,
  type V2DecisionPolicy,
  type V2DryRunProfile,
  type V2DryRunResult,
} from '@/modules/admin/services/decision-policy.staff.service'

/**
 * Configurador del motor de decisión (Matriz Maestra MoneyCapital).
 *
 * Política de TENANT (filtros de entrada: gate telefónico + cooldown) y
 * políticas por PRODUCTO (scoring, bandas, oferta, graduación), versionadas
 * con activar/rollback, modos off/shadow/active y probador de perfiles.
 * Edita SUPER_ADMIN (la API valida canConfigureTenant); ADMIN consulta.
 */

const toast = useToast()
const authStore = useAuthStore()
const canEdit = computed(() => authStore.isSuperAdmin)

const loading = ref(true)
const policies = ref<V2DecisionPolicy[]>([])

// =====================================================
// Agrupación por alcance
// =====================================================

const tenantVersions = computed(() =>
  policies.value.filter((p) => p.product_id === null).sort((a, b) => b.version - a.version))
const tenantActive = computed(() => tenantVersions.value.find((p) => p.is_active) ?? null)

interface ProductGroup {
  productId: string
  productName: string
  productCode: string
  versions: V2DecisionPolicy[]
  active: V2DecisionPolicy | null
}

const productGroups = computed<ProductGroup[]>(() => {
  const groups = new Map<string, ProductGroup>()
  for (const p of policies.value) {
    if (!p.product_id) continue
    const g = groups.get(p.product_id) ?? {
      productId: p.product_id,
      productName: p.product?.name ?? 'Producto',
      productCode: p.product?.code ?? '',
      versions: [],
      active: null,
    }
    g.versions.push(p)
    if (p.is_active) g.active = p
    groups.set(p.product_id, g)
  }
  return [...groups.values()].map((g) => ({
    ...g,
    versions: g.versions.sort((a, b) => b.version - a.version),
  }))
})

const modeBadgeClass = (mode: DecisionMode) => ({
  OFF: 'bg-gray-100 text-gray-600',
  SHADOW: 'bg-amber-100 text-amber-700',
  ACTIVE: 'bg-green-100 text-green-700',
}[mode])

const modeLabel = (mode: DecisionMode) =>
  ({ OFF: 'Apagado', SHADOW: 'Modo sombra', ACTIVE: 'Activo' })[mode]

const formatDate = (d: string | null) =>
  d ? new Date(d).toLocaleString('es-MX', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—'

// =====================================================
// Carga
// =====================================================

async function load() {
  loading.value = true
  try {
    const res = await decisionPolicyService.list()
    policies.value = res.data?.policies ?? []
  } catch (error) {
    toast.error(getErrorMessage(error, 'No se pudieron cargar las políticas'))
  } finally {
    loading.value = false
  }
}

onMounted(load)

// =====================================================
// Versiones expandibles
// =====================================================

const expandedVersions = reactive(new Map<string, boolean>())
const versionsKey = (productId: string | null) => productId ?? 'tenant'
const isExpanded = (k: string | null) => expandedVersions.get(versionsKey(k)) ?? false
const toggleVersions = (k: string | null) =>
  expandedVersions.set(versionsKey(k), !isExpanded(k))

// =====================================================
// Activar / cambiar modo
// =====================================================

const activating = ref(false)

async function activateVersion(policy: V2DecisionPolicy, mode?: DecisionMode) {
  const targetMode = mode ?? policy.mode
  if (targetMode === 'ACTIVE') {
    const ok = window.confirm(
      `El motor comenzará a DECIDIR solicitudes reales (v${policy.version} en modo Activo): ` +
      'ofertas automáticas, revisiones y rechazos sin intervención del staff. ¿Continuar?'
    )
    if (!ok) return
  }
  activating.value = true
  try {
    const res = await decisionPolicyService.activate(policy.id, mode)
    toast.success(res.message ?? 'Versión activada')
    await load()
  } catch (error) {
    toast.error(getErrorMessage(error, 'No se pudo activar la versión'))
  } finally {
    activating.value = false
  }
}

// =====================================================
// Editor (borradores)
// =====================================================

const editorOpen = ref(false)
const editorSaving = ref(false)
const editorIsTenant = ref(false)
const editorProductId = ref<string | null>(null)
const editorProductName = ref('')
/** Borrador existente que se está editando (null = se creará versión nueva). */
const editorDraftId = ref<string | null>(null)
const editorNotes = ref('')
const editorErrors = ref<string[]>([])
const editorRules = ref<DecisionPolicyRules>({})

function cloneRules(rules: DecisionPolicyRules): DecisionPolicyRules {
  return JSON.parse(JSON.stringify(rules ?? {}))
}

/** Abre el editor partiendo de una versión (activa o borrador). */
function openEditor(base: V2DecisionPolicy | null, productId: string | null, productName = '') {
  editorIsTenant.value = productId === null
  editorProductId.value = productId
  editorProductName.value = productName
  editorErrors.value = []
  // Borrador puro (nunca activado) se edita en sitio; lo demás crea versión nueva.
  const isPureDraft = !!base && !base.is_active && !base.activated_at
  editorDraftId.value = isPureDraft ? base!.id : null
  editorNotes.value = base?.notes ?? ''
  editorRules.value = base
    ? cloneRules(base.rules)
    : (productId === null ? defaultTenantRules() : defaultProductRules())
  ensureEditorShape()
  editorOpen.value = true
}

function defaultTenantRules(): DecisionPolicyRules {
  return {
    phone_risk_gate: { enabled: true, flag_from: 401, block_from: 601, fail_mode: 'open' },
    cooldown: { days: 30 },
  }
}

function defaultProductRules(): DecisionPolicyRules {
  return {
    scoring: { variables: [], band_cutoffs: [{ min_score: 0, band: 'BASE' }] },
    bands: [{ key: 'BASE', min_amount: 300, max_amount: 400, target_share_pct: 85 }],
    first_credit: { term_days: 7 },
    offer: { validity_hours: 72, reminder_hours_before: 24 },
    review: { input_timeout_minutes: 30 },
    reject: [{ rule: 'kyc_failed' }, { rule: 'identity_mismatch' }],
    graduation: {
      levels: [{ level: 0, max_amount: 1000, max_term_days: 7 }],
      advance: { on_time: 1, late_or_extension: 0, max_late_days_for_auto: 5 },
    },
  }
}

/** Garantiza que todas las secciones existan para que el v-model no truene. */
function ensureEditorShape() {
  const r = editorRules.value
  if (editorIsTenant.value) {
    r.phone_risk_gate ??= { enabled: false, flag_from: 401, block_from: 601, fail_mode: 'open' }
    r.cooldown ??= { days: 30 }
  } else {
    r.scoring ??= { variables: [], band_cutoffs: [] }
    r.scoring.variables ??= []
    r.scoring.band_cutoffs ??= []
    r.bands ??= []
    r.first_credit ??= { term_days: 7 }
    r.offer ??= { validity_hours: 72, reminder_hours_before: 24 }
    r.review ??= { input_timeout_minutes: 30 }
    r.graduation ??= { levels: [], advance: { on_time: 1, late_or_extension: 0, max_late_days_for_auto: 5 } }
    r.graduation.levels ??= []
  }
}

// --- Repetidores del editor de producto ---

function addBand() {
  editorRules.value.bands!.push({ key: '', min_amount: 0, max_amount: 0, target_share_pct: 0 })
}
function removeBand(i: number) {
  editorRules.value.bands!.splice(i, 1)
}
function addCutoff() {
  editorRules.value.scoring!.band_cutoffs.push({ min_score: 0, band: '' })
}
function removeCutoff(i: number) {
  editorRules.value.scoring!.band_cutoffs.splice(i, 1)
}
function addVariable() {
  editorRules.value.scoring!.variables.push({ key: '', points: {} })
}
function removeVariable(i: number) {
  editorRules.value.scoring!.variables.splice(i, 1)
}
function addLevel() {
  const levels = editorRules.value.graduation!.levels
  const next = levels.length ? Math.max(...levels.map((l) => l.level)) + 1 : 0
  levels.push({ level: next, max_amount: 0, max_term_days: 7 })
}
function removeLevel(i: number) {
  editorRules.value.graduation!.levels.splice(i, 1)
}

// Puntos por variable: se editan como filas (valor → puntos) sobre un Map
// derivado, sin colgar props sintéticas en los objetos del backend.
const variableAt = (i: number) => editorRules.value.scoring?.variables[i]

const variablePointRows = (i: number) => {
  const variable = variableAt(i)
  if (!variable) return []
  return Object.entries(variable.points).map(([value, points]) => ({ value, points }))
}

function setVariablePoint(i: number, oldValue: string, newValue: string, points: number) {
  const variable = variableAt(i)
  if (!variable) return
  const map = { ...variable.points }
  if (oldValue !== newValue) delete map[oldValue]
  map[newValue] = points
  variable.points = map
}
function addVariablePoint(i: number) {
  const variable = variableAt(i)
  if (!variable) return
  variable.points = { ...variable.points, '': 0 }
}
function removeVariablePoint(i: number, value: string) {
  const variable = variableAt(i)
  if (!variable) return
  const map = { ...variable.points }
  delete map[value]
  variable.points = map
}

async function saveEditor() {
  editorSaving.value = true
  editorErrors.value = []
  try {
    const payload = {
      rules: editorRules.value,
      notes: editorNotes.value || undefined,
    }
    const res = editorDraftId.value
      ? await decisionPolicyService.updateDraft(editorDraftId.value, payload)
      : await decisionPolicyService.createDraft({
          ...payload,
          product_id: editorProductId.value,
        })
    toast.success(res.message ?? 'Borrador guardado')
    editorOpen.value = false
    await load()
  } catch (error) {
    if (isAxiosError(error) && error.response?.data?.errors?.rules) {
      editorErrors.value = error.response.data.errors.rules as string[]
    } else {
      toast.error(getErrorMessage(error, 'No se pudo guardar el borrador'))
    }
  } finally {
    editorSaving.value = false
  }
}

// =====================================================
// Probador (dry-run)
// =====================================================

const testerOpen = ref(false)
const testerRunning = ref(false)
const testerPolicyId = ref('')
const testerResult = ref<V2DryRunResult | null>(null)
const testerMode = ref<'first_credit' | 'renewal'>('first_credit')

const testerProfile = reactive({
  requested_amount: 900,
  salary_range: 'R_6001_9000',
  employment_type: 'EMPLOYEE',
  education_level: 'HIGH_SCHOOL',
  online_loans_count: '1',
  phone_risk_score: 200 as number | null,
  kyc_status: 'VERIFIED',
})

const testerRenewalLoans = ref<Array<{ on_time: boolean; used_extension: boolean; late_days: number }>>([
  { on_time: true, used_extension: false, late_days: 0 },
])

const testablePolicies = computed(() =>
  policies.value.filter((p) => p.product_id !== null))

function openTester(policy?: V2DecisionPolicy) {
  testerResult.value = null
  testerPolicyId.value = policy?.id ?? (testablePolicies.value[0]?.id ?? '')
  testerOpen.value = true
}

function addRenewalLoan() {
  testerRenewalLoans.value.push({ on_time: true, used_extension: false, late_days: 0 })
}
function removeRenewalLoan(i: number) {
  testerRenewalLoans.value.splice(i, 1)
}

async function runTester() {
  if (!testerPolicyId.value) return
  testerRunning.value = true
  testerResult.value = null
  try {
    const profile: V2DryRunProfile = testerMode.value === 'renewal'
      ? { renewal_history: testerRenewalLoans.value }
      : {
          requested_amount: testerProfile.requested_amount,
          kyc_status: testerProfile.kyc_status,
          phone_risk_score: testerProfile.phone_risk_score,
          variables: {
            salary_range: testerProfile.salary_range,
            employment_type: testerProfile.employment_type,
            education_level: testerProfile.education_level,
            online_loans_count: testerProfile.online_loans_count,
          },
        }
    const res = await decisionPolicyService.dryRun(testerPolicyId.value, profile)
    testerResult.value = res.data ?? null
  } catch (error) {
    toast.error(getErrorMessage(error, 'No se pudo correr la prueba'))
  } finally {
    testerRunning.value = false
  }
}

const outcomeBadgeClass = (outcome: string) => ({
  OFFER: 'bg-green-100 text-green-700',
  ALLOW: 'bg-green-100 text-green-700',
  REVIEW: 'bg-amber-100 text-amber-700',
  FLAG: 'bg-amber-100 text-amber-700',
  REJECT: 'bg-red-100 text-red-700',
  BLOCK: 'bg-red-100 text-red-700',
  NO_OFFER: 'bg-gray-100 text-gray-600',
}[outcome] ?? 'bg-gray-100 text-gray-600')

const outcomeLabel = (outcome: string) => ({
  OFFER: 'Oferta automática',
  REVIEW: 'Revisión manual',
  REJECT: 'Rechazo',
  NO_OFFER: 'Sin oferta',
  ALLOW: 'Permitir',
  FLAG: 'Alerta',
  BLOCK: 'Bloquear',
}[outcome] ?? outcome)

const formatMoney = (n: number) =>
  new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(n)
</script>

<template>
  <div class="p-6 max-w-6xl mx-auto space-y-6">
    <div class="flex items-start justify-between gap-4 flex-wrap">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Motor de decisión</h1>
        <p class="text-sm text-gray-500 mt-1">
          Políticas versionadas de la Matriz Maestra: filtros de entrada, scoring de bandas,
          ofertas automáticas y graduación de renovaciones. El modo sombra registra qué habría
          decidido el motor sin actuar.
        </p>
      </div>
      <AppButton v-if="testablePolicies.length" variant="secondary" @click="openTester()">
        Probar un perfil
      </AppButton>
    </div>

    <div v-if="loading" class="flex justify-center py-16">
      <div class="w-10 h-10 border-2 border-primary-600 border-t-transparent rounded-full animate-spin" />
    </div>

    <template v-else>
      <!-- ============ POLÍTICA DE TENANT (filtros de entrada) ============ -->
      <section class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <div class="flex items-start justify-between gap-3 flex-wrap">
          <div>
            <h2 class="font-semibold text-gray-900">Filtros de entrada (tenant)</h2>
            <p class="text-xs text-gray-500 mt-0.5">Gate telefónico (Regla 21) y cooldown post-rechazo (Regla 01)</p>
          </div>
          <div class="flex items-center gap-2">
            <span v-if="tenantActive" class="text-xs px-2 py-1 rounded-full font-medium" :class="modeBadgeClass(tenantActive.mode)">
              v{{ tenantActive.version }} · {{ modeLabel(tenantActive.mode) }}
            </span>
            <span v-else class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-500">Sin política activa</span>
          </div>
        </div>

        <div v-if="tenantActive" class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4 text-sm">
          <div class="bg-gray-50 rounded-lg p-3">
            <div class="text-xs text-gray-500">Gate telefónico</div>
            <div class="font-semibold text-gray-900">{{ tenantActive.rules.phone_risk_gate?.enabled ? 'Encendido' : 'Apagado' }}</div>
          </div>
          <div class="bg-gray-50 rounded-lg p-3">
            <div class="text-xs text-gray-500">Alerta desde</div>
            <div class="font-semibold text-gray-900">{{ tenantActive.rules.phone_risk_gate?.flag_from ?? '—' }}</div>
          </div>
          <div class="bg-gray-50 rounded-lg p-3">
            <div class="text-xs text-gray-500">Bloqueo desde</div>
            <div class="font-semibold text-gray-900">{{ tenantActive.rules.phone_risk_gate?.block_from ?? '—' }}</div>
          </div>
          <div class="bg-gray-50 rounded-lg p-3">
            <div class="text-xs text-gray-500">Cooldown</div>
            <div class="font-semibold text-gray-900">{{ tenantActive.rules.cooldown?.days ?? 0 }} días</div>
          </div>
        </div>

        <div class="flex items-center gap-2 mt-4 flex-wrap">
          <AppButton v-if="canEdit" size="sm" variant="secondary" @click="openEditor(tenantActive, null)">
            {{ tenantActive ? 'Editar (nueva versión)' : 'Crear política' }}
          </AppButton>
          <button
            v-if="tenantVersions.length"
            type="button"
            class="text-sm text-primary-600 font-medium px-2 py-1"
            @click="toggleVersions(null)"
          >
            {{ isExpanded(null) ? 'Ocultar versiones' : `Versiones (${tenantVersions.length})` }}
          </button>
        </div>

        <div v-if="isExpanded(null)" class="mt-3 border-t border-gray-100 pt-3 space-y-2">
          <div
            v-for="v in tenantVersions"
            :key="v.id"
            class="flex items-center justify-between gap-3 text-sm bg-gray-50 rounded-lg px-3 py-2"
          >
            <div class="min-w-0">
              <span class="font-semibold text-gray-900">v{{ v.version }}</span>
              <span class="text-xs px-2 py-0.5 rounded-full ml-2" :class="v.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600'">
                {{ v.is_active ? `Activa · ${modeLabel(v.mode)}` : (v.activated_at ? 'Anterior' : 'Borrador') }}
              </span>
              <div class="text-xs text-gray-500 truncate">{{ v.notes || 'Sin notas' }} · {{ formatDate(v.created_at) }}</div>
            </div>
            <div v-if="canEdit" class="flex gap-2 shrink-0">
              <template v-if="!v.is_active">
                <AppButton size="sm" variant="secondary" :disabled="activating" @click="activateVersion(v)">Activar</AppButton>
              </template>
              <template v-else>
                <select
                  class="text-xs border border-gray-300 rounded-lg px-2 py-1"
                  :value="v.mode"
                  @change="activateVersion(v, ($event.target as HTMLSelectElement).value as DecisionMode)"
                >
                  <option value="OFF">Apagado</option>
                  <option value="SHADOW">Modo sombra</option>
                  <option value="ACTIVE">Activo</option>
                </select>
              </template>
            </div>
          </div>
        </div>
      </section>

      <!-- ============ POLÍTICAS POR PRODUCTO ============ -->
      <section
        v-for="group in productGroups"
        :key="group.productId"
        class="bg-white rounded-xl border border-gray-200 shadow-sm p-5"
      >
        <div class="flex items-start justify-between gap-3 flex-wrap">
          <div>
            <h2 class="font-semibold text-gray-900">{{ group.productName }}</h2>
            <p class="text-xs text-gray-500 mt-0.5">{{ group.productCode }}</p>
          </div>
          <span v-if="group.active" class="text-xs px-2 py-1 rounded-full font-medium" :class="modeBadgeClass(group.active.mode)">
            v{{ group.active.version }} · {{ modeLabel(group.active.mode) }}
          </span>
          <span v-else class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-500">Sin versión activa</span>
        </div>

        <div v-if="group.active" class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4 text-sm">
          <div class="bg-gray-50 rounded-lg p-3">
            <div class="text-xs text-gray-500">Bandas</div>
            <div class="font-semibold text-gray-900">
              {{ (group.active.rules.bands ?? []).map(b => b.key).join(', ') || '—' }}
            </div>
          </div>
          <div class="bg-gray-50 rounded-lg p-3">
            <div class="text-xs text-gray-500">Primer crédito</div>
            <div class="font-semibold text-gray-900">{{ group.active.rules.first_credit?.term_days ?? '—' }} días</div>
          </div>
          <div class="bg-gray-50 rounded-lg p-3">
            <div class="text-xs text-gray-500">Vigencia de oferta</div>
            <div class="font-semibold text-gray-900">{{ group.active.rules.offer?.validity_hours ?? '—' }} h</div>
          </div>
          <div class="bg-gray-50 rounded-lg p-3">
            <div class="text-xs text-gray-500">Niveles de graduación</div>
            <div class="font-semibold text-gray-900">{{ (group.active.rules.graduation?.levels ?? []).length }}</div>
          </div>
        </div>

        <div class="flex items-center gap-2 mt-4 flex-wrap">
          <AppButton v-if="canEdit" size="sm" variant="secondary" @click="openEditor(group.active, group.productId, group.productName)">
            {{ group.active ? 'Editar (nueva versión)' : 'Crear política' }}
          </AppButton>
          <AppButton size="sm" variant="secondary" @click="openTester(group.active ?? group.versions[0])">Probar</AppButton>
          <button
            type="button"
            class="text-sm text-primary-600 font-medium px-2 py-1"
            @click="toggleVersions(group.productId)"
          >
            {{ isExpanded(group.productId) ? 'Ocultar versiones' : `Versiones (${group.versions.length})` }}
          </button>
        </div>

        <div v-if="isExpanded(group.productId)" class="mt-3 border-t border-gray-100 pt-3 space-y-2">
          <div
            v-for="v in group.versions"
            :key="v.id"
            class="flex items-center justify-between gap-3 text-sm bg-gray-50 rounded-lg px-3 py-2"
          >
            <div class="min-w-0">
              <span class="font-semibold text-gray-900">v{{ v.version }}</span>
              <span class="text-xs px-2 py-0.5 rounded-full ml-2" :class="v.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600'">
                {{ v.is_active ? `Activa · ${modeLabel(v.mode)}` : (v.activated_at ? 'Anterior' : 'Borrador') }}
              </span>
              <div class="text-xs text-gray-500 truncate">{{ v.notes || 'Sin notas' }} · {{ formatDate(v.created_at) }}</div>
            </div>
            <div v-if="canEdit" class="flex gap-2 shrink-0 items-center">
              <AppButton
                v-if="!v.is_active && !v.activated_at"
                size="sm"
                variant="secondary"
                @click="openEditor(v, group.productId, group.productName)"
              >Editar</AppButton>
              <AppButton v-if="!v.is_active" size="sm" variant="secondary" :disabled="activating" @click="activateVersion(v)">Activar</AppButton>
              <select
                v-else
                class="text-xs border border-gray-300 rounded-lg px-2 py-1"
                :value="v.mode"
                @change="activateVersion(v, ($event.target as HTMLSelectElement).value as DecisionMode)"
              >
                <option value="OFF">Apagado</option>
                <option value="SHADOW">Modo sombra</option>
                <option value="ACTIVE">Activo</option>
              </select>
            </div>
          </div>
        </div>
      </section>

      <div v-if="!productGroups.length && !tenantVersions.length" class="text-center text-gray-500 py-12">
        No hay políticas configuradas todavía.
        <template v-if="canEdit"> Crea la política de tenant o corre el seeder del tenant piloto.</template>
      </div>
    </template>

    <!-- ============ MODAL EDITOR ============ -->
    <div v-if="editorOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="editorOpen = false">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto p-6 space-y-5">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-900">
            {{ editorDraftId ? 'Editar borrador' : 'Nueva versión' }} —
            {{ editorIsTenant ? 'Filtros de entrada' : editorProductName }}
          </h3>
          <button type="button" class="text-gray-400 hover:text-gray-600 text-xl leading-none" @click="editorOpen = false">×</button>
        </div>

        <div v-if="editorErrors.length" class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg p-3 space-y-1">
          <div v-for="(err, i) in editorErrors" :key="i">• {{ err }}</div>
        </div>

        <!-- ------- Política de tenant ------- -->
        <template v-if="editorIsTenant">
          <fieldset class="border border-gray-200 rounded-xl p-4 space-y-3">
            <legend class="text-sm font-semibold text-gray-700 px-1">Gate telefónico (Regla 21)</legend>
            <label class="flex items-center gap-2 text-sm text-gray-700">
              <input v-model="editorRules.phone_risk_gate!.enabled" type="checkbox" class="w-4 h-4 rounded text-primary-600" />
              Encendido (evalúa el Phone Risk Score antes del paso de INE)
            </label>
            <div class="grid grid-cols-2 gap-3">
              <label class="text-sm text-gray-600">
                Alerta desde (score)
                <input v-model.number="editorRules.phone_risk_gate!.flag_from" type="number" min="0" max="1000" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
              </label>
              <label class="text-sm text-gray-600">
                Bloqueo desde (score)
                <input v-model.number="editorRules.phone_risk_gate!.block_from" type="number" min="0" max="1000" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
              </label>
            </div>
            <p class="text-xs text-gray-400">Fail-open: si Nubarium falla, el cliente continúa marcado a revisión — nunca se bloquea por falla técnica.</p>
          </fieldset>

          <fieldset class="border border-gray-200 rounded-xl p-4">
            <legend class="text-sm font-semibold text-gray-700 px-1">Cooldown post-rechazo (Regla 01)</legend>
            <label class="text-sm text-gray-600 block max-w-xs">
              Días de bloqueo tras un rechazo
              <input v-model.number="editorRules.cooldown!.days" type="number" min="0" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </label>
          </fieldset>
        </template>

        <!-- ------- Política de producto ------- -->
        <template v-else>
          <fieldset class="border border-gray-200 rounded-xl p-4 space-y-3">
            <legend class="text-sm font-semibold text-gray-700 px-1">Bandas de oferta inicial</legend>
            <div v-for="(band, i) in editorRules.bands" :key="i" class="grid grid-cols-[1fr_1fr_1fr_1fr_auto] gap-2 items-end">
              <label class="text-xs text-gray-500">Clave
                <input v-model="band.key" type="text" class="mt-1 w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" />
              </label>
              <label class="text-xs text-gray-500">Monto mín
                <input v-model.number="band.min_amount" type="number" min="0" class="mt-1 w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" />
              </label>
              <label class="text-xs text-gray-500">Monto máx (cupo)
                <input v-model.number="band.max_amount" type="number" min="0" class="mt-1 w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" />
              </label>
              <label class="text-xs text-gray-500">Meta %
                <input v-model.number="band.target_share_pct" type="number" min="0" max="100" class="mt-1 w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" />
              </label>
              <button type="button" class="text-red-500 text-sm pb-2" @click="removeBand(i)">Quitar</button>
            </div>
            <button type="button" class="text-sm text-primary-600 font-medium" @click="addBand">+ Agregar banda</button>
            <p class="text-xs text-gray-400">Los porcentajes son metas de monitoreo — la asignación real la decide el scoring.</p>
          </fieldset>

          <fieldset class="border border-gray-200 rounded-xl p-4 space-y-3">
            <legend class="text-sm font-semibold text-gray-700 px-1">Cortes de puntaje → banda</legend>
            <div v-for="(cutoff, i) in editorRules.scoring!.band_cutoffs" :key="i" class="grid grid-cols-[1fr_1fr_auto] gap-2 items-end">
              <label class="text-xs text-gray-500">Puntaje mínimo
                <input v-model.number="cutoff.min_score" type="number" class="mt-1 w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" />
              </label>
              <label class="text-xs text-gray-500">Banda
                <select v-model="cutoff.band" class="mt-1 w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm">
                  <option v-for="b in editorRules.bands" :key="b.key" :value="b.key">{{ b.key }}</option>
                </select>
              </label>
              <button type="button" class="text-red-500 text-sm pb-2" @click="removeCutoff(i)">Quitar</button>
            </div>
            <button type="button" class="text-sm text-primary-600 font-medium" @click="addCutoff">+ Agregar corte</button>
          </fieldset>

          <fieldset class="border border-gray-200 rounded-xl p-4 space-y-4">
            <legend class="text-sm font-semibold text-gray-700 px-1">Variables de scoring</legend>
            <div v-for="(variable, i) in editorRules.scoring!.variables" :key="i" class="border border-gray-100 rounded-lg p-3 space-y-2">
              <div class="flex items-end gap-2">
                <label class="text-xs text-gray-500 flex-1">Variable (llave del insumo)
                  <input v-model="variable.key" type="text" placeholder="salary_range" class="mt-1 w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" />
                </label>
                <button type="button" class="text-red-500 text-sm pb-2" @click="removeVariable(i)">Quitar variable</button>
              </div>
              <div
                v-for="row in variablePointRows(i)"
                :key="row.value"
                class="grid grid-cols-[2fr_1fr_auto] gap-2 items-center"
              >
                <input
                  :value="row.value"
                  type="text"
                  placeholder="Valor (ej. GT_15000)"
                  class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm"
                  @change="setVariablePoint(i, row.value, ($event.target as HTMLInputElement).value, row.points)"
                />
                <input
                  :value="row.points"
                  type="number"
                  class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm"
                  @change="setVariablePoint(i, row.value, row.value, Number(($event.target as HTMLInputElement).value))"
                />
                <button type="button" class="text-red-500 text-xs" @click="removeVariablePoint(i, row.value)">Quitar</button>
              </div>
              <button type="button" class="text-xs text-primary-600 font-medium" @click="addVariablePoint(i)">+ Valor</button>
            </div>
            <button type="button" class="text-sm text-primary-600 font-medium" @click="addVariable">+ Agregar variable</button>
          </fieldset>

          <fieldset class="border border-gray-200 rounded-xl p-4 grid grid-cols-2 md:grid-cols-4 gap-3">
            <legend class="text-sm font-semibold text-gray-700 px-1">Oferta y revisión</legend>
            <label class="text-xs text-gray-500">Plazo 1er crédito (días)
              <input v-model.number="editorRules.first_credit!.term_days" type="number" min="1" class="mt-1 w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" />
            </label>
            <label class="text-xs text-gray-500">Vigencia oferta (horas)
              <input v-model.number="editorRules.offer!.validity_hours" type="number" min="1" class="mt-1 w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" />
            </label>
            <label class="text-xs text-gray-500">Recordatorio (horas antes)
              <input v-model.number="editorRules.offer!.reminder_hours_before" type="number" min="0" class="mt-1 w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" />
            </label>
            <label class="text-xs text-gray-500">Timeout insumos (min)
              <input v-model.number="editorRules.review!.input_timeout_minutes" type="number" min="1" class="mt-1 w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" />
            </label>
          </fieldset>

          <fieldset class="border border-gray-200 rounded-xl p-4 space-y-3">
            <legend class="text-sm font-semibold text-gray-700 px-1">Graduación de renovaciones (Regla 20)</legend>
            <div v-for="(level, i) in editorRules.graduation!.levels" :key="i" class="grid grid-cols-[1fr_1fr_1fr_auto] gap-2 items-end">
              <label class="text-xs text-gray-500">Nivel
                <input v-model.number="level.level" type="number" min="0" class="mt-1 w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" />
              </label>
              <label class="text-xs text-gray-500">Cupo máximo
                <input v-model.number="level.max_amount" type="number" min="0" class="mt-1 w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" />
              </label>
              <label class="text-xs text-gray-500">Plazo máx (días)
                <input v-model.number="level.max_term_days" type="number" min="1" class="mt-1 w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" />
              </label>
              <button type="button" class="text-red-500 text-sm pb-2" @click="removeLevel(i)">Quitar</button>
            </div>
            <button type="button" class="text-sm text-primary-600 font-medium" @click="addLevel">+ Agregar nivel</button>
            <div class="grid grid-cols-3 gap-3 pt-2 border-t border-gray-100">
              <label class="text-xs text-gray-500">Puntual sube (+niveles)
                <input v-model.number="editorRules.graduation!.advance.on_time" type="number" min="0" class="mt-1 w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" />
              </label>
              <label class="text-xs text-gray-500">Tarde/prórroga (+niveles)
                <input v-model.number="editorRules.graduation!.advance.late_or_extension" type="number" min="0" class="mt-1 w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" />
              </label>
              <label class="text-xs text-gray-500">Mora máx p/ oferta auto (días)
                <input v-model.number="editorRules.graduation!.advance.max_late_days_for_auto" type="number" min="0" class="mt-1 w-full border border-gray-300 rounded-lg px-2 py-1.5 text-sm" />
              </label>
            </div>
          </fieldset>
        </template>

        <label class="text-sm text-gray-600 block">
          Notas de la versión
          <input v-model="editorNotes" type="text" maxlength="500" placeholder="Ej. Ajuste de umbral tras primera semana de sombra" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
        </label>

        <div class="flex justify-end gap-2 pt-2">
          <AppButton variant="secondary" @click="editorOpen = false">Cancelar</AppButton>
          <AppButton variant="primary" :loading="editorSaving" @click="saveEditor">
            Guardar como borrador
          </AppButton>
        </div>
      </div>
    </div>

    <!-- ============ MODAL PROBADOR ============ -->
    <div v-if="testerOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="testerOpen = false">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-900">Probador de perfiles</h3>
          <button type="button" class="text-gray-400 hover:text-gray-600 text-xl leading-none" @click="testerOpen = false">×</button>
        </div>
        <p class="text-xs text-gray-500">
          Evalúa un perfil hipotético contra una versión de política (activa o borrador) sin tocar solicitudes reales.
        </p>

        <div class="grid grid-cols-2 gap-3">
          <label class="text-sm text-gray-600 col-span-2">
            Versión de política
            <select v-model="testerPolicyId" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
              <option v-for="p in testablePolicies" :key="p.id" :value="p.id">
                {{ p.product?.name }} — v{{ p.version }} {{ p.is_active ? `(activa · ${modeLabel(p.mode)})` : (p.activated_at ? '(anterior)' : '(borrador)') }}
              </option>
            </select>
          </label>

          <label class="text-sm text-gray-600 col-span-2">
            Tipo de evaluación
            <select v-model="testerMode" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
              <option value="first_credit">Primer crédito (scoring de bandas)</option>
              <option value="renewal">Renovación (graduación)</option>
            </select>
          </label>
        </div>

        <template v-if="testerMode === 'first_credit'">
          <div class="grid grid-cols-2 gap-3">
            <label class="text-sm text-gray-600">Monto solicitado
              <input v-model.number="testerProfile.requested_amount" type="number" min="1" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </label>
            <label class="text-sm text-gray-600">Score telefónico (0-1000)
              <input v-model.number="testerProfile.phone_risk_score" type="number" min="0" max="1000" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            </label>
            <label class="text-sm text-gray-600">Rango salarial
              <select v-model="testerProfile.salary_range" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="LT_3000">Menos de $3,000</option>
                <option value="R_3001_6000">$3,001 - $6,000</option>
                <option value="R_6001_9000">$6,001 - $9,000</option>
                <option value="R_9001_12000">$9,001 - $12,000</option>
                <option value="R_12001_15000">$12,001 - $15,000</option>
                <option value="GT_15000">Más de $15,000</option>
              </select>
            </label>
            <label class="text-sm text-gray-600">Actividad laboral
              <select v-model="testerProfile.employment_type" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="EMPLOYEE">Empleado</option>
                <option value="SELF_EMPLOYED">Independiente</option>
                <option value="BUSINESS_OWNER">Dueño de negocio</option>
                <option value="RETIRED">Jubilado</option>
                <option value="HOMEMAKER">Hogar</option>
                <option value="STUDENT">Estudiante</option>
                <option value="UNEMPLOYED">Desempleado</option>
              </select>
            </label>
            <label class="text-sm text-gray-600">Nivel educativo
              <select v-model="testerProfile.education_level" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="PRIMARY">Primaria</option>
                <option value="SECONDARY">Secundaria</option>
                <option value="HIGH_SCHOOL">Preparatoria</option>
                <option value="TECHNICAL">Técnico</option>
                <option value="BACHELOR">Licenciatura</option>
                <option value="MASTER">Maestría</option>
              </select>
            </label>
            <label class="text-sm text-gray-600">Créditos en línea declarados
              <select v-model="testerProfile.online_loans_count" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option v-for="n in ['0','1','2','3','4','5+']" :key="n" :value="n">{{ n }}</option>
              </select>
            </label>
            <label class="text-sm text-gray-600">Estado KYC
              <select v-model="testerProfile.kyc_status" class="mt-1 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="VERIFIED">Verificado</option>
                <option value="REJECTED">Rechazado</option>
              </select>
            </label>
          </div>
        </template>

        <template v-else>
          <div class="space-y-2">
            <div class="text-sm text-gray-600 font-medium">Historial de créditos liquidados (cronológico)</div>
            <div v-for="(loan, i) in testerRenewalLoans" :key="i" class="grid grid-cols-[1fr_1fr_1fr_auto] gap-2 items-center text-sm bg-gray-50 rounded-lg p-2">
              <label class="flex items-center gap-1.5 text-xs text-gray-600">
                <input v-model="loan.on_time" type="checkbox" class="w-4 h-4 rounded text-primary-600" /> Puntual
              </label>
              <label class="flex items-center gap-1.5 text-xs text-gray-600">
                <input v-model="loan.used_extension" type="checkbox" class="w-4 h-4 rounded text-primary-600" /> Con prórroga
              </label>
              <label class="text-xs text-gray-500">Días de atraso
                <input v-model.number="loan.late_days" type="number" min="0" class="mt-0.5 w-full border border-gray-300 rounded px-2 py-1 text-sm" />
              </label>
              <button type="button" class="text-red-500 text-xs" @click="removeRenewalLoan(i)">Quitar</button>
            </div>
            <button type="button" class="text-sm text-primary-600 font-medium" @click="addRenewalLoan">+ Crédito liquidado</button>
          </div>
        </template>

        <AppButton variant="primary" class="w-full" :loading="testerRunning" :disabled="!testerPolicyId" @click="runTester">
          Evaluar perfil
        </AppButton>

        <!-- Resultado -->
        <div v-if="testerResult" class="border border-gray-200 rounded-xl p-4 space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-sm text-gray-500">Decisión (v{{ testerResult.policy_version }})</span>
            <span class="text-sm px-3 py-1 rounded-full font-semibold" :class="outcomeBadgeClass(testerResult.result.outcome)">
              {{ outcomeLabel(testerResult.result.outcome) }}
            </span>
          </div>
          <div class="grid grid-cols-3 gap-3 text-sm">
            <div class="bg-gray-50 rounded-lg p-2 text-center">
              <div class="text-xs text-gray-500">Puntaje</div>
              <div class="font-bold text-gray-900">{{ testerResult.result.score ?? '—' }}</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-2 text-center">
              <div class="text-xs text-gray-500">Banda / Nivel</div>
              <div class="font-bold text-gray-900">{{ testerResult.result.band ?? '—' }}</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-2 text-center">
              <div class="text-xs text-gray-500">Rango autorizado</div>
              <div class="font-bold text-gray-900">
                <template v-if="testerResult.result.range">
                  {{ formatMoney(testerResult.result.range.min_amount) }}–{{ formatMoney(testerResult.result.range.max_amount) }}
                  × {{ testerResult.result.range.max_term_days }}d
                </template>
                <template v-else>—</template>
              </div>
            </div>
          </div>
          <div v-if="testerResult.result.reasons.length" class="text-sm text-gray-600">
            <div class="text-xs text-gray-500 font-medium mb-1">Motivos</div>
            <ul class="list-disc list-inside space-y-0.5">
              <li v-for="(r, i) in testerResult.result.reasons" :key="i">{{ r }}</li>
            </ul>
          </div>
          <details class="text-xs text-gray-500">
            <summary class="cursor-pointer font-medium">Reglas disparadas ({{ testerResult.result.rule_hits.length }})</summary>
            <div v-for="(hit, i) in testerResult.result.rule_hits" :key="i" class="mt-1 bg-gray-50 rounded p-2">
              <span class="font-mono">{{ hit.rule }}</span> → {{ hit.effect }}
              <span v-if="hit.detail" class="block text-gray-400 truncate">{{ JSON.stringify(hit.detail) }}</span>
            </div>
          </details>
        </div>
      </div>
    </div>
  </div>
</template>
