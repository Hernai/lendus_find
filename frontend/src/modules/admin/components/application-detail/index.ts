/**
 * Application detail sub-components.
 *
 * These components are extracted from AdminApplicationDetail.vue
 * to improve maintainability and reduce file size.
 */

export { default as ReferencesSection } from './ReferencesSection.vue'
export { default as BankAccountsSection } from './BankAccountsSection.vue'
export { default as NotesSection } from './NotesSection.vue'
// Feed unificado de actividad. Reemplaza a TimelineSection + AuditLogList +
// ApiLogsSection (eliminados). Consume /v2/staff/applications/{id}/activity.
export { default as ActivityTimeline } from './ActivityTimeline.vue'
export { default as VerifiableField } from './VerifiableField.vue'
export { default as RisksSection } from './RisksSection.vue'
export { default as LoanDetailsSection } from './LoanDetailsSection.vue'
export { default as LoanSummaryCards } from './LoanSummaryCards.vue'
export { default as CompletenessCard } from './CompletenessCard.vue'
export { default as SignatureSection } from './SignatureSection.vue'
export { default as ApplicationNotFoundState } from './ApplicationNotFoundState.vue'
export { default as AddressSection } from './AddressSection.vue'
export { default as EmploymentSection } from './EmploymentSection.vue'
export { default as LeaseInfoSection } from './LeaseInfoSection.vue'
export { default as TabsBar } from './TabsBar.vue'
export { default as ApplicantDataSection } from './ApplicantDataSection.vue'
export { default as IneVerificationModal } from './IneVerificationModal.vue'
export { default as StatusChangeModal } from './StatusChangeModal.vue'
export { default as AssignAnalystModal } from './AssignAnalystModal.vue'
export { default as ReferenceVerifyModal } from './ReferenceVerifyModal.vue'
export { default as CounterOfferModal } from './CounterOfferModal.vue'
export { default as ApplicationDetailHeader } from './ApplicationDetailHeader.vue'
export { default as SelfieViewerModal } from './SelfieViewerModal.vue'
