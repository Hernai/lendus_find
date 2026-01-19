// =====================================================
// Core API
// =====================================================

export { default as api, api as apiInstance } from './api'

// =====================================================
// V2 Services (Primary Architecture)
// =====================================================

export { v2, v2 as default } from './v2'

// Re-export individual V2 services for granular imports
export {
  applicantAuth as v2ApplicantAuth,
  staffAuth as v2StaffAuth,
  applicantApplication as v2ApplicantApplication,
  staffApplication as v2StaffApplication,
  applicantDocument as v2ApplicantDocument,
  staffDocument as v2StaffDocument,
  person as v2Person,
  company as v2Company,
} from './v2'

// =====================================================
// V2 Types
// =====================================================

export type {
  // Common types
  V2ApiResponse,
  V2PaginatedResponse,

  // Auth types
  V2OtpRequestPayload,
  V2OtpVerifyPayload,
  V2CheckUserPayload,
  V2CheckUserResponse,
  V2PinLoginPayload,
  V2AuthResponse,
  V2ApplicantUser,
  V2StaffUser,

  // Person types
  V2Person,
  V2PersonCreatePayload,
  V2Identification,
  V2Address,
  V2Employment,
  V2Reference,
  V2BankAccount,

  // Application types
  V2Application,
  V2ApplicationStatus,
  V2ApplicationCreatePayload,
  V2ApplicationFilters,
  V2ApplicationStatistics,

  // Document types
  V2Document,
  V2DocumentType,

  // Company types
  V2Company,
  V2CompanyMember,
} from './v2'
