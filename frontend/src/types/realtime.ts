export interface ApplicationStatusChangedEvent {
  application_id: string
  folio: string
  applicant_id: string
  previous_status: string
  new_status: string
  reason?: string
  changed_by?: {
    id: string
    name: string
  }
  changed_at: string
}

export interface DocumentStatusChangedEvent {
  document_id: string
  application_id: string
  type: string
  previous_status: string
  new_status: string
  reason?: string
  reviewed_by?: {
    id: string
    name: string
  }
  reviewed_at: string
}

export interface ReferenceVerifiedEvent {
  reference_id: string
  application_id: string
  full_name: string
  result: string
  is_verified: boolean
  notes?: string
  verified_by?: {
    id: string
    name: string
  }
  verified_at: string
}

export interface ApplicationAssignedEvent {
  application_id: string
  folio: string
  assigned_to: {
    id: string
    name: string
  }
  assigned_by?: {
    id: string
    name: string
  }
  assigned_at: string
}
