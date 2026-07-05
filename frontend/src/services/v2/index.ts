/**
 * V2 API Services Index
 *
 * Central export point for all V2 API services.
 * Import from this file to access V2 services:
 *
 * @example
 * import { v2 } from '@/services/v2'
 *
 * // Applicant auth
 * await v2.applicant.auth.requestOtp({ target_type: 'phone', target_value: '5512345678' })
 *
 * // Los servicios de staff/admin viven en @/modules/admin/services (facade `staff`),
 * // NO en este barrel: el layer compartido no depende del módulo admin.
 * // import { staff } from '@/modules/admin/services'
 */

// =====================================================
// Service Imports
// =====================================================

import applicantAuth from './auth.applicant.service'
import applicantApplication from './application.applicant.service'
import applicantCorrection from './correction.applicant.service'
import applicantDocument from './document.applicant.service'
import applicantProfile from './profile.service'
import applicantKyc from './kyc.applicant.service'
import applicantNotification from './notification.applicant.service'
import simulator from './simulator.service'
import publicConfig from './config.public.service'
import applicantLoan from './loan.applicant.service'

// =====================================================
// Organized V2 API Namespace
// =====================================================

/**
 * V2 API Services
 *
 * Organized by domain and role for clear separation:
 * - applicant: Services for applicant-facing operations
 * - staff: Services for staff/admin operations
 */
export const v2 = {
  /**
   * Applicant-facing services
   */
  applicant: {
    auth: applicantAuth,
    application: applicantApplication,
    correction: applicantCorrection,
    document: applicantDocument,
    profile: applicantProfile,
    kyc: applicantKyc,
    notification: applicantNotification,
    loan: applicantLoan,
  },

  /**
   * Public simulator services
   */
  simulator,

  /**
   * Public config service
   */
  config: publicConfig,
}

// =====================================================
// Individual Service Exports (for tree-shaking)
// =====================================================

export {
  applicantAuth,
  applicantApplication,
  applicantCorrection,
  applicantDocument,
  applicantProfile,
  applicantKyc,
  applicantNotification,
  applicantLoan,
  simulator,
  publicConfig,
}

// =====================================================
// Type Re-exports
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
  V2PinSetupPayload,
  V2PinChangePayload,
  V2AuthResponse,
  V2ApplicantUser,
  V2StaffUser,
  V2StaffLoginPayload,

  // Person types
  V2Person,
  V2PersonCreatePayload,
  V2PersonUpdatePayload,
  V2Identification,
  V2IdentificationPayload,
  V2IdentificationType,
  V2Address,
  V2AddressPayload,
  V2AddressType,
  V2Employment,
  V2EmploymentPayload,
  V2EmploymentType,
  V2ContractType,
  V2PaymentFrequency,
  V2Reference,
  V2ReferencePayload,
  V2ReferenceType,
  V2Relationship,
  V2BankAccount,
  V2BankAccountPayload,
  V2BankAccountType,
  V2ClabeValidationResult,

  // Application types
  V2Application,
  V2ApplicationStatus,
  V2ApplicationCreatePayload,
  V2ApplicationUpdatePayload,
  V2CounterOffer,
  V2CounterOfferResponsePayload,
  V2ApplicationFilters,
  V2AssignApplicationPayload,
  V2ChangeStatusPayload,
  V2ApprovePayload,
  V2RejectPayload,
  V2CounterOfferCreatePayload,
  V2RiskAssessmentPayload,
  V2ApplicationStatistics,
  V2ApplicationNote,
  V2ApplicationNotePayload,

  // Product types
  V2Product,
  V2ProductType,

  // Document types
  V2Document,
  V2DocumentStatus,
  V2DocumentCategory,
  V2DocumentType,
  V2DocumentUploadPayload,

  // Company types
  V2Company,
  V2CompanyStatus,
  V2CompanyCreatePayload,
  V2CompanyMember,
  V2CompanyMemberPayload,
  V2MemberRole,
  V2MemberStatus,

  // Profile types
  V2Profile,
  V2ProfileSummary,
  V2PersonalData,
  V2Identifications,
  V2ProfileAddress,
  V2ProfileEmployment,
  V2ProfileBankAccount,
  V2ProfileReference,
  V2ClabeValidation,
} from '@/types/v2'

// Re-export types from services
// V2ApiLogEntry removido junto con application.staff.service.getApiLogs.
// La info de api-logs por aplicacion viaja ahora en el feed unificado:
// v2.staff.activity.getActivity(appId, { kind: 'api' })

// Default export for convenience
export default v2
