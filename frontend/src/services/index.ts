// =====================================================
// Core API
// =====================================================

export { default as api, api as apiInstance } from './api'

// =====================================================
// V2 Services (Primary Architecture)
// =====================================================

export { v2, v2 as default } from './v2'

// Re-export individual V2 services for granular imports.
// Los servicios staff viven en @/modules/admin/services (facade `staff`).
export {
  applicantAuth as v2ApplicantAuth,
  applicantApplication as v2ApplicantApplication,
  applicantDocument as v2ApplicantDocument,
} from './v2'

// =====================================================
// KYC Service
// =====================================================

export * as kycService from './kyc.service'
export {
  recordVerifications,
  recordSingleVerification,
  loadVerifications,
} from './kyc.service'
export type {
  KycServicesResponse,
  IneOcrData,
  IneValidationResponse,
  CurpValidationResponse,
  RfcValidationResponse,
  BiometricTokenResponse,
  FaceMatchResponse,
  LivenessResponse,
  ComplianceCheckResponse,
  VerifiedField as KycVerifiedField,
  LoadVerificationsResponse,
} from './kyc.service'

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
