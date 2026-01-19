# V2 API Pending Features

This document tracks features that need to be added to the V2 API based on frontend requirements.

## AdminApplicationDetail.vue

### 1. Signature Support
- **Location**: Line 468
- **Field**: `signature: boolean`
- **Description**: Need to add signature field support to V2 Application model
- **Priority**: Medium

### 2. Application Purpose
- **Location**: Line 543
- **Field**: `purpose: string`
- **Description**: Purpose/reason for the loan application
- **Priority**: Low

### 3. KYC Lock Flag
- **Location**: Line 556
- **Field**: `is_kyc_locked: boolean`
- **Description**: Flag to indicate if KYC data is locked (verified and cannot be changed)
- **Priority**: High

### 4. Bank Account Ownership
- **Location**: Line 582
- **Field**: `is_own_account: boolean`
- **Description**: Flag on bank accounts to indicate if account belongs to applicant
- **Priority**: Medium

### 5. Field Verifications
- **Location**: Line 615
- **Field**: `field_verifications: Record<string, FieldVerification>`
- **Description**: Support for per-field verification status and metadata
- **Priority**: High

## Action Items

1. [ ] Add `signature` column to applications table
2. [ ] Add `purpose` column to applications table
3. [ ] Add `is_kyc_locked` column to applications table
4. [ ] Add `is_own_account` column to bank_accounts table
5. [ ] Create field_verifications table and relationship
6. [ ] Update V2 API response transformers
7. [ ] Update TypeScript types in frontend

## Related Types

```typescript
interface V2Application {
  // ... existing fields
  signature?: boolean
  purpose?: string
  is_kyc_locked?: boolean
  field_verifications?: Record<string, FieldVerification>
}

interface V2BankAccount {
  // ... existing fields
  is_own_account?: boolean
}

interface FieldVerification {
  field: string
  value: unknown
  method: string
  verified: boolean
  verified_at?: string
  metadata?: Record<string, unknown>
}
```
