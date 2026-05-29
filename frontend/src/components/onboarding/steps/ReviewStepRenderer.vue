<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useTenantStore } from '@/stores/tenant'
import type { ReviewStep, ReviewFullStep } from '@/types/v2/onboardingStep'
import { bankName } from '@/utils/banks'

/**
 * Renderiza step `review` (pantalla 8): Revisión de información personal.
 *
 * Layout:
 *  - Banner morado "Revisa tu información antes de continuar"
 *  - Lista de DROPDOWN rows editables (icono + label + valor + chevron)
 *  - Tap en cualquier row → navega de regreso al step para editar
 *
 * Tenant-agnóstico.
 */

const props = defineProps<{
  step: ReviewStep | ReviewFullStep
  formData: Record<string, unknown>
}>()

const isFullReview = computed(() => props.step.type === 'review_full')

defineEmits<{
  (e: 'update:modelValue', v: true): void
}>()

const router = useRouter()
const tenantStore = useTenantStore()

interface Row {
  stepId: string
  icon: string
  label: string
  value: string
}

interface Section {
  title: string
  rows: Row[]
}

function formatPhoneDisplay(v: unknown): string {
  const d = String(v ?? '').replace(/\D/g, '')
  if (d.length === 10) return `${d.slice(0, 2)} ${d.slice(2, 6)} ${d.slice(6)}`
  return d || '—'
}

function maskClabe(v: unknown): string {
  const s = String(v ?? '')
  if (!s) return '—'
  if (s.length < 4) return '••••'
  return `•••• ${s.slice(-4)}`
}

// bankName se importa de @/utils/banks (catálogo compartido).

function labelFor(enumName: string, value: unknown): string {
  if (value == null || value === '') return '—'
  const key = enumName.charAt(0).toLowerCase() + enumName.slice(1)
  const list = (tenantStore.options as Record<string, Array<{ value: string; label: string }>>)[key] ?? []
  return list.find((o) => String(o.value) === String(value))?.label ?? String(value)
}

function personalRows(fd: Record<string, unknown>): Row[] {
  return [
    { stepId: 'education', icon: 'cap', label: 'Nivel educativo', value: labelFor('EducationLevel', fd.education_level) },
    { stepId: 'marital', icon: 'user', label: 'Estado civil', value: labelFor('MaritalStatus', fd.marital_status) },
    {
      stepId: 'location',
      icon: 'pin',
      label: 'Estado o ciudad actual',
      value: [labelFor('MexicanState', fd.state), fd.city].filter(Boolean).join(', ') || '—',
    },
    { stepId: 'employment', icon: 'briefcase', label: 'Tipo de actividad o trabajo', value: labelFor('EmploymentType', fd.employment_type) },
    { stepId: 'salary_range', icon: 'wallet', label: 'Rango salarial mensual', value: labelFor('SalaryRange', fd.salary_range) },
  ]
}

function verificationRows(fd: Record<string, unknown>): Row[] {
  const refs = Array.isArray(fd.references) ? (fd.references as Array<{ type?: string; name?: string; phone?: string }>) : []
  const family = refs.find((r) => r.type === 'FAMILY')
  const personal = refs.find((r) => r.type === 'PERSONAL')
  const bank = fd.bank_account as { type?: string; bank_code?: string; account_number?: string } | undefined
  const ine = fd.kyc_ine as { front_image?: string; back_image?: string } | undefined
  const selfie = fd.kyc_face as string | undefined
  return [
    {
      stepId: 'references',
      icon: 'users',
      label: 'Referencia familiar',
      value: family?.name ? `${family.name} · ${formatPhoneDisplay(family.phone)}` : '—',
    },
    {
      stepId: 'references',
      icon: 'users',
      label: 'Referencia personal',
      value: personal?.name ? `${personal.name} · ${formatPhoneDisplay(personal.phone)}` : '—',
    },
    {
      stepId: 'credit_history',
      icon: 'wallet',
      label: 'Préstamos en línea previos',
      value: fd.online_loans_count != null ? String(fd.online_loans_count) : '—',
    },
    {
      stepId: 'bank_account',
      icon: 'card',
      label: 'Cuenta bancaria',
      value: bank?.bank_code
        ? `${bankName(bank.bank_code)} · ${maskClabe(bank.account_number)}`
        : '—',
    },
    {
      stepId: 'kyc_ine',
      icon: 'id',
      label: 'Identificación (INE)',
      value: ine?.front_image && ine?.back_image ? 'Frente y reverso cargados' : (ine?.front_image ? 'Solo frente cargado' : '—'),
    },
    {
      stepId: 'kyc_face',
      icon: 'face',
      label: 'Validación facial',
      value: selfie ? 'Selfie capturada' : '—',
    },
  ]
}

const sections = computed<Section[]>(() => {
  const fd = props.formData ?? {}
  if (isFullReview.value) {
    return [
      { title: 'Información personal', rows: personalRows(fd) },
      { title: 'Verificación y documentos', rows: verificationRows(fd) },
    ]
  }
  const s = props.step as ReviewStep
  if (s.sections?.includes('personal')) {
    return [{ title: 'Información personal', rows: personalRows(fd) }]
  }
  return []
})

function editRow(stepId: string) {
  router.push({ name: 'm-onboarding-step', params: { stepId } })
}
</script>

