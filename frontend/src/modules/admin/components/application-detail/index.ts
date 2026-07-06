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
