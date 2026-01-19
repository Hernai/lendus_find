/**
 * Servicio para generar RFC (Registro Federal de Contribuyentes) en México
 * Basado en el algoritmo oficial del SAT
 *
 * NOTA IMPORTANTE: Este servicio genera una SUGERENCIA de RFC basándose en el algoritmo público.
 * La homoclave y dígito verificador oficiales solo pueden ser generados por el SAT.
 * Para validación oficial, use el validador del SAT o el servicio de Nubarium.
 */

import { logger } from '@/utils/logger'

const log = logger.child('RFC')

export interface PersonaFisicaData {
  nombre: string
  primerApellido: string
  segundoApellido?: string | null
  dia: number
  mes: number
  anio: number
}

export interface RfcResult {
  rfcBase: string // 10 characters (4 letters + 6 digits)
  suggestedRfc: string // 13 characters (with homoclave placeholder)
  warning: string
}

// Lista completa de palabras inconvenientes del SAT
const PALABRAS_INCONVENIENTES = [
  'BUEI', 'BUEY', 'CACA', 'CACO', 'CAGA', 'CAGO', 'CAKA', 'CAKO',
  'COGE', 'COJA', 'COJE', 'COJI', 'COJO', 'CULO', 'FETO', 'GUEY',
  'JOTO', 'KACA', 'KACO', 'KAGA', 'KAGO', 'KOGE', 'KOJO', 'KAKA',
  'KULO', 'MAME', 'MAMO', 'MEAR', 'MEAS', 'MEON', 'MION', 'MOCO',
  'MULA', 'PEDA', 'PEDO', 'PENE', 'PUTA', 'PUTO', 'QULO', 'RATA',
  'RUIN'
]

// Palabras a eliminar de apellidos
const PARTICULAS_IGNORAR = [
  'DE', 'DEL', 'LA', 'LAS', 'LOS', 'LE', 'LES',
  'MC', 'MAC', 'VON', 'VAN', 'Y'
]

// Nombres comunes a ignorar para tomar el segundo nombre
const NOMBRES_COMUNES = ['MARIA', 'JOSE']

/**
 * Normaliza un texto eliminando acentos (excepto Ñ) y caracteres especiales
 */
function normalizarTexto(texto: string): string {
  return texto
    .toUpperCase()
    .trim()
    // Eliminar acentos excepto Ñ
    .replace(/Á/g, 'A')
    .replace(/É/g, 'E')
    .replace(/Í/g, 'I')
    .replace(/Ó/g, 'O')
    .replace(/Ú/g, 'U')
    .replace(/Ü/g, 'U')
    // Eliminar caracteres especiales (mantener letras, Ñ y espacios)
    .replace(/[^A-ZÑ\s]/g, '')
    .trim()
}

/**
 * Busca la primera vocal en una palabra
 */
function obtenerPrimeraVocal(palabra: string, saltarPrimera: boolean = false): string {
  const vocales = ['A', 'E', 'I', 'O', 'U']
  const inicio = saltarPrimera ? 1 : 0

  for (let i = inicio; i < palabra.length; i++) {
    const char = palabra[i]
    if (char && vocales.includes(char)) {
      return char
    }
  }

  return 'X'
}

/**
 * Limpia un apellido eliminando partículas (DE, DEL, LA, etc.)
 * y tomando solo la primera palabra significativa
 */
function limpiarApellido(apellido: string): string {
  const palabras = apellido.split(/\s+/).filter(p => p.length > 0)

  // Filtrar partículas
  const palabrasFiltradas = palabras.filter(p =>
    !PARTICULAS_IGNORAR.includes(p)
  )

  if (palabrasFiltradas.length === 0) {
    // Si todas las palabras fueron filtradas, usar el apellido original
    return palabras[0] ?? apellido
  }

  // Tomar solo la primera palabra significativa
  return palabrasFiltradas[0] ?? apellido
}

/**
 * Limpia un nombre aplicando la regla de María/José
 */
