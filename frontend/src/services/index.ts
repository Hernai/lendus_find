// Export all services
export { default as api } from './api'
export { default as authService } from './auth.service'
export { default as applicantService } from './applicant.service'
export { default as applicationService } from './application.service'
export { default as simulatorService } from './simulator.service'
export { default as adminService } from './admin.service'

// Export types
export type { RequestOtpPayload, VerifyOtpPayload, AuthResponse, UserProfile } from './auth.service'
export type {
  ApplicantResponse,
  ApplicantUpdateResponse,
  PersonalDataPayload,
  AddressPayload,
  EmploymentPayload,
  BankAccountPayload,
} from './applicant.service'
export type {
  Application,
  ApplicationDocument,
  ApplicationReference,
  CreateApplicationPayload,
  CreateReferencePayload,
} from './application.service'
export type {
  Product,
  CalculatePayload,
  LoanCalculation,
  AmortizationRow,
  AmortizationPayload,
} from './simulator.service'
export type {
  DashboardData,
  AdminApplication,
  AdminApplicationDetail,
  ApplicationFilters,
  CounterOfferPayload,
} from './admin.service'
