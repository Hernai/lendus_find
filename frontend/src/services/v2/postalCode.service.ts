import { api } from '../api'

/**
 * Consulta del catálogo SEPOMEX (CP → estado/municipio/colonias).
 * Endpoint público: GET /v2/public/postal-codes/{cp}
 */

export interface PostalCodeColonia {
  nombre: string
  tipo: string | null
}

export interface PostalCodeLookup {
  cp: string
  estado: string
  municipio: string
  ciudad: string | null
  colonias: PostalCodeColonia[]
}

export async function lookupPostalCode(cp: string): Promise<PostalCodeLookup | null> {
  try {
    const res = await api.get<{ success: boolean; data: PostalCodeLookup }>(
      `/v2/public/postal-codes/${cp}`,
    )
    return res.data?.data ?? null
  } catch {
    // 404 (CP inexistente) o error de red → el usuario llena a mano.
    return null
  }
}
