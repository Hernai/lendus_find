/**
 * Shared catalog constants for the application.
 *
 * Use these instead of hardcoding lists in components.
 * These are static data that doesn't change per tenant.
 */

export interface CatalogOption {
  value: string
  label: string
}

/**
 * Mexican states with CURP codes.
 * Used for birth state selection and address state selection.
 */
export const MEXICAN_STATES: CatalogOption[] = [
  { value: 'AS', label: 'Aguascalientes' },
  { value: 'BC', label: 'Baja California' },
  { value: 'BS', label: 'Baja California Sur' },
  { value: 'CC', label: 'Campeche' },
  { value: 'CS', label: 'Chiapas' },
  { value: 'CH', label: 'Chihuahua' },
  { value: 'CL', label: 'Coahuila' },
  { value: 'CM', label: 'Colima' },
  { value: 'DF', label: 'Ciudad de México' },
  { value: 'DG', label: 'Durango' },
  { value: 'GT', label: 'Guanajuato' },
  { value: 'GR', label: 'Guerrero' },
  { value: 'HG', label: 'Hidalgo' },
  { value: 'JC', label: 'Jalisco' },
  { value: 'MC', label: 'Estado de México' },
  { value: 'MN', label: 'Michoacán' },
  { value: 'MS', label: 'Morelos' },
  { value: 'NT', label: 'Nayarit' },
  { value: 'NL', label: 'Nuevo León' },
  { value: 'OC', label: 'Oaxaca' },
  { value: 'PL', label: 'Puebla' },
  { value: 'QT', label: 'Querétaro' },
  { value: 'QR', label: 'Quintana Roo' },
  { value: 'SP', label: 'San Luis Potosí' },
  { value: 'SL', label: 'Sinaloa' },
  { value: 'SR', label: 'Sonora' },
  { value: 'TC', label: 'Tabasco' },
  { value: 'TS', label: 'Tamaulipas' },
  { value: 'TL', label: 'Tlaxcala' },
  { value: 'VZ', label: 'Veracruz' },
  { value: 'YN', label: 'Yucatán' },
  { value: 'ZS', label: 'Zacatecas' },
  { value: 'NE', label: 'Nacido en el Extranjero' },
]

/**
 * Mexican states with full names as values (uppercase).
 * Used for address forms where full state name is needed.
 */
export const MEXICAN_STATES_FULL: CatalogOption[] = [
  { value: 'AGUASCALIENTES', label: 'Aguascalientes' },
  { value: 'BAJA CALIFORNIA', label: 'Baja California' },
  { value: 'BAJA CALIFORNIA SUR', label: 'Baja California Sur' },
  { value: 'CAMPECHE', label: 'Campeche' },
  { value: 'CHIAPAS', label: 'Chiapas' },
  { value: 'CHIHUAHUA', label: 'Chihuahua' },
  { value: 'CIUDAD DE MEXICO', label: 'Ciudad de México' },
  { value: 'COAHUILA', label: 'Coahuila' },
  { value: 'COLIMA', label: 'Colima' },
  { value: 'DURANGO', label: 'Durango' },
  { value: 'GUANAJUATO', label: 'Guanajuato' },
  { value: 'GUERRERO', label: 'Guerrero' },
  { value: 'HIDALGO', label: 'Hidalgo' },
  { value: 'JALISCO', label: 'Jalisco' },
  { value: 'MEXICO', label: 'Estado de México' },
  { value: 'MICHOACAN', label: 'Michoacán' },
  { value: 'MORELOS', label: 'Morelos' },
  { value: 'NAYARIT', label: 'Nayarit' },
  { value: 'NUEVO LEON', label: 'Nuevo León' },
  { value: 'OAXACA', label: 'Oaxaca' },
  { value: 'PUEBLA', label: 'Puebla' },
  { value: 'QUERETARO', label: 'Querétaro' },
  { value: 'QUINTANA ROO', label: 'Quintana Roo' },
  { value: 'SAN LUIS POTOSI', label: 'San Luis Potosí' },
  { value: 'SINALOA', label: 'Sinaloa' },
  { value: 'SONORA', label: 'Sonora' },
  { value: 'TABASCO', label: 'Tabasco' },
  { value: 'TAMAULIPAS', label: 'Tamaulipas' },
  { value: 'TLAXCALA', label: 'Tlaxcala' },
  { value: 'VERACRUZ', label: 'Veracruz' },
  { value: 'YUCATAN', label: 'Yucatán' },
  { value: 'ZACATECAS', label: 'Zacatecas' },
]