function limpiarNombre(nombre: string): string {
  const palabras = nombre.split(/\s+/).filter(p => p.length > 0)
  const primeraPalabra = palabras[0]
  const segundaPalabra = palabras[1]

  if (!primeraPalabra) {
    return nombre
  }

  // Si el primer nombre es María o José, usar el segundo
  if (segundaPalabra && NOMBRES_COMUNES.includes(primeraPalabra)) {
    // Verificar si el segundo también es común (José María)
    if (NOMBRES_COMUNES.includes(segundaPalabra)) {
      // José María → usar María
      return segundaPalabra
    }
    // María Fernanda → usar Fernanda
    return segundaPalabra
  }

  // Usar el primer nombre
  return primeraPalabra
}

/**
 * Extrae las 4 letras iniciales del RFC según el algoritmo oficial
 */
function extraerCuatroLetras(data: PersonaFisicaData): string {
  // Normalizar apellidos y nombre
  const primerApellido = normalizarTexto(limpiarApellido(data.primerApellido))
  const segundoApellido = data.segundoApellido
    ? normalizarTexto(limpiarApellido(data.segundoApellido))
    : ''
  const nombre = normalizarTexto(limpiarNombre(data.nombre))

  if (!primerApellido || !nombre) {
    throw new Error('Se requiere al menos un apellido y un nombre')
  }

  // 1. Primera letra del apellido paterno
  const letra1 = primerApellido[0] ?? 'X'

  // 2. Primera vocal del apellido paterno
  // Si el apellido empieza con vocal, tomar la siguiente vocal
  const empiezaConVocal = ['A', 'E', 'I', 'O', 'U'].includes(letra1)
  const letra2 = obtenerPrimeraVocal(primerApellido, empiezaConVocal)

  // 3. Primera letra del apellido materno (o X si no existe)
  const letra3 = segundoApellido ? (segundoApellido[0] ?? 'X') : 'X'

  // 4. Primera letra del nombre
  const letra4 = nombre[0] ?? 'X'

  return letra1 + letra2 + letra3 + letra4
}

/**
 * Formatea la fecha de nacimiento en formato AAMMDD
 */
function formatearFecha(dia: number, mes: number, anio: number): string {
  const anioStr = String(anio).slice(-2).padStart(2, '0')
  const mesStr = String(mes).padStart(2, '0')
  const diaStr = String(dia).padStart(2, '0')

  return anioStr + mesStr + diaStr
}

/**
 * Corrige palabras inconvenientes reemplazando la última letra con X
 */
function corregirPalabraInconveniente(letras: string): string {
  if (PALABRAS_INCONVENIENTES.includes(letras)) {
    log.debug(`Palabra inconveniente detectada: ${letras}, corrigiendo a ${letras.slice(0, 3)}X`)
    return letras.slice(0, 3) + 'X'
  }
  return letras
}

/**
 * Tabla de valores para el cálculo de la homoclave
 * Asigna un valor numérico de 2 dígitos a cada carácter
 */
const TABLA_HOMOCLAVE: Record<string, string> = {
  ' ': '00', '0': '00', '1': '01', '2': '02', '3': '03', '4': '04',
  '5': '05', '6': '06', '7': '07', '8': '08', '9': '09', '&': '10',
  'A': '11', 'B': '12', 'C': '13', 'D': '14', 'E': '15', 'F': '16',
  'G': '17', 'H': '18', 'I': '19', 'J': '21', 'K': '22', 'L': '23',
  'M': '24', 'N': '25', 'O': '26', 'P': '27', 'Q': '28', 'R': '29',
  'S': '32', 'T': '33', 'U': '34', 'V': '35', 'W': '36', 'X': '37',
  'Y': '38', 'Z': '39', 'Ñ': '40'
}

/**
 * Tabla para convertir residuo a carácter de homoclave (base 34)
 * Mapeo: 0-8 → '1'-'9', 9-33 → 'A'-'Z' (excluyendo 'O')
 * Total: 34 caracteres = "123456789ABCDEFGHIJKLMNPQRSTUVWXYZ"
 */
const TABLA_DIGITO: Record<number, string> = {
  0: '1', 1: '2', 2: '3', 3: '4', 4: '5', 5: '6', 6: '7', 7: '8',
  8: '9', 9: 'A', 10: 'B', 11: 'C', 12: 'D', 13: 'E', 14: 'F',
  15: 'G', 16: 'H', 17: 'I', 18: 'J', 19: 'K', 20: 'L', 21: 'M',
  22: 'N', 23: 'P', 24: 'Q', 25: 'R', 26: 'S', 27: 'T', 28: 'U',
  29: 'V', 30: 'W', 31: 'X', 32: 'Y', 33: 'Z'
}

