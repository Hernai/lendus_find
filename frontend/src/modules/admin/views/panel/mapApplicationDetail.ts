/**
 * Mapeo puro de la respuesta del detalle (V2ApplicationDetail) al modelo de vista
 * `Application` que consume AdminApplicationDetail.vue.
 *
 * Extraído del componente (antes ~250 líneas inline dentro de `fetchApplication`)
 * para: (1) reducir el tamaño del "god-component", (2) hacer la transformación
 * testeable de forma aislada, y (3) dejar `fetchApplication` enfocado en I/O y
 * estado. Es determinista y sin efectos secundarios sobre estado del componente;
 * la única dependencia de runtime (etiqueta del tipo de documento, que vive en el
 * composable `useDocumentTypes`) se inyecta vía `deps`.
 */

import type { V2ApplicationDetail } from '@/types/v2'
import type { Application } from './applicationDetail.types'

// Nacionalidad mexicana: distintos flujos guardan el valor de formas diferentes
// (MX / MEX / MEXICO / MEXICANA…). Normalizamos para no marcar como extranjero a
// un mexicano. Sin dato => NO asumimos extranjero.
const MEXICAN_NATIONALITY = new Set([
  'MX', 'MEX', 'MEXICO', 'MÉXICO', 'MEXICANA', 'MEXICANO', 'MEXICAN', 'MXN',
])

export function isForeignNationality(nat?: string | null): boolean {
  const v = (nat ?? '').trim().toUpperCase()
  if (v === '') return false
  return !MEXICAN_NATIONALITY.has(v)
}

// Normaliza required_documents a una lista plana de TIPOS (string[]).
// Acepta: array plano legacy (string[] u objetos {type}) o el formato nuevo
// { nationals: [...], foreigners: [...] } cuyas entradas son objetos
// { type, required, description }. Sin esto, comparar `Set(objetos).has(string)`
// daba siempre falso (de ahí "0/3 aprobados" con 3 docs aprobados).
export function requiredDocTypesFor(raw: unknown, foreigner: boolean): string[] {
  let list: unknown[] = []
  if (Array.isArray(raw)) {
    list = raw
  } else if (raw && typeof raw === 'object') {
    const o = raw as { nationals?: unknown[]; foreigners?: unknown[] }
    list = (foreigner ? o.foreigners : o.nationals) ?? []
  }
  return list
    .map((d) => (typeof d === 'string' ? d : (d as { type?: string })?.type))
    .filter((t): t is string => typeof t === 'string' && t.length > 0)
}

/** Dependencias de runtime inyectadas (viven en composables del componente). */
export interface MapApplicationDeps {
  /** Etiqueta legible del tipo de documento (de useDocumentTypes). */
  getDocumentTypeLabel: (type: string) => string
}

/**
 * Transforma la respuesta cruda del detalle en el modelo de vista `Application`.
 * Movimiento verbatim del cuerpo que antes vivía en `fetchApplication`.
 */