/**
 * Countries list for nationality selection.
 * Common countries for Mexican loan applicants.
 */
export const COUNTRIES: CatalogOption[] = [
  { value: 'US', label: 'Estados Unidos' },
  { value: 'GT', label: 'Guatemala' },
  { value: 'HN', label: 'Honduras' },
  { value: 'SV', label: 'El Salvador' },
  { value: 'NI', label: 'Nicaragua' },
  { value: 'CR', label: 'Costa Rica' },
  { value: 'PA', label: 'Panamá' },
  { value: 'CO', label: 'Colombia' },
  { value: 'VE', label: 'Venezuela' },
  { value: 'PE', label: 'Perú' },
  { value: 'EC', label: 'Ecuador' },
  { value: 'AR', label: 'Argentina' },
  { value: 'CL', label: 'Chile' },
  { value: 'BR', label: 'Brasil' },
  { value: 'CU', label: 'Cuba' },
  { value: 'DO', label: 'República Dominicana' },
  { value: 'PR', label: 'Puerto Rico' },
  { value: 'ES', label: 'España' },
  { value: 'FR', label: 'Francia' },
  { value: 'DE', label: 'Alemania' },
  { value: 'IT', label: 'Italia' },
  { value: 'GB', label: 'Reino Unido' },
  { value: 'CA', label: 'Canadá' },
  { value: 'CN', label: 'China' },
  { value: 'JP', label: 'Japón' },
  { value: 'OTHER', label: 'Otro país' },
]

/**
 * Yes/No options for binary questions.
 */
export const YES_NO_OPTIONS: CatalogOption[] = [
  { value: 'SI', label: 'Sí' },
  { value: 'NO', label: 'No' },
]

/**
 * State abbreviation mappings for INE address parsing.
 * Maps various abbreviation formats to full state names.
 */
