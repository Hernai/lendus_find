/**
 * V2 Staff Postal Catalog Service
 *
 * Carga y aplicación del catálogo GLOBAL de códigos postales (SEPOMEX) desde el
 * panel. Flujo de dos fases: `upload` (parseo a staging vía job en cola, con
 * progreso por Reverb) → `apply` (swap atómico tras la confirmación explícita
 * del SUPER_ADMIN). `getImport` sirve como respaldo por polling del progreso.
 *
 * Todos los endpoints exigen `canConfigureTenant` (SUPER_ADMIN) y viven bajo
 * /api/v2/staff/catalogs/postal-codes.
 */

import { api } from '@/services/api'
import type { V2ApiResponse } from '@/types/v2'

const BASE = '/v2/staff/catalogs/postal-codes'

// =====================================================
// Types
// =====================================================

/** Estados del registro de importación (enum backend, UPPERCASE). */
export type PostalCodeImportStatus =
  | 'PENDING_PARSE'
  | 'PARSED'
  | 'APPLYING'
  | 'APPLIED'
  | 'REJECTED'
  | 'DISCARDED'

/** Fila de muestra del catálogo (primeras ~10 filas mapeadas del archivo). */
export interface PostalCodeSampleRow {
  cp: string | null
  asentamiento: string | null
  tipo_asentamiento: string | null
  municipio: string | null
  estado: string | null
  ciudad: string | null
  estado_clave: string | null
  municipio_clave: string | null
}

/** Registro de importación: auditoría + máquina de estado + preview. */
export interface PostalCodeImport {
  id: string
  status: PostalCodeImportStatus
  status_label: string
  original_filename: string
  rows_count: number | null
  states_count: number | null
  municipalities_count: number | null
  sample: PostalCodeSampleRow[] | null
  rejection_reason: string | null
  staff_account_id: string | null
  origin_tenant_id: string | null
  origin_tenant_slug: string | null
  created_at: string | null
  updated_at: string | null
}

/** Meta de paginación del historial. */
export interface PostalCodeImportListMeta {
  current_page: number
  from: number | null
  last_page: number
  per_page: number
  to: number | null
  total: number
}

/** Vigencia actual del catálogo (endpoint `/status`). */
export interface PostalCatalogStatus {
  /** Última importación aplicada: `{ at, count }` o null si nunca se importó. */
  last_import: { at: string; count: number } | null
  /** Conteo vivo de filas en `postal_codes`. */
  current_count: number
}

/** Fases emitidas por el evento Reverb `progress`. */
export type PostalCodeImportPhase =
  | 'parsing'
  | 'validating'
  | 'ready'
  | 'rejected'
  | 'applying'
  | 'applied'

/**
 * Payload del evento Reverb `progress` en el canal privado
 * `postal-codes-import.{importId}` (broadcastAs `progress`). Solo llegan las
 * llaves con valor según la fase.
 */
export interface PostalCodeImportProgressEvent {
  importId: string
  phase: PostalCodeImportPhase
  processed?: number
  rows?: number
  states?: number
  municipalities?: number
  reason?: string
}

// =====================================================
// API Functions
// =====================================================

/**
 * Sube el archivo (.zip/.txt/.csv, máx 30 MB) y despacha el parseo.
 * Devuelve el registro de importación recién creado (PENDING_PARSE); su `id`
 * nombra el canal Reverb del progreso.
 */
export async function upload(file: File): Promise<V2ApiResponse<PostalCodeImport>> {
  const formData = new FormData()
  formData.append('file', file)

  const res = await api.post<V2ApiResponse<PostalCodeImport>>(`${BASE}/upload`, formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
    // El parseo de ZIP grandes puede tardar; el upload en sí sube el archivo.
    timeout: 120000,
  })
  return res.data
}

/** Historial de importaciones (auditoría global), más reciente primero. */
export async function listImports(params?: {
  per_page?: number
  page?: number
}): Promise<V2ApiResponse<{ imports: PostalCodeImport[]; meta: PostalCodeImportListMeta }>> {
  const res = await api.get<V2ApiResponse<{ imports: PostalCodeImport[]; meta: PostalCodeImportListMeta }>>(
    `${BASE}/imports`,
    { params },
  )
  return res.data
}

/** Estado + resumen de una importación (preview y respaldo de polling). */
export async function getImport(id: string): Promise<V2ApiResponse<PostalCodeImport>> {
  const res = await api.get<V2ApiResponse<PostalCodeImport>>(`${BASE}/imports/${id}`)
  return res.data
}

/** Aplica el swap atómico de un import en `PARSED` → `APPLIED`. */
export async function apply(id: string): Promise<V2ApiResponse<PostalCodeImport>> {
  const res = await api.post<V2ApiResponse<PostalCodeImport>>(`${BASE}/imports/${id}/apply`)
  return res.data
}

/**
 * Descarta un import en `PENDING_PARSE`/`PARSED` (libera la staging única y
 * compartida para permitir una nueva carga). Devuelve `{ id }` del import
 * descartado. Responde 422 `INVALID_STATE` si el import ya no admite descarte y
 * 404 si no existe.
 */
export async function discard(id: string): Promise<V2ApiResponse<{ id: string }>> {
  const res = await api.post<V2ApiResponse<{ id: string }>>(`${BASE}/imports/${id}/discard`)
  return res.data
}

/** Vigencia actual del catálogo: última importación y conteo vivo. */
export async function getStatus(): Promise<V2ApiResponse<PostalCatalogStatus>> {
  const res = await api.get<V2ApiResponse<PostalCatalogStatus>>(`${BASE}/status`)
  return res.data
}

export default {
  upload,
  listImports,
  getImport,
  apply,
  discard,
  getStatus,
}
