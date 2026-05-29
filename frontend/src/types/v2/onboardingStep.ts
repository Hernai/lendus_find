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

export interface OnboardingStepBase {
  id: string
  type: OnboardingStepType
  label?: string
  required?: boolean
}

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
