/**
 * Catálogo de bancos mexicanos compartido por el onboarding (captura,
 * persistencia y revisión). Fuente única de verdad para evitar duplicación.
 *
 * `code` = clave de institución ABM/Banxico (3 dígitos), que coincide con los
 * primeros 3 dígitos de la CLABE — permite derivar el banco desde la CLABE.
 *
 * `validForTransfer` = la institución acredita transferencias de inmediato
 * (SPEI/CEP), por lo que el préstamo puede dispersarse ahí sin demora. Es la
 * fuente única de verdad consumida por el paso de cuenta bancaria y por
 * cualquier derivación de banco. Default conservador: si no está confirmado
 * (incluida la comodín "Otro"), es `false`.
 */
export interface BankOption {
  code: string
  name: string
  validForTransfer: boolean
}

// Ordenados: primero los más usados por clientes (incluidos los digitales),
// luego el resto de instituciones. Claves ABM/Banxico.
export const MEXICAN_BANKS: BankOption[] = [
  // Más comunes
  { code: '012', name: 'BBVA México', validForTransfer: true },
  { code: '002', name: 'Citibanamex', validForTransfer: true },
  { code: '014', name: 'Santander', validForTransfer: true },
  { code: '072', name: 'Banorte', validForTransfer: true },
  { code: '021', name: 'HSBC', validForTransfer: true },
  { code: '044', name: 'Scotiabank', validForTransfer: true },
  { code: '036', name: 'Inbursa', validForTransfer: true },
  { code: '127', name: 'Banco Azteca', validForTransfer: false },
  { code: '137', name: 'BanCoppel', validForTransfer: false },
  { code: '030', name: 'Banco del Bajío', validForTransfer: true },
  { code: '062', name: 'Afirme', validForTransfer: true },
  { code: '058', name: 'Banregio', validForTransfer: true },
  // Digitales / neobancos (nuevos)
  { code: '638', name: 'Nu México', validForTransfer: true },
  { code: '722', name: 'Mercado Pago', validForTransfer: false },
  { code: '661', name: 'Klar', validForTransfer: true },
  { code: '728', name: 'SPIN by OXXO', validForTransfer: true },
  { code: '723', name: 'Cuenca', validForTransfer: true },
  { code: '659', name: 'Stori', validForTransfer: true },
  { code: '646', name: 'STP', validForTransfer: false },
  { code: '710', name: 'NVIO', validForTransfer: true },
  // Resto de bancos comerciales
  { code: '019', name: 'Banjército', validForTransfer: true },
  { code: '042', name: 'Mifel', validForTransfer: true },
  { code: '059', name: 'Invex', validForTransfer: true },
  { code: '060', name: 'Bansi', validForTransfer: true },
  { code: '106', name: 'Bank of America', validForTransfer: false },
  { code: '108', name: 'MUFG', validForTransfer: false },
  { code: '110', name: 'JP Morgan', validForTransfer: false },
  { code: '112', name: 'Banco Monex', validForTransfer: false },
  { code: '113', name: 'Ve por Más (BX+)', validForTransfer: true },
  { code: '128', name: 'Banco Autofin', validForTransfer: false },
  { code: '129', name: 'Barclays', validForTransfer: false },
  { code: '130', name: 'Compartamos Banco', validForTransfer: true },
  { code: '132', name: 'Multiva', validForTransfer: true },
  { code: '133', name: 'Actinver', validForTransfer: false },
  { code: '136', name: 'Intercam Banco', validForTransfer: false },
  { code: '140', name: 'Consubanco', validForTransfer: false },
  { code: '141', name: 'Volkswagen Bank', validForTransfer: false },
  { code: '143', name: 'CIBanco', validForTransfer: false },
  { code: '145', name: 'Banco BASE', validForTransfer: true },
  { code: '147', name: 'Bankaool', validForTransfer: false },
  { code: '148', name: 'Banco PagaTodo', validForTransfer: false },
  { code: '150', name: 'Inmobiliario Mexicano', validForTransfer: false },
  { code: '151', name: 'Dondé Banco', validForTransfer: false },
  { code: '152', name: 'Bancrea', validForTransfer: false },
  { code: '154', name: 'Banco Finterra', validForTransfer: false },
  { code: '155', name: 'ICBC', validForTransfer: false },
  { code: '156', name: 'Banco Sabadell', validForTransfer: false },
  { code: '160', name: 'Banco S3', validForTransfer: false },
  { code: '166', name: 'Banco del Bienestar', validForTransfer: false },
  { code: '168', name: 'Sociedad Hipotecaria Federal', validForTransfer: false },
  // Instituciones de fondos de pago electrónico / otros
  { code: '652', name: 'Credicapital', validForTransfer: false },
  { code: '653', name: 'Kuspit', validForTransfer: false },
  { code: '670', name: 'Libertad', validForTransfer: false },
  { code: '677', name: 'Caja Popular Mexicana', validForTransfer: false },
  { code: '684', name: 'Transfer', validForTransfer: false },
  { code: '706', name: 'Arcus', validForTransfer: false },
  { code: 'OTHR', name: 'Otro', validForTransfer: false },
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
