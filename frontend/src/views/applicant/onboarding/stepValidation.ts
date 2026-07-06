import type { OnboardingStep } from '@/types/v2/onboardingStep'

/**
 * Contexto externo que la validación necesita (inyectado para que la función sea
 * PURA y testeable). En el runner viene de los stores; en el test se pasa directo.
 */
export interface StepValidationContext {
  /** ¿El tenant tiene proveedor KYC activo? (con él, el OCR de INE lo hace Nubarium). */
  hasKycProvider: boolean
  /** Teléfono del propio solicitante, YA en solo-dígitos, para bloquear auto-referencia. */
  ownPhone: string
}

/**
 * Validación por tipo de paso — spec canónica del gate "Continuar" del onboarding.
 *
 * Los 11 tipos base ya son dueños de su validación en el renderer (contrato
 * `update:valid`), reproduciendo EXACTAMENTE la validez de aquí. Esta función queda
 * como (a) FALLBACK del runner para tipos sin contrato (p.ej. un paso custom de
 * tenant que no emita update:valid → cae a la rama default: un valor no vacío), y
 * (b) spec ejecutable de las reglas de validación, congelada por stepValidation.spec.ts.
 *
 * Es PURA: no toca stores ni refs (el contexto se inyecta). Si cambias una regla aquí,
 * actualiza el `isValid` del renderer correspondiente para que sigan en paridad.
 */
export function legacyCanContinue(
  s: OnboardingStep,
  v: unknown,
  ctx: StepValidationContext,
): boolean {
  if (s.type === 'review' || s.type === 'review_full') return true
  if (s.type === 'state_city') {
    const sc = v as { state: string; city: string } | null
    return !!sc && !!sc.state && !!sc.city
  }
  if (s.type === 'references') {
    const refs = v as Array<{ name: string; phone: string }> | null
    if (!refs || refs.length < 2) return false
    const eachValid = refs.every((r) => {
      const parts = (r.name || '').trim().split(/\s+/).filter(Boolean)
      const nameOk = parts.length >= 2 && parts.every((p) => p.length >= 2)
      return nameOk && r.phone.replace(/\D/g, '').length === 10
    })
    if (!eachValid) return false
    // Referencias distintas entre sí y distintas del propio cliente.
    const phones = refs.map((r) => r.phone.replace(/\D/g, ''))
    const names = refs.map((r) => (r.name || '').trim().toLowerCase().replace(/\s+/g, ' '))
    if (new Set(phones).size !== phones.length) return false
    if (new Set(names).size !== names.length) return false
    if (ctx.ownPhone && phones.includes(ctx.ownPhone)) return false
    return true
  }
  if (s.type === 'bank_account') {
    const ba = v as { type?: string; bank_code?: string; account_number?: string } | null
    if (!ba || !ba.bank_code || !ba.account_number) return false
    const max = ba.type === 'CARD' ? 16 : 18
    return ba.account_number.replace(/\D/g, '').length === max
  }
  if (s.type === 'kyc_ine') {
    const k = v as {
      front_image?: string; back_image?: string;
      personal?: { curp?: string; clave_elector?: string; numero_ocr?: string }
    } | null
    if (!k?.front_image || !k?.back_image) return false
    if (ctx.hasKycProvider) return true
    // Sin OCR: validar los IDENTIFICADORES del INE (CURP, clave de elector, OCR).
    const p = k.personal ?? {}
    const curpOk = !!p.curp && /^[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]\d$/.test(p.curp.toUpperCase())
    const claveOk = !!p.clave_elector && /^[A-Z0-9]{18}$/.test(p.clave_elector.toUpperCase())
    const ocrOk = !!p.numero_ocr && /^\d{13}$/.test(p.numero_ocr)
    return curpOk && claveOk && ocrOk
  }
  if (s.type === 'personal_data') {
    const pd = v as { first_name?: string; last_name?: string; birth_date?: string; rfc?: string; gender?: string; is_mexican?: string; birth_state?: string; nationality?: string } | null
    if (!pd) return false
    if (!pd.first_name || pd.first_name.trim().length < 2) return false
    if (!pd.last_name || pd.last_name.trim().length < 2) return false
    if (!pd.birth_date || pd.birth_date.length !== 10) return false
    if (pd.gender !== 'M' && pd.gender !== 'F') return false
    if (pd.is_mexican !== 'SI' && pd.is_mexican !== 'NO') return false
    if (pd.is_mexican === 'SI' && !pd.birth_state) return false
    if (!pd.rfc || !/^[A-ZÑ&]{4}\d{6}[A-Z0-9]{3}$/.test(pd.rfc.toUpperCase())) return false
    return true
  }
  if (s.type === 'address') {
    const ad = v as { postal_code?: string; state?: string; municipality?: string; neighborhood?: string; street?: string; ext_number?: string; housing_type?: string; years_at_address?: number; months_at_address?: number } | null
    return !!ad
      && /^\d{5}$/.test(ad.postal_code || '')
      && !!ad.state && !!ad.municipality && !!ad.neighborhood && !!ad.street && !!ad.ext_number
      && !!ad.housing_type
      && ((ad.years_at_address ?? 0) > 0 || (ad.months_at_address ?? 0) > 0)
  }
  if (s.type === 'kyc_selfie') {
    return typeof v === 'string' && v.length > 0
  }
  return v !== null && v !== '' && v !== undefined
}
