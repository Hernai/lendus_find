import { defineAsyncComponent } from 'vue'
import { registerTenantSteps } from '@/components/onboarding/stepRegistry'

/**
 * Pasos custom del tenant `demo` para el flujo de ARRENDAMIENTO (puro y
 * financiero). El core no conoce tenants: estos renderers se inyectan aquí y
 * `tenants/registerAll.ts` importa este archivo con glob eager al boot.
 *
 * Los tipos se referencian desde `products.onboarding_steps` de los productos
 * ARRE-* del seeder demo. Async para code-splitting.
 */
registerTenantSteps('demo', {
  applicant_type_select: {
    default: defineAsyncComponent(() => import('./onboarding/ApplicantTypeSelectStep.vue')),
  },
  asset_type: {
    default: defineAsyncComponent(() => import('./onboarding/AssetTypeStep.vue')),
  },
  company_data: {
    default: defineAsyncComponent(() => import('./onboarding/CompanyDataStep.vue')),
  },
  company_docs: {
    default: defineAsyncComponent(() => import('./onboarding/CompanyDocsStep.vue')),
  },
  // Override del paso base `references`: variante demo con nombre/apellidos
  // separados. MoneyCapital sigue usando el ReferencesStepRenderer base.
  references: {
    default: defineAsyncComponent(() => import('./onboarding/ReferencesStep.vue')),
  },
  // Paso de documentos: sube los required_documents del producto (comprobante de
  // domicilio, ingresos…) que no captura el KYC.
  documents: {
    default: defineAsyncComponent(() => import('./onboarding/DocumentsStep.vue')),
  },
})
