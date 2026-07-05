/**
 * Gateway de auth de staff (dependency inversion).
 *
 * El store compartido `stores/auth.ts` maneja login de applicant Y de staff, pero
 * NO debe depender del módulo admin (regla: las features dependen de shared, nunca
 * al revés). En vez de importar `@/modules/admin/services`, el store consume este
 * gateway compartido; el módulo admin REGISTRA su implementación al cargarse
 * (ver modules/admin/routes.ts). Así la dependencia queda admin → shared.
 */

import type { V2ApiResponse, V2StaffUser, V2StaffLoginPayload } from '@/types/v2'

// Tipos SHARED (no del módulo admin): el contrato queda tan preciso como el
// servicio real, sin acoplar la capa compartida a modules/admin.
export interface StaffAuthGateway {
  login(payload: V2StaffLoginPayload): Promise<V2ApiResponse<{ token: string; user: V2StaffUser }>>
  logout(): Promise<V2ApiResponse<null>>
  getMe(): Promise<V2ApiResponse<{ user: V2StaffUser }>>
}

let impl: StaffAuthGateway | null = null

/** El módulo admin llama esto al cargarse para inyectar su implementación. */
export function registerStaffAuth(gateway: StaffAuthGateway): void {
  impl = gateway
}

/** Accesor usado por el store de auth. Lanza si el módulo admin no se cargó. */
export function staffAuth(): StaffAuthGateway {
  if (!impl) {
    throw new Error('Staff auth gateway no registrado (¿se cargó el módulo admin?).')
  }
  return impl
}