export function mapApplicationDetail(
  data: V2ApplicationDetail,
  deps: MapApplicationDeps,
): Application {
  const getDocTypeName = deps.getDocumentTypeLabel

  // Extract data from new structured response
  const applicantData = data.applicant
  const person = applicantData?.person
  const company = applicantData?.company
  const loan = data.loan
  const verification = data.verification
  const workflow = data.workflow
  const docs = data.documents || []

  // Get person sub-entities
  const personAddress = person?.address
  const personEmployment = person?.employment
  const personReferences = person?.references || []
  const personBankAccounts = person?.bank_accounts || []

  // Calculate approved documents that are in the required list.
  // El backend devuelve string[] (legacy) o { nationals: string[]; foreigners: string[] }
  // — el type V2 está pegado al legacy, así que reinterpretamos a runtime.
  const requiredDocTypesRaw = (data.required_documents || []) as unknown as
    | string[]
    | { nationals: string[]; foreigners: string[] }
  const isForeigner = isForeignNationality(person?.personal_data?.nationality)
  const requiredDocsArray = requiredDocTypesFor(requiredDocTypesRaw, isForeigner)
  const requiredTypes = new Set(requiredDocsArray)
  const approvedRequiredCount = docs.filter(d =>
    d.status === 'APPROVED' && requiredTypes.has(d.type)
  ).length

  return {
    id: data.id,
    folio: data.folio || '',
    status: data.status,
    created_at: data.created_at,
    updated_at: data.updated_at,
    assigned_to: workflow?.assigned_to?.name ?? undefined,
    online_loans_count: data.online_loans_count ?? null,
    lease_info: (data as unknown as { lease_info?: Application['lease_info'] }).lease_info ?? null,
    required_documents: requiredDocTypesRaw,
    completeness: {
      personal_data: !!person,
      address: !!personAddress,
      employment: !!personEmployment,
      documents: {
        uploaded: docs.length,
        required: requiredDocsArray.length,
        approved: approvedRequiredCount
      },
      references: {
        count: personReferences.length,
        verified: personReferences.filter(r => r.verification_status === 'VERIFIED').length
      },
      signature: verification?.signature?.has_signed ?? false
    },
    applicant: person ? {
      id: person.id,
      full_name: person.personal_data.full_name,
      first_name: person.personal_data.first_name,
      last_name_1: person.personal_data.last_name_1,
      last_name_2: person.personal_data.last_name_2 || '',
      email: person.contact.email || '',
      phone: person.contact.phone || '',
      curp: person.identifications.curp || '',
      rfc: person.identifications.rfc || '',
      ine_clave: person.identifications.ine_clave || '',
      ine_ocr: person.identifications.ine_ocr || '',
      ine_folio: person.identifications.ine_folio || '',
      ine_verification: (person as { ine_verification?: {
        ocr?: { nombres?: string; apellido_paterno?: string; apellido_materno?: string; curp?: string } | null
        renapo?: { nombres?: string; apellido_paterno?: string; apellido_materno?: string } | null
        ine_valid?: boolean | null
        curp_valid?: boolean | null
        verified_at?: string | null
      } | null }).ine_verification ?? null,
      birth_date: person.personal_data.birth_date || '',
      birth_state: person.personal_data.birth_state || '',
      nationality: person.personal_data.nationality || '',
      nationality_info: person.personal_data.nationality_info,
      gender: person.personal_data.gender || '',
      passport_number: person.identifications.passport_number || '',
      passport_issue_date: person.identifications.passport_issue_date || '',
      passport_expiry_date: person.identifications.passport_expiry_date || '',
      marital_status: person.personal_data.marital_status || '',
      marital_status_label: person.personal_data.marital_status_label || '',
      education_level: person.personal_data.education_level || '',
      education_level_label: person.personal_data.education_level_label || ''
    } : company ? {
      id: company.id,
      full_name: company.legal_name,
      first_name: company.legal_name,
      last_name_1: '',
      last_name_2: '',
      email: company.contact.email || '',
      phone: company.contact.phone || '',
      curp: '',
      rfc: company.rfc || '',
      ine_clave: '',
      birth_date: '',
      nationality: '',
      nationality_info: undefined,
      gender: '',
      passport_number: '',
      passport_issue_date: '',
      passport_expiry_date: ''
    } : {
      id: '',
      full_name: '',
      first_name: '',
      last_name_1: '',
      last_name_2: '',
      email: '',
      phone: '',
      curp: '',
      rfc: '',
      ine_clave: '',
      birth_date: '',
      nationality: '',
      nationality_info: undefined,
      gender: '',
      passport_number: '',
      passport_issue_date: '',
      passport_expiry_date: ''
    },
    address: personAddress ? {
      street: personAddress.street,
      ext_number: personAddress.exterior_number,
      int_number: personAddress.interior_number || undefined,
      neighborhood: personAddress.neighborhood,
      city: personAddress.city || undefined,
      postal_code: personAddress.postal_code,
      municipality: personAddress.municipality,
      state: personAddress.state,
      housing_type: personAddress.housing_type || '',
      years_at_address: personAddress.years_at_address || undefined,
      months_at_address: personAddress.months_at_address || undefined
    } : {
      street: '',
      ext_number: '',
      neighborhood: '',
      postal_code: '',
      municipality: '',
      state: '',
      housing_type: '',
      years_at_address: 0,
      months_at_address: 0
    },
    employment: personEmployment ? {
      employment_type: personEmployment.employment_type,
      company_name: personEmployment.employer_name || '',
      position: personEmployment.job_title || '',
      monthly_income: personEmployment.monthly_income || 0,
      income_range_label: personEmployment.income_range_label || '',
      seniority_months: personEmployment.start_date
        ? Math.floor((Date.now() - new Date(personEmployment.start_date).getTime()) / (1000 * 60 * 60 * 24 * 30))
        : (personEmployment.years_employed || 0) * 12 + (personEmployment.months_employed || 0)
    } : {
      employment_type: '',
      company_name: '',
      position: '',
      monthly_income: 0,
      seniority_months: 0
    },
    loan: {
      product_name: loan?.product_name || '',
      requested_amount: loan?.requested_amount || 0,
      approved_amount: loan?.approved_amount || undefined,
      term_months: loan?.requested_term_months || 12,
      payment_frequency: 'MENSUAL', // Default, could be added to loan structure
      interest_rate: loan?.interest_rate || 0,
      monthly_payment: loan?.monthly_payment || 0,
      total_to_pay: loan?.total_amount || 0,
      purpose: loan?.purpose || '',
      purpose_label: loan?.purpose_label || undefined,
      // Plazo en días + contraoferta (modal adaptivo y tarjeta de oferta vigente)
      term_in_days: loan?.term_in_days ?? false,
      requested_term_days: loan?.requested_term_days ?? undefined,
      requested_term_months: loan?.requested_term_months ?? undefined,
      approved_term_days: loan?.approved_term_days ?? null,
      has_counter_offer: loan?.has_counter_offer ?? false,
      counter_offer: loan?.counter_offer ?? null,
      counter_offer_accepted: loan?.counter_offer_accepted ?? null,
      product_limits: loan?.product_limits ?? null,
      engine_decision: loan?.engine_decision ?? null
    },
    documents: docs.map(d => ({
      id: d.id,
      type: d.type,
      name: getDocTypeName(d.type),
      status: d.status as 'PENDING' | 'APPROVED' | 'REJECTED',
      rejection_reason: d.rejection_reason || undefined,
      rejection_comment: undefined,
      uploaded_at: d.created_at || undefined,
      reviewed_at: d.reviewed_at || undefined,
      mime_type: d.mime_type,
      metadata: d.ocr_data ?? undefined,
      is_kyc_locked: d.is_kyc_locked ?? false
    })),
    references: personReferences.map(r => ({
      id: r.id,
      full_name: r.full_name,
      relationship: r.relationship,
      phone: r.phone,
      verified: r.verification_status === 'VERIFIED',
      verification_result: r.verification_status === 'VERIFIED' ? 'VERIFIED' as const
        : r.verification_status === 'REJECTED' ? 'NOT_VERIFIED' as const
        : r.verification_status === 'UNREACHABLE' ? 'NO_ANSWER' as const
        : undefined,
      verification_notes: r.verification_notes || undefined,
      verified_at: r.verified_at || undefined
    })),
    bank_accounts: personBankAccounts.map(ba => ({
      id: ba.id,
      type: ba.account_type || '',
      bank_name: ba.bank_name,
      bank_code: ba.bank_code || '',
      clabe: ba.clabe,
      account_type: ba.account_type || '',
      account_type_label: ba.account_type || '',
      holder_name: ba.holder_name || '',
      holder_rfc: ba.holder_rfc || undefined,
      is_primary: ba.is_primary,
      is_own_account: ba.is_own_account ?? true,
      is_verified: ba.is_verified,
      // Método/estado de verificación: sin esto el detalle no distinguía
      // "automática (Nubarium)" de "manual" ni mostraba "Ver respuesta".
      verified_at: ba.verified_at || undefined,
      verification_method: ba.verification_method ?? null,
      verified_by_nubarium: ba.verified_by_nubarium ?? false,
      clabe_validation: ba.clabe_validation ?? null,
      created_at: ba.created_at || undefined
    })),
    notes: (workflow?.notes || []).map(n => ({
      id: n.id,
      text: n.content,
      author: n.author?.name || 'Sistema',
      created_at: n.created_at
    })),
    // timeline removido: el feed unificado lo consulta directo desde
    // /v2/staff/applications/{id}/activity al abrir el tab Actividad.
    timeline: [],
    signature: {
      has_signed: verification?.signature?.has_signed ?? false,
      signature_base64: verification?.signature?.signature_base64 ?? undefined,
      signature_date: verification?.signature?.signature_date ?? undefined,
      signature_ip: verification?.signature?.signature_ip ?? undefined
    },
    verification: {
      phone_verified: false,
      phone_verified_at: undefined,
      email_verified: false,
      email_verified_at: undefined,
      identity_verified: verification?.kyc_status === 'VERIFIED',
      identity_verified_at: verification?.kyc_verified_at || undefined,
      address_verified: personAddress?.verification_status === 'VERIFIED',
      employment_verified: personEmployment?.verification_status === 'VERIFIED'
    },
    field_verifications: (() => {
      const fields = verification?.fields || {}
      // Map ine_document_front verification to ine_clave for display
      if (fields.ine_document_front && !fields.ine_clave) {
        fields.ine_clave = fields.ine_document_front
      }
      return fields
    })()
  }
}
