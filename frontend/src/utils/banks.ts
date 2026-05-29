/**
 * Catálogo de bancos mexicanos compartido por el onboarding (captura,
 * persistencia y revisión). Fuente única de verdad para evitar duplicación.
 */
export interface BankOption {
  code: string
  name: string
}

export const MEXICAN_BANKS: BankOption[] = [
  { code: 'BBVA', name: 'BBVA México' },
  { code: 'BNMX', name: 'Citibanamex' },
  { code: 'SANT', name: 'Santander' },
  { code: 'HSBC', name: 'HSBC' },
  { code: 'BNRT', name: 'Banorte' },
  { code: 'SCOT', name: 'Scotiabank' },
  { code: 'INBR', name: 'Inbursa' },
  { code: 'AZTC', name: 'Banco Azteca' },
  { code: 'COPP', name: 'Banco Coppel' },
  { code: 'BJIO', name: 'Banco del Bajío' },
  { code: 'AFRM', name: 'Afirme' },
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
