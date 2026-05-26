<script setup lang="ts">
import { computed } from 'vue'
import { useTenantStore } from '@/stores/tenant'
import type { ReviewStep } from '@/types/v2/onboardingStep'

const props = defineProps<{
  step: ReviewStep
  /** Datos acumulados del onboarding hasta aquí. */
  formData: Record<string, unknown>
}>()

defineEmits<{
  (e: 'update:modelValue', v: true): void
}>()

const tenantStore = useTenantStore()

interface Row { label: string; value: string }

function labelFor(enumName: string, value: unknown): string {
  if (value == null || value === '') return '—'
  const key = enumName.charAt(0).toLowerCase() + enumName.slice(1)
  const list = (tenantStore.options as Record<string, Array<{ value: string; label: string }>>)[key] ?? []
  return list.find((o) => String(o.value) === String(value))?.label ?? String(value)
}

const rows = computed<Row[]>(() => {
  const fd = props.formData ?? {}
  const out: Row[] = []
  if (props.step.sections?.includes('personal')) {
    if (fd.education_level !== undefined)
      out.push({ label: 'Nivel educativo', value: labelFor('EducationLevel', fd.education_level) })
    if (fd.marital_status !== undefined)
      out.push({ label: 'Estado civil', value: labelFor('MaritalStatus', fd.marital_status) })
    if (fd.state || fd.city)
      out.push({
        label: 'Estado y ciudad',
        value: [labelFor('MexicanState', fd.state), fd.city].filter(Boolean).join(', '),
      })
    if (fd.employment_type !== undefined)
      out.push({ label: 'Tipo de actividad', value: labelFor('EmploymentType', fd.employment_type) })
    if (fd.salary_range !== undefined)
      out.push({ label: 'Rango salarial', value: labelFor('SalaryRange', fd.salary_range) })
  }
  return out
})
</script>

<template>
  <div class="step-review">
    <div class="review-header">
      <span class="review-icon">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      </span>
      <p>Revisa tu información antes de continuar.</p>
    </div>

    <ul v-if="rows.length" class="review-list">
      <li v-for="row in rows" :key="row.label">
        <span class="row-label">{{ row.label }}</span>
        <span class="row-value">{{ row.value }}</span>
      </li>
    </ul>
    <p v-else class="empty">No hay datos para revisar todavía.</p>
  </div>
</template>

<style scoped>
.step-review { display: flex; flex-direction: column; gap: 16px; }
.review-header {
  display: flex; align-items: center; gap: 10px;
  background: color-mix(in srgb, var(--tenant-primary, #5B21B6) 6%, #fff);
  padding: 12px; border-radius: 12px;
  color: var(--tenant-primary, #5B21B6);
  font-size: 14px; font-weight: 500;
}
.review-icon { display: grid; place-items: center; }
.review-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; }
.review-list li { display: flex; justify-content: space-between; padding: 10px 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; }
.row-label { font-size: 13px; color: #64748b; }
.row-value { font-size: 14px; color: #0f172a; font-weight: 600; text-align: right; }
.empty { color: #94a3b8; font-size: 13px; text-align: center; padding: 20px; }
</style>
