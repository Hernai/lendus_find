import { api } from '../api'

/**
 * Geocodificación inversa (lat/lng → dirección) para "Estoy en mi domicilio".
 * El backend resuelve por cadena Google → Nominatim (OSM) → coords_only:
 * `source` = 'google' | 'osm' cuando trae dirección, o 'coords_only' (solo
 * coordenadas; el CP autollena la colonia vía SEPOMEX).
 */

export interface ReverseGeocodeResult {
  source: 'google' | 'osm' | 'coords_only'
  lat: number
  lng: number
  formatted_address?: string | null
  postal_code?: string | null
  state?: string | null
  municipality?: string | null
  city?: string | null
  neighborhood?: string | null
  street?: string | null
  ext_number?: string | null
}

export async function reverseGeocode(lat: number, lng: number): Promise<ReverseGeocodeResult | null> {
  try {
    const res = await api.post<{ success: boolean; data: ReverseGeocodeResult }>(
      '/v2/applicant/geo/reverse',
      { lat, lng },
    )
    return res.data?.data ?? null
  } catch {
    return null
  }
}
