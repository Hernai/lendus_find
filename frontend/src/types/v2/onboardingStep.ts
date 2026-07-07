/**
 * Tipos de un step declarativo del onboarding (definido en
 * `Product.onboarding_steps` del backend).
 *
 * Cada step se renderiza por `OnboardingStepRenderer.vue` que despacha
 * al componente `steps/<Type>StepRenderer.vue` según el campo `type`.
 */

export type OnboardingStepType =
  | 'select'
  | 'state_city'
  | 'number_select'
  | 'references'
  | 'bank_account'
  | 'kyc_ine'
  | 'kyc_selfie'
  | 'review'
  | 'review_full'
  | 'personal_data'
  | 'address'
  // Pasos custom del flujo de arrendamiento (registrados por el tenant demo).
  | 'applicant_type_select'
  | 'asset_type'
  | 'company_data'
  | 'company_docs'

// Condiciones que filtran un paso según integraciones del tenant o la rama del flujo.
export type OnboardingStepCondition =
  | 'if_kyc_provider' | 'unless_kyc_provider' | 'if_company' | 'if_individual'

export interface OnboardingStepBase {
  id: string
  type: OnboardingStepType
  // El backend guarda los steps como JSONB en `products.onboarding_steps`;
  // `label` no es obligatorio en el esquema, así que lo mantenemos opcional.
  // Los renderers deben defaultear a string vacío cuando lo lean.
  label?: string
  required?: boolean
  // Filtra el paso según integraciones activas del tenant o la rama del flujo.
  // if_company / if_individual: ramificación persona moral / física (arrendamiento).
  // Puede ser un array (AND): ['if_individual','unless_kyc_provider'] = persona
  // física Y sin proveedor KYC (datos personales a mano en el demo).
  condition?: OnboardingStepCondition | OnboardingStepCondition[]
  // Elige un DISEÑO alternativo del mismo tipo de paso en el registry (opcional).
  // El registry resuelve type[/variant]; sin variant usa el diseño default.
  variant?: string
  // Config libre para pasos custom (p.ej. { min: 3000 }). Los pasos base la ignoran.
  config?: Record<string, unknown>
}

/**
 * CONTRATO DE RENDERER (todo `steps/*StepRenderer.vue` lo cumple):
 *   props:  { step, modelValue, formData? }
 *   emits:  'update:modelValue'  — el valor capturado por el paso
 *           'update:valid'       — booleano: ¿este paso satisface SU propia validación?
 *
 * `update:valid` refleja SOLO la validez intrínseca del renderer; la combinación con
 * `step.required` la hace el runner (DynamicOnboardingView). Emitir con
 * `watch(isValid, ..., { immediate: true })` para reportar el estado inicial.
 */

export interface SelectStep extends OnboardingStepBase {
  type: 'select'
  field: string
  enum: string                  // nombre del enum en tenantStore.options
  required: true
}

export interface StateCityStep extends OnboardingStepBase {
  type: 'state_city'
  fields: ['state', 'city']
  required: true
}

export interface NumberSelectStep extends OnboardingStepBase {
  type: 'number_select'
  field: string
  options: number[]
  required: true
}

export interface ReferencesStep extends OnboardingStepBase {
  type: 'references'
  min: number
  max: number
}

export interface BankAccountStep extends OnboardingStepBase {
  type: 'bank_account'
}

export interface KycIneStep extends OnboardingStepBase {
  type: 'kyc_ine'
}

export interface KycSelfieStep extends OnboardingStepBase {
  type: 'kyc_selfie'
}

export interface ReviewStep extends OnboardingStepBase {
  type: 'review'
  sections: string[]
}

export interface ReviewFullStep extends OnboardingStepBase {
  type: 'review_full'
}

export interface PersonalDataStep extends OnboardingStepBase {
  type: 'personal_data'
}

export interface AddressStep extends OnboardingStepBase {
  type: 'address'
}

// --- Pasos custom del flujo de arrendamiento (tenant demo) ---
export interface ApplicantTypeSelectStep extends OnboardingStepBase {
  type: 'applicant_type_select'
}

export interface AssetTypeStep extends OnboardingStepBase {
  type: 'asset_type'
}

export interface CompanyDataStep extends OnboardingStepBase {
  type: 'company_data'
}

export interface CompanyDocsStep extends OnboardingStepBase {
  type: 'company_docs'
}

export type OnboardingStep =
  | SelectStep
  | StateCityStep
  | NumberSelectStep
  | ReferencesStep
  | BankAccountStep
  | KycIneStep
  | KycSelfieStep
  | ReviewStep
  | ReviewFullStep
  | PersonalDataStep
  | AddressStep
  | ApplicantTypeSelectStep
  | AssetTypeStep
  | CompanyDataStep
  | CompanyDocsStep
