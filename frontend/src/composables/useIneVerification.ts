import { verifyIne, type IneVerifyData } from '@/services/v2/kyc.applicant.service'

export interface IneVerifiedFields {
  nombres: string
  apellido_paterno: string
  apellido_materno: string
  curp: string
}

export interface IneVerificationResult {
  /** Datos extraídos por el OCR (base para la confirmación del cliente). */
  fields: IneVerifiedFields
  /** Validación INE contra lista nominal (null si no se pudo determinar). */
  ineValid: boolean | null
  /** Validación de la CURP con RENAPO (null si no se validó). */
  curpValid: boolean | null
  /** Datos oficiales de RENAPO. */
  renapo: { nombres: string; apellido_paterno: string; apellido_materno: string } | null
  /** Diferencias OCR vs RENAPO calculadas por el backend. */
  diffs: Record<string, { ocr: string; renapo: string }>
}

/**
 * Verificación de INE. TODA la lógica de negocio vive en el backend
 * (POST /v2/applicant/kyc/ine/verify): OCR + validación INE + CURP RENAPO +
 * persistencia + diferencias. Este composable es solo el punto de entrada del
 * front (una llamada) para que cualquier onboarding (dinámico o legacy) la use
 * igual. No decide UI ni bloqueo.
 */
export function useIneVerification() {
  async function verify(frontImage: string, backImage?: string | null): Promise<IneVerificationResult> {
    const res: IneVerifyData = await verifyIne(frontImage, backImage ?? null, true)
    return {
      fields: {
        nombres: res.fields?.nombres ?? '',
        apellido_paterno: res.fields?.apellido_paterno ?? '',
        apellido_materno: res.fields?.apellido_materno ?? '',
        curp: res.fields?.curp ?? '',
      },
      ineValid: res.ine_valid ?? null,
      curpValid: res.curp_valid ?? null,
      renapo: res.renapo ?? null,
      diffs: res.diffs ?? {},
    }
  }

  return { verify }
}