<template>
  <div class="step-review">
    <!-- Banner morado shield -->
    <div class="review-banner">
      <span class="banner-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M12 2.5c2.5 1.6 5 2.4 7.5 2.4v6.6c0 4.6-3.2 8.4-7.5 9.5-4.3-1.1-7.5-4.9-7.5-9.5V4.9c2.5 0 5-.8 7.5-2.4z" fill="white" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
          <path d="M8 12.2l2.6 2.6 5.2-5.2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </span>
      <p>Revisa tu información antes de continuar.</p>
    </div>

    <!-- Secciones con filas editables -->
    <div v-for="(section, sIdx) in sections" :key="sIdx" class="review-section">
      <h3 v-if="isFullReview" class="section-title">{{ section.title }}</h3>
      <ul class="review-list">
        <li v-for="(row, rIdx) in section.rows" :key="`${sIdx}-${rIdx}`" class="review-block">
          <span class="row-label">{{ row.label }}</span>
          <button type="button" class="dropdown-row" @click="editRow(row.stepId)">
            <span class="row-icon" aria-hidden="true">
              <svg v-if="row.icon === 'cap'" viewBox="0 0 24 24" fill="none">
                <path d="M3 9l9-4 9 4-9 4-9-4z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                <path d="M6 11v4c0 1 2.5 2.5 6 2.5s6-1.5 6-2.5v-4" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
              </svg>
              <svg v-else-if="row.icon === 'user'" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.6" />
                <path d="M4 21c0-4 4-6 8-6s8 2 8 6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
              </svg>
              <svg v-else-if="row.icon === 'users'" viewBox="0 0 24 24" fill="none">
                <circle cx="9" cy="9" r="3.2" stroke="currentColor" stroke-width="1.6" />
                <circle cx="17" cy="11" r="2.4" stroke="currentColor" stroke-width="1.6" />
                <path d="M3 19c.6-3 3-4.6 6-4.6s5.4 1.6 6 4.6M14 17c.5-1.6 2-2.6 4-2.6s3.5 1 4 2.6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
              </svg>
              <svg v-else-if="row.icon === 'pin'" viewBox="0 0 24 24" fill="none">
                <path d="M12 22s7-7 7-13a7 7 0 10-14 0c0 6 7 13 7 13z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                <circle cx="12" cy="9" r="2.5" stroke="currentColor" stroke-width="1.6" />
              </svg>
              <svg v-else-if="row.icon === 'briefcase'" viewBox="0 0 24 24" fill="none">
                <rect x="3" y="7" width="18" height="13" rx="2" stroke="currentColor" stroke-width="1.6" />
                <path d="M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
              </svg>
              <svg v-else-if="row.icon === 'wallet'" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6" />
                <path d="M12 7v10M9 9.5h4a2 2 0 010 4h-2a2 2 0 000 4h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
              </svg>
              <svg v-else-if="row.icon === 'card'" viewBox="0 0 24 24" fill="none">
                <rect x="3" y="6" width="18" height="13" rx="2" stroke="currentColor" stroke-width="1.6" />
                <path d="M3 11h18M7 16h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
              </svg>
              <svg v-else-if="row.icon === 'id'" viewBox="0 0 24 24" fill="none">
                <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.6" />
                <circle cx="9" cy="11" r="2.2" stroke="currentColor" stroke-width="1.6" />
                <path d="M6 16c.6-1.4 1.8-2.2 3-2.2s2.4.8 3 2.2M14 10h4M14 13h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
              </svg>
              <svg v-else-if="row.icon === 'face'" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6" />
                <circle cx="9.5" cy="11" r="1" fill="currentColor" />
                <circle cx="14.5" cy="11" r="1" fill="currentColor" />
                <path d="M9 15.5c.8.9 1.8 1.3 3 1.3s2.2-.4 3-1.3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
              </svg>
            </span>
            <span class="row-value">{{ row.value }}</span>
            <svg class="row-chevron" viewBox="0 0 24 24" fill="none">
              <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </button>
        </li>
      </ul>
    </div>
  </div>
</template>

<style scoped>
.step-review {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.review-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  background: rgb(var(--surface-soft-rgb, 243 242 250) / 1);
  border-radius: 14px;
  color: var(--tenant-primary, #5B21B6);
}
.banner-icon {
  width: 36px;
  height: 36px;
  flex-shrink: 0;
  border-radius: 999px;
  background: var(--tenant-primary, #5B21B6);
  color: var(--tenant-primary, #5B21B6);
  display: grid;
  place-items: center;
}
.banner-icon svg {
  width: 22px;
  height: 22px;
  color: white;
}
.review-banner p {
  margin: 0;
  font-size: 13.5px;
  font-weight: 500;
}

.review-section { display: flex; flex-direction: column; gap: 10px; }
.review-section + .review-section { margin-top: 6px; }
.section-title {
  margin: 4px 2px 0;
  font-size: 12.5px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--tenant-primary, #5B21B6);
}

.review-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.review-block {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.row-label {
  font-size: 12.5px;
  color: #475569;
  font-weight: 500;
}
.dropdown-row {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 14px;
  background: #ffffff;
  border: 1.5px solid #e5e7eb;
  border-radius: 14px;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
  min-height: 54px;
  transition: border-color 140ms ease;
}
.dropdown-row:active {
  border-color: var(--tenant-primary, #5B21B6);
}
.row-icon {
  width: 22px;
  height: 22px;
  color: var(--tenant-primary, #5B21B6);
  flex-shrink: 0;
}
.row-icon svg {
  width: 100%;
  height: 100%;
}
.row-value {
  flex: 1;
  text-align: left;
  font-size: 14.5px;
  color: #0f172a;
  font-weight: 600;
}
.row-chevron {
  width: 18px;
  height: 18px;
  color: #94a3b8;
  flex-shrink: 0;
}
</style>
