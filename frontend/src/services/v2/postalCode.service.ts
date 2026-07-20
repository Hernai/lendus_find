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

export interface MunicipalitiesResponse {
  estado: string
  municipios: string[]
}

/**
 * Municipios (distintos, ordenados alfabéticamente) de un estado desde el
 * catálogo SEPOMEX. Endpoint público:
 *   GET /v2/public/postal-codes/municipios/{estado}
 *
 * `estado` es el código del enum MexicanState (p. ej. `CDMX`, `JAL`). Estado sin
 * datos o error de red → arreglo vacío (nunca lanza; el paso degrada con
 * seguridad a captura manual del municipio).
 */
export async function fetchMunicipalities(estado: string): Promise<string[]> {
  if (!estado) return []
  try {
    const res = await api.get<{ success: boolean; data: MunicipalitiesResponse }>(
      `/v2/public/postal-codes/municipios/${encodeURIComponent(estado)}`,
    )
    return res.data?.data?.municipios ?? []
  } catch {
    return []
  }
}
