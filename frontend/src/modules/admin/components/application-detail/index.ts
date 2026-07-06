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
export { default as DocumentViewerModal } from './DocumentViewerModal.vue'
export { default as AddressSection } from './AddressSection.vue'
export { default as EmploymentSection } from './EmploymentSection.vue'
export { default as TabsBar } from './TabsBar.vue'
