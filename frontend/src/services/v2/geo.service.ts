import { api } from '../api'

/**
 * Geocodificación inversa (lat/lng → dirección) para "Estoy en mi domicilio".
 * Si el tenant tiene Google Maps configurado, devuelve la dirección; si no,
 * source = 'coords_only' (solo coordenadas; el CP autollena vía SEPOMEX).
 */

export interface ReverseGeocodeResult {
  source: 'google' | 'coords_only'
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