/**
 * Tabla para el dígito verificador
 */
const TABLA_VERIFICADOR: Record<number, string> = {
  0: '0', 1: '1', 2: '2', 3: '3', 4: '4', 5: '5', 6: '6', 7: '7',
  8: '8', 9: '9', 10: 'A'
}

/**
 * Calcula la homoclave del RFC usando el algoritmo oficial del SAT
 *
 * El algoritmo oficial (basado en documentación del IFAI):
 * 1. Construir nombre completo: "APELLIDO_PATERNO APELLIDO_MATERNO NOMBRE"
 * 2. Agregar un '0' al inicio del nombre
 * 3. Convertir cada carácter a su valor de 2 dígitos (usando TABLA_HOMOCLAVE)
 * 4. Para cada posición i, multiplicar el número de 2 dígitos (i, i+1) por el dígito en i+1
 * 5. Sumar todos los productos
 * 6. Tomar los últimos 3 dígitos (módulo 1000)
 * 7. Dividir entre 34: cociente → primer carácter, residuo → segundo carácter
 */
function calcularHomoclave(data: PersonaFisicaData): string {
  // Construir el nombre completo normalizado
  // El algoritmo del SAT usa el nombre completo tal como aparece (sin filtrar partículas)
  const apellidoPaterno = normalizarTexto(data.primerApellido)
  const apellidoMaterno = data.segundoApellido ? normalizarTexto(data.segundoApellido) : ''
  const nombre = normalizarTexto(data.nombre)

  // Formato: APELLIDO_PATERNO APELLIDO_MATERNO NOMBRE(S)
  let nombreCompleto = apellidoPaterno
  if (apellidoMaterno) {
    nombreCompleto += ' ' + apellidoMaterno
  }
  nombreCompleto += ' ' + nombre

  // Agregar un '0' al inicio (requerido por el algoritmo oficial)
  // El '0' tiene valor '00' en la tabla
  nombreCompleto = '0' + nombreCompleto

  log.debug('Nombre completo para homoclave:', { nombreCompleto })

  // Paso 1: Convertir cada carácter a su valor de 2 dígitos
  let valores = ''
  for (const char of nombreCompleto) {
    const valor = TABLA_HOMOCLAVE[char] || '00'
    valores += valor
  }

  log.debug('Valores numéricos:', { valores, length: valores.length })

  // Paso 2: Calcular la suma de productos
  // Para cada posición i, multiplicar el número de 2 dígitos (posiciones i, i+1)
  // por el dígito en la posición i+1
  let suma = 0
  for (let i = 0; i < valores.length - 1; i++) {
    // Tomar el número de 2 dígitos empezando en i
    const dosDigitos = parseInt(valores.substring(i, i + 2), 10)
    // Multiplicar por el dígito en posición i+1
    const digitoSiguiente = parseInt(valores.charAt(i + 1), 10)
    const producto = dosDigitos * digitoSiguiente
    suma += producto
  }

  log.debug('Suma de productos:', { suma })

  // Paso 3: Obtener los últimos 3 dígitos de la suma
  const ultimos3 = suma % 1000
  log.debug('Últimos 3 dígitos:', { ultimos3 })

  // Paso 4: Calcular los 2 caracteres de la homoclave
  // Primer carácter: cociente de dividir entre 34
  // Segundo carácter: residuo de dividir entre 34
  const cociente = Math.floor(ultimos3 / 34)
  const residuo = ultimos3 % 34

  const primerCaracter = TABLA_DIGITO[cociente] || '1'
  const segundoCaracter = TABLA_DIGITO[residuo] || '1'

  log.debug('Homoclave cálculo:', { cociente, primerCaracter, residuo, segundoCaracter })

  return primerCaracter + segundoCaracter
}

/**
 * Calcula el dígito verificador del RFC
 */
