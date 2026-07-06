import type { Application } from '@/modules/admin/views/panel/applicationDetail.types'

/**
 * Helpers de LECTURA del estado de verificación por campo (application.field_verifications).
 *
 * Extraídos de AdminApplicationDetail.vue para compartirlos entre el padre y los
 * hijos (ApplicantDataSection verifica ~13 campos; pasar 5 booleanos por campo como
 * props no escala). Son funciones puras: solo LEEN field_verifications, sin efectos
 * ni mutación — los handlers de escritura (verifyData/openRejectDataModal/…) viven
 * en el padre.
 *
 * Recibe un GETTER de la solicitud (no un Ref) para admitir tanto el ref del padre
 * (`() => application.value`) como la prop no-nula de un hijo (`() => props.application`)
 * sin fricción de invarianza de Ref. Se lee `getApplication()` dentro de cada helper
 * para mantener la reactividad (el flujo optimista muta application.value.field_verifications).
 */
export function useFieldVerification(getApplication: () => Application | null) {
  const isFieldVerified = (field: string): boolean => {
    const verification = getApplication()?.field_verifications?.[field]
    return verification?.status === 'VERIFIED' || verification?.verified === true
  }

  const isFieldRejected = (field: string): boolean => {
    const verification = getApplication()?.field_verifications?.[field]
    return verification?.status === 'REJECTED'
  }

  const isFieldPending = (field: string): boolean => {
    const verification = getApplication()?.field_verifications?.[field]
    return verification?.status === 'PENDING'
  }

  const getFieldVerification = (field: string) => {
    return getApplication()?.field_verifications?.[field]
  }

  const isFieldLocked = (field: string): boolean => {
    const verification = getApplication()?.field_verifications?.[field]
    return verification?.is_locked === true
  }

  const getFieldLabel = (field: string): string => {
    const labels: Record<string, string> = {
      'first_name': 'Nombre',
      'last_name_1': 'Apellido Paterno',
      'last_name_2': 'Apellido Materno',
      'curp': 'CURP',
      'rfc': 'RFC',
      'ine_clave': 'Clave INE',
      'birth_date': 'Fecha de Nacimiento',
      'phone': 'Teléfono',
      'email': 'Email',
      'address': 'Dirección',
      'employment': 'Información Laboral',
      'passport_number': 'Número de Pasaporte',
      'passport_issue_date': 'Fecha de Emisión (Pasaporte)',
      'passport_expiry_date': 'Fecha de Expiración (Pasaporte)'
    }
    return labels[field] || field
  }

  return {
    isFieldVerified,
    isFieldRejected,
    isFieldPending,
    getFieldVerification,
    isFieldLocked,
    getFieldLabel,
  }
}
