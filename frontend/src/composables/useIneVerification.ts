import {
  validateIne,
  validateCurp,
  type IneOcrData,
} from '@/services/v2/kyc.applicant.service'
import { logger } from '@/utils/logger'

const log = logger.child('IneVerification')

export interface IneVerifiedFields {
  nombres: string
  apellido_paterno: string
  apellido_materno: string
  curp: string
}

export interface IneVerificationResult {
  /** Datos extraídos por el OCR (base para la confirmación del cliente). */
  fields: IneVerifiedFields
  ocr: IneOcrData | null
  /** Validación INE contra lista nominal (null si no se pudo determinar). */
  ineValid: boolean | null
  /** Validación de la CURP con RENAPO (null si no se validó). */
  curpValid: boolean | null
  /** Datos oficiales devueltos por RENAPO (para comparar contra el OCR/editado). */
  renapo: { nombres: string; apellido_paterno: string; apellido_materno: string } | null
}

/**
 * Lógica de negocio COMPARTIDA de verificación de INE con Nubarium. La usan
 * todos los onboardings (dinámico de MoneyCapital y legacy de Finatea/Demo),
 * porque el flujo es el mismo:
 *   1) OCR + validación INE (lista nominal)
 *   2) validación de la CURP extraída con RENAPO
 *
 * No decide UI ni bloqueo: devuelve los datos + validez para que la vista
 * muestre la pantalla de confirmación y persista lo que el cliente confirme.
 */
export function useIneVerification() {
  /**
   * Ejecuta OCR + validación INE y, con la CURP extraída, la validación RENAPO.
   * Lanza si el OCR falla (el llamador decide no bloquear y marcar para el admin).
   */
  async function verify(frontImage: string, backImage?: string | null): Promise<IneVerificationResult> {
    // 1) OCR + validación INE (lista nominal)
    const ine = await validateIne(frontImage, backImage ?? null, true)
    const ocr = ine.ocr_data ?? null
    const fields: IneVerifiedFields = {
      nombres: (ocr?.nombres ?? '').toUpperCase().trim(),
      apellido_paterno: (ocr?.apellido_paterno ?? '').toUpperCase().trim(),
      apellido_materno: (ocr?.apellido_materno ?? '').toUpperCase().trim(),
      curp: (ocr?.curp ?? '').replace(/\s+/g, '').toUpperCase(),
    }
    const ineValid = ine.is_valid ?? ine.list_validation?.valid ?? null

    // 2) Validación de la CURP con RENAPO (si el OCR la extrajo). Si falla, no
    //    rompe el flujo: solo queda sin validar.
    let curpValid: boolean | null = null
    let renapo: IneVerificationResult['renapo'] = null
    if (fields.curp) {
      try {
        const res = await validateCurp(fields.curp)
        curpValid = res.valid
        if (res.curp_data) {
          renapo = {
            nombres: (res.curp_data.nombres ?? '').toUpperCase().trim(),
            apellido_paterno: (res.curp_data.apellido_paterno ?? '').toUpperCase().trim(),
            apellido_materno: (res.curp_data.apellido_materno ?? '').toUpperCase().trim(),
          }
        }
      } catch (e) {
        log.warn('validación de CURP con RENAPO falló', { error: e })
      }
    }

    return { fields, ocr, ineValid, curpValid, renapo }
  }

  /**
   * Diferencias entre la referencia (RENAPO si validó, si no el OCR) y lo que el
   * cliente confirmó/editó. Sirve para que el admin revise cuando hay mucha
   * diferencia entre lo leído y lo que el cliente dejó.
   */
  function computeDiffs(
    reference: IneVerifiedFields | null,
    confirmed: IneVerifiedFields,
  ): Record<string, { reference: string; confirmed: string }> {
    const diffs: Record<string, { reference: string; confirmed: string }> = {}
    if (!reference) return diffs
    for (const k of ['nombres', 'apellido_paterno', 'apellido_materno', 'curp'] as const) {
      if ((reference[k] ?? '') !== (confirmed[k] ?? '')) {
        diffs[k] = { reference: reference[k] ?? '', confirmed: confirmed[k] ?? '' }
      }
    }
    return diffs
  }

  return { verify, computeDiffs }
}
