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
 * // Staff auth
 * await v2.staff.auth.login({ email: 'admin@example.com', password: 'secret' })
 *
 * // Person management
 * await v2.person.create({ first_name: 'Juan', last_name_1: 'Perez' })
 * await v2.person.addresses.create(personId, { type: 'HOME', ... })
 *
 * // Company management
 * await v2.company.create({ legal_name: 'ACME SA de CV', rfc: 'ABC123456789' })
 */

// =====================================================
// Service Imports
// =====================================================

import applicantAuth from './auth.applicant.service'
import staffAuth from './auth.staff.service'
import applicantApplication from './application.applicant.service'
import staffApplication from './application.staff.service'
import applicantDocument from './document.applicant.service'
import staffDocument from './document.staff.service'
import applicantProfile from './profile.service'
import staffUser from './user.staff.service'
import staffProduct from './product.staff.service'
import staffConfig from './config.staff.service'
import staffApiLog from './apilog.staff.service'
import staffTenant from './tenant.staff.service'
import staffIntegration from './integration.staff.service'
import person from './person.service'
import company from './company.service'

// =====================================================
// Organized V2 API Namespace
// =====================================================

/**
 * V2 API Services
 *
 * Organized by domain and role for clear separation:
 * - applicant: Services for applicant-facing operations
 * - staff: Services for staff/admin operations
 * - person: Person entity management (identifications, addresses, etc.)
 * - company: Company entity management (for Persona Moral)
 */
export const v2 = {
  /**
   * Applicant-facing services
   */
  applicant: {
    auth: applicantAuth,
    application: applicantApplication,
    document: applicantDocument,
    profile: applicantProfile,
  },

  /**
   * Staff/Admin services
   */
  staff: {
    auth: staffAuth,
    application: staffApplication,
    document: staffDocument,
    user: staffUser,
    product: staffProduct,
    config: staffConfig,
    apiLog: staffApiLog,
    tenant: staffTenant,
    integration: staffIntegration,
  },

  /**
   * Person entity services (with nested resources)
   */
  person,

  /**
   * Company entity services (for Persona Moral)
   */
  company,
}

// =====================================================
// Individual Service Exports (for tree-shaking)
// =====================================================

export {
  applicantAuth,
  staffAuth,
  applicantApplication,
  staffApplication,
  applicantDocument,
  staffDocument,
  applicantProfile,
  staffUser,
  staffProduct,
  staffConfig,
  staffApiLog,
  staffTenant,
  staffIntegration,
  person,
  company,
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
  V2StatusHistoryEntry,
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
export type { V2ApiLogEntry } from './application.staff.service'

// Default export for convenience
export default v2