function calcularDigitoVerificador(rfcSinDigito: string): string {
  const tabla: Record<string, number> = {
    '0': 0, '1': 1, '2': 2, '3': 3, '4': 4, '5': 5, '6': 6, '7': 7,
    '8': 8, '9': 9, 'A': 10, 'B': 11, 'C': 12, 'D': 13, 'E': 14,
    'F': 15, 'G': 16, 'H': 17, 'I': 18, 'J': 19, 'K': 20, 'L': 21,
    'M': 22, 'N': 23, 'O': 25, 'P': 26, 'Q': 27, 'R': 28, 'S': 29,
    'T': 30, 'U': 31, 'V': 32, 'W': 33, 'X': 34, 'Y': 35, 'Z': 36,
    ' ': 37, 'Ñ': 24, '&': 38
  }

  // Agregar espacio al inicio para RFCs de 12 caracteres (persona física sin dígito)
  const rfcPadded = rfcSinDigito.length === 12 ? ' ' + rfcSinDigito : rfcSinDigito

  let suma = 0
  for (let i = 0; i < 12; i++) {
    const char = rfcPadded[i] ?? ' '
    const valor = tabla[char] ?? 0
    suma += valor * (13 - i)
  }

  const residuo = suma % 11
  const digito = residuo === 0 ? 0 : 11 - residuo

  return TABLA_VERIFICADOR[digito] || '0'
}

/**
 * Genera el RFC base (10 caracteres) para una persona física
 */
export function calcularRFCBase(data: PersonaFisicaData): string {
  // Validar datos
  if (!data.nombre || !data.primerApellido) {
    throw new Error('Se requiere nombre y primer apellido')
  }

  if (!data.dia || !data.mes || !data.anio) {
    throw new Error('Se requiere fecha de nacimiento completa')
  }

  if (data.dia < 1 || data.dia > 31) {
    throw new Error('Día inválido')
  }

  if (data.mes < 1 || data.mes > 12) {
    throw new Error('Mes inválido')
  }

  if (data.anio < 1900 || data.anio > new Date().getFullYear()) {
    throw new Error('Año inválido')
  }

  // Extraer las 4 letras iniciales
  const cuatroLetras = extraerCuatroLetras(data)
  log.debug('Cuatro letras extraídas:', { cuatroLetras })

  // Corregir palabras inconvenientes
  const letrasCorregidas = corregirPalabraInconveniente(cuatroLetras)

  // Formatear fecha
  const fecha = formatearFecha(data.dia, data.mes, data.anio)
  log.debug('Fecha formateada:', { fecha })

  // RFC base (10 caracteres)
  return letrasCorregidas + fecha
}

/**
 * Genera una sugerencia de RFC completo (13 caracteres)
 * Incluye homoclave calculada con el algoritmo oficial del SAT
 */
export function generarRFCSugerido(data: PersonaFisicaData): RfcResult {
  const rfcBase = calcularRFCBase(data)

  // Calcular homoclave (2 caracteres)
  const homoclave = calcularHomoclave(data)
  log.debug('Homoclave calculada:', { homoclave })

  // Calcular dígito verificador
  const rfcSinDigito = rfcBase + homoclave
  const digitoVerificador = calcularDigitoVerificador(rfcSinDigito)
  log.debug('Dígito verificador:', { digitoVerificador })

  const suggestedRfc = rfcBase + homoclave + digitoVerificador

  return {
    rfcBase,
    suggestedRfc,
    warning: 'Este RFC fue calculado con el algoritmo oficial del SAT. Se validará automáticamente con Nubarium.'
  }
}

/**
 * Parsea una fecha en varios formatos posibles
 * Soporta: YYYY-MM-DD, DD/MM/YYYY, DD-MM-YYYY
 */
function parseFechaNacimiento(fecha: string): { dia: number; mes: number; anio: number } | null {
  if (!fecha || typeof fecha !== 'string') {
    return null
  }

  // Formato YYYY-MM-DD (ISO)
  if (/^\d{4}-\d{2}-\d{2}$/.test(fecha)) {
    const [anio, mes, dia] = fecha.split('-').map(Number)
    if (anio && mes && dia) {
      return { dia, mes, anio }
    }
  }

  // Formato DD/MM/YYYY
  if (/^\d{2}\/\d{2}\/\d{4}$/.test(fecha)) {
    const [dia, mes, anio] = fecha.split('/').map(Number)
    if (anio && mes && dia) {
      return { dia, mes, anio }
    }
  }

  // Formato DD-MM-YYYY
  if (/^\d{2}-\d{2}-\d{4}$/.test(fecha)) {
    const [dia, mes, anio] = fecha.split('-').map(Number)
    if (anio && mes && dia) {
      return { dia, mes, anio }
    }
  }

  return null
}

