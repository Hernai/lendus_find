/**
 * Catálogo de bancos mexicanos compartido por el onboarding (captura,
 * persistencia y revisión). Fuente única de verdad para evitar duplicación.
 *
 * `code` = clave de institución ABM/Banxico (3 dígitos), que coincide con los
 * primeros 3 dígitos de la CLABE — permite derivar el banco desde la CLABE.
 */
export interface BankOption {
  code: string
  name: string
}

// Ordenados: primero los más usados por clientes (incluidos los digitales),
// luego el resto de instituciones. Claves ABM/Banxico.
export const MEXICAN_BANKS: BankOption[] = [
  // Más comunes
  { code: '012', name: 'BBVA México' },
  { code: '002', name: 'Citibanamex' },
  { code: '014', name: 'Santander' },
  { code: '072', name: 'Banorte' },
  { code: '021', name: 'HSBC' },
  { code: '044', name: 'Scotiabank' },
  { code: '036', name: 'Inbursa' },
  { code: '127', name: 'Banco Azteca' },
  { code: '137', name: 'BanCoppel' },
  { code: '030', name: 'Banco del Bajío' },
  { code: '062', name: 'Afirme' },
  { code: '058', name: 'Banregio' },
  // Digitales / neobancos (nuevos)
  { code: '638', name: 'Nu México' },
  { code: '722', name: 'Mercado Pago' },
  { code: '661', name: 'Klar' },
  { code: '728', name: 'SPIN by OXXO' },
  { code: '723', name: 'Cuenca' },
  { code: '659', name: 'Stori' },
  { code: '646', name: 'STP' },
  { code: '710', name: 'NVIO' },
  // Resto de bancos comerciales
  { code: '019', name: 'Banjército' },
  { code: '042', name: 'Mifel' },
  { code: '059', name: 'Invex' },
  { code: '060', name: 'Bansi' },
  { code: '106', name: 'Bank of America' },
  { code: '108', name: 'MUFG' },
  { code: '110', name: 'JP Morgan' },
  { code: '112', name: 'Banco Monex' },
  { code: '113', name: 'Ve por Más (BX+)' },
  { code: '128', name: 'Banco Autofin' },
  { code: '129', name: 'Barclays' },
  { code: '130', name: 'Compartamos Banco' },
  { code: '132', name: 'Multiva' },
  { code: '133', name: 'Actinver' },
  { code: '136', name: 'Intercam Banco' },
  { code: '140', name: 'Consubanco' },
  { code: '141', name: 'Volkswagen Bank' },
  { code: '143', name: 'CIBanco' },
  { code: '145', name: 'Banco BASE' },
  { code: '147', name: 'Bankaool' },
  { code: '148', name: 'Banco PagaTodo' },
  { code: '150', name: 'Inmobiliario Mexicano' },
  { code: '151', name: 'Dondé Banco' },
  { code: '152', name: 'Bancrea' },
  { code: '154', name: 'Banco Finterra' },
  { code: '155', name: 'ICBC' },
  { code: '156', name: 'Banco Sabadell' },
  { code: '160', name: 'Banco S3' },
  { code: '166', name: 'Banco del Bienestar' },
  { code: '168', name: 'Sociedad Hipotecaria Federal' },
  // Instituciones de fondos de pago electrónico / otros
  { code: '652', name: 'Credicapital' },
  { code: '653', name: 'Kuspit' },
  { code: '670', name: 'Libertad' },
  { code: '677', name: 'Caja Popular Mexicana' },
  { code: '684', name: 'Transfer' },
  { code: '706', name: 'Arcus' },
  { code: 'OTHR', name: 'Otro' },
]

/** Mapa code → nombre, para lookups rápidos. */
export const BANK_NAMES: Record<string, string> = Object.fromEntries(
  MEXICAN_BANKS.map((b) => [b.code, b.name]),
)

/** Devuelve el nombre del banco o el code si no está en el catálogo. */
export function bankName(code: string | null | undefined): string {
  const c = String(code ?? '')
  return BANK_NAMES[c] || c
}

/**
 * Deriva el banco desde la CLABE: los primeros 3 dígitos son la clave de la
 * institución (ABM/Banxico). Devuelve la opción del catálogo o null.
 */
export function bankFromClabe(clabe: string | null | undefined): BankOption | null {
  const digits = String(clabe ?? '').replace(/\D/g, '')
  if (digits.length < 3) return null
  const code = digits.slice(0, 3)
  return MEXICAN_BANKS.find((b) => b.code === code) ?? null
}