export const STATE_ABBREVIATIONS: Record<string, string> = {
  // Aguascalientes
  'AGS.': 'AGUASCALIENTES',
  'AGS': 'AGUASCALIENTES',
  'AS': 'AGUASCALIENTES',
  // Baja California
  'B.C.': 'BAJA CALIFORNIA',
  'BC': 'BAJA CALIFORNIA',
  'BCN': 'BAJA CALIFORNIA',
  // Baja California Sur
  'B.C.S.': 'BAJA CALIFORNIA SUR',
  'BCS': 'BAJA CALIFORNIA SUR',
  'BS': 'BAJA CALIFORNIA SUR',
  // Campeche
  'CAMP.': 'CAMPECHE',
  'CAM': 'CAMPECHE',
  'CC': 'CAMPECHE',
  // Chiapas
  'CHIS.': 'CHIAPAS',
  'CHIS': 'CHIAPAS',
  'CS': 'CHIAPAS',
  // Chihuahua
  'CHIH.': 'CHIHUAHUA',
  'CHIH': 'CHIHUAHUA',
  'CH': 'CHIHUAHUA',
  // Coahuila
  'COAH.': 'COAHUILA',
  'COAH': 'COAHUILA',
  'CL': 'COAHUILA',
  // Colima
  'COL.': 'COLIMA',
  'COL': 'COLIMA',
  'CM': 'COLIMA',
  // Ciudad de México
  'CDMX': 'CIUDAD DE MEXICO',
  'CDMEX': 'CIUDAD DE MEXICO',
  'D.F.': 'CIUDAD DE MEXICO',
  'DF': 'CIUDAD DE MEXICO',
  // Durango
  'DGO.': 'DURANGO',
  'DGO': 'DURANGO',
  'DG': 'DURANGO',
  // Estado de México
  'EDO. MEX.': 'MEXICO',
  'EDO.MEX.': 'MEXICO',
  'EDO MEX': 'MEXICO',
  'EDOMEX': 'MEXICO',
  'MEX.': 'MEXICO',
  'MEX': 'MEXICO',
  'MC': 'MEXICO',
  // Guanajuato
  'GTO.': 'GUANAJUATO',
  'GTO': 'GUANAJUATO',
  'GT': 'GUANAJUATO',
  // Guerrero
  'GRO.': 'GUERRERO',
  'GRO': 'GUERRERO',
  'GR': 'GUERRERO',
  // Hidalgo
  'HGO.': 'HIDALGO',
  'HGO': 'HIDALGO',
  'HG': 'HIDALGO',
  // Jalisco
  'JAL.': 'JALISCO',
  'JAL': 'JALISCO',
  'JC': 'JALISCO',
  // Michoacán
  'MICH.': 'MICHOACAN',
  'MICH': 'MICHOACAN',
  'MN': 'MICHOACAN',
  // Morelos
  'MOR.': 'MORELOS',
  'MOR': 'MORELOS',
  'MS': 'MORELOS',
  // Nayarit
  'NAY.': 'NAYARIT',
  'NAY': 'NAYARIT',
  'NT': 'NAYARIT',
  // Nuevo León
  'N.L.': 'NUEVO LEON',
  'NL': 'NUEVO LEON',
  // Oaxaca
  'OAX.': 'OAXACA',
  'OAX': 'OAXACA',
  'OC': 'OAXACA',
  // Puebla
  'PUE.': 'PUEBLA',
  'PUE': 'PUEBLA',
  'PL': 'PUEBLA',
  // Querétaro
  'QRO.': 'QUERETARO',
  'QRO': 'QUERETARO',
  'QT': 'QUERETARO',
  // Quintana Roo
  'Q.R.': 'QUINTANA ROO',
  'Q. ROO': 'QUINTANA ROO',
  'QROO': 'QUINTANA ROO',
  'QR': 'QUINTANA ROO',
  // San Luis Potosí
  'S.L.P.': 'SAN LUIS POTOSI',
  'SLP': 'SAN LUIS POTOSI',
  'SP': 'SAN LUIS POTOSI',
  // Sinaloa
  'SIN.': 'SINALOA',
  'SIN': 'SINALOA',
  'SL': 'SINALOA',
  // Sonora
  'SON.': 'SONORA',
  'SON': 'SONORA',
  'SR': 'SONORA',
  // Tabasco
  'TAB.': 'TABASCO',
  'TAB': 'TABASCO',
  'TC': 'TABASCO',
  // Tamaulipas
  'TAMPS.': 'TAMAULIPAS',
  'TAMPS': 'TAMAULIPAS',
  'TS': 'TAMAULIPAS',
  // Tlaxcala
  'TLAX.': 'TLAXCALA',
  'TLAX': 'TLAXCALA',
  'TL': 'TLAXCALA',
  // Veracruz
  'VER.': 'VERACRUZ',
  'VER': 'VERACRUZ',
  'VZ': 'VERACRUZ',
  // Yucatán
  'YUC.': 'YUCATAN',
  'YUC': 'YUCATAN',
  'YN': 'YUCATAN',
  // Zacatecas
  'ZAC.': 'ZACATECAS',
  'ZAC': 'ZACATECAS',
  'ZS': 'ZACATECAS',
}

/**
 * Get state name from abbreviation.
 * Handles various abbreviation formats from INE cards.
 */
export function getStateFromAbbreviation(abbr: string): string | null {
  if (!abbr) return null
  const normalized = abbr.toUpperCase().trim()
  return STATE_ABBREVIATIONS[normalized] || null
}