/**
 * Genera un RFC sugerido a partir de los datos del KYC (INE)
 */
export function generarRFCDesdeKyc(
  nombres: string,
  apellidoPaterno: string,
  apellidoMaterno: string | null,
  fechaNacimiento: string // Formato: YYYY-MM-DD, DD/MM/YYYY, o DD-MM-YYYY
): RfcResult {
  const fechaParsed = parseFechaNacimiento(fechaNacimiento)

  if (!fechaParsed) {
    throw new Error(`Formato de fecha inválido: "${fechaNacimiento}". Se esperaba YYYY-MM-DD, DD/MM/YYYY o DD-MM-YYYY`)
  }

  return generarRFCSugerido({
    nombre: nombres,
    primerApellido: apellidoPaterno,
    segundoApellido: apellidoMaterno,
    dia: fechaParsed.dia,
    mes: fechaParsed.mes,
    anio: fechaParsed.anio
  })
}

/**
 * Valida el formato básico de un RFC (sin validar con el SAT)
 */
export function validarFormatoRFC(rfc: string): {
  valido: boolean
  tipo: 'PERSONA_FISICA' | 'PERSONA_MORAL' | 'INVALIDO'
  errores: string[]
} {
  const errores: string[] = []
  rfc = rfc.toUpperCase().trim()

  // Verificar longitud
  if (rfc.length !== 12 && rfc.length !== 13) {
    errores.push('El RFC debe tener 12 (persona moral) o 13 (persona física) caracteres')
    return { valido: false, tipo: 'INVALIDO', errores }
  }

  const tipo = rfc.length === 13 ? 'PERSONA_FISICA' : 'PERSONA_MORAL'

  if (tipo === 'PERSONA_FISICA') {
    // Formato: AAAA######XXX
    // 4 letras + 6 dígitos + 3 alfanuméricos
    const patron = /^[A-ZÑ&]{4}\d{6}[A-Z0-9]{3}$/
    if (!patron.test(rfc)) {
      errores.push('Formato inválido para RFC de persona física (debe ser: 4 letras + 6 dígitos + 3 caracteres)')
    }
  } else {
    // Formato: AAA######XXX
    // 3 letras + 6 dígitos + 3 alfanuméricos
    const patron = /^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/
    if (!patron.test(rfc)) {
      errores.push('Formato inválido para RFC de persona moral (debe ser: 3 letras + 6 dígitos + 3 caracteres)')
    }
  }

  return {
    valido: errores.length === 0,
    tipo,
    errores
  }
}

/**
 * Extrae información de un RFC (sin validar con el SAT)
 */
export function extraerInformacionRFC(rfc: string): {
  tipo: 'PERSONA_FISICA' | 'PERSONA_MORAL' | 'INVALIDO'
  fechaNacimiento?: Date
  iniciales?: string
  homoclave?: string
} {
  rfc = rfc.toUpperCase().trim()

  const { valido, tipo } = validarFormatoRFC(rfc)
  if (!valido) {
    return { tipo: 'INVALIDO' }
  }

  const longitudIniciales = tipo === 'PERSONA_FISICA' ? 4 : 3
  const iniciales = rfc.substring(0, longitudIniciales)
  const fechaStr = rfc.substring(longitudIniciales, longitudIniciales + 6)
  const homoclave = rfc.substring(longitudIniciales + 6)

  // Extraer fecha (AAMMDD)
  const anio = parseInt('20' + fechaStr.substring(0, 2)) // Asumimos siglo XXI
  const mes = parseInt(fechaStr.substring(2, 4))
  const dia = parseInt(fechaStr.substring(4, 6))

  let fechaNacimiento: Date | undefined
  try {
    // Ajustar siglo (si el año es > año actual, es del siglo XX)
    const anioCompleto = anio > new Date().getFullYear() ? anio - 100 : anio
    fechaNacimiento = new Date(anioCompleto, mes - 1, dia)
  } catch {
    // Fecha inválida
  }

  return {
    tipo,
    fechaNacimiento,
    iniciales,
    homoclave
  }
}
