import { describe, it, expect } from 'vitest'
import type { OnboardingStep } from '@/types/v2/onboardingStep'
import { legacyCanContinue, type StepValidationContext } from './stepValidation'

/**
 * ORÁCULO anti-regresión de la validación del onboarding dinámico.
 *
 * Congela el comportamiento de `legacyCanContinue` (la fuente de verdad original del
 * gate "Continuar"). Durante la migración al contrato `update:valid` (plan Fases 1-3),
 * cada renderer debe reproducir EXACTAMENTE la validez de su tipo aquí congelada.
 * Si un cambio altera este oráculo sin querer, el test falla.
 */

// `legacyCanContinue` solo lee `s.type`; el resto de campos del step no importan para
// la validación, así que un step mínimo (casteado) basta.
const step = (type: OnboardingStep['type']): OnboardingStep =>
  ({ id: `step-${type}`, type } as OnboardingStep)

const ctx = (over: Partial<StepValidationContext> = {}): StepValidationContext => ({
  hasKycProvider: false,
  ownPhone: '',
  ...over,
})

describe('legacyCanContinue — oráculo', () => {
  describe('review / review_full', () => {
    it('siempre permite continuar', () => {
      expect(legacyCanContinue(step('review'), null, ctx())).toBe(true)
      expect(legacyCanContinue(step('review_full'), undefined, ctx())).toBe(true)
    })
  })

  describe('state_city', () => {
    it('requiere state y city', () => {
      expect(legacyCanContinue(step('state_city'), { state: 'JAL', city: 'GDL' }, ctx())).toBe(true)
      expect(legacyCanContinue(step('state_city'), { state: 'JAL', city: '' }, ctx())).toBe(false)
      expect(legacyCanContinue(step('state_city'), { state: '', city: 'GDL' }, ctx())).toBe(false)
      expect(legacyCanContinue(step('state_city'), null, ctx())).toBe(false)
    })
  })

  describe('references', () => {
    const ok = [
      { name: 'Ana Pérez', phone: '5511111111' },
      { name: 'Luis Gómez', phone: '5522222222' },
    ]
    it('acepta 2 referencias válidas y distintas', () => {
      expect(legacyCanContinue(step('references'), ok, ctx())).toBe(true)
    })
    it('rechaza menos de 2', () => {
      expect(legacyCanContinue(step('references'), [ok[0]], ctx())).toBe(false)
      expect(legacyCanContinue(step('references'), null, ctx())).toBe(false)
    })
    it('rechaza nombre de una sola palabra o teléfono != 10 dígitos', () => {
      expect(legacyCanContinue(step('references'), [{ name: 'Ana', phone: '5511111111' }, ok[1]], ctx())).toBe(false)
      expect(legacyCanContinue(step('references'), [{ name: 'Ana Pérez', phone: '551111' }, ok[1]], ctx())).toBe(false)
    })
    it('rechaza teléfonos o nombres duplicados entre referencias', () => {
      expect(legacyCanContinue(step('references'), [ok[0], { name: 'Luis Gómez', phone: '5511111111' }], ctx())).toBe(false)
      expect(legacyCanContinue(step('references'), [ok[0], { name: 'Ana Pérez', phone: '5522222222' }], ctx())).toBe(false)
    })
    it('rechaza si una referencia es el teléfono propio', () => {
      expect(legacyCanContinue(step('references'), ok, ctx({ ownPhone: '5511111111' }))).toBe(false)
    })
  })

  describe('bank_account', () => {
    it('CLABE = 18 dígitos, CARD = 16 dígitos', () => {
      expect(legacyCanContinue(step('bank_account'), { type: 'CLABE', bank_code: '002', account_number: '0'.repeat(18) }, ctx())).toBe(true)
      expect(legacyCanContinue(step('bank_account'), { type: 'CLABE', bank_code: '002', account_number: '0'.repeat(16) }, ctx())).toBe(false)
      expect(legacyCanContinue(step('bank_account'), { type: 'CARD', bank_code: '002', account_number: '0'.repeat(16) }, ctx())).toBe(true)
      expect(legacyCanContinue(step('bank_account'), { type: 'CARD', bank_code: '002', account_number: '0'.repeat(18) }, ctx())).toBe(false)
    })
    it('rechaza sin bank_code o account_number', () => {
      expect(legacyCanContinue(step('bank_account'), { bank_code: '', account_number: '0'.repeat(18) }, ctx())).toBe(false)
      expect(legacyCanContinue(step('bank_account'), null, ctx())).toBe(false)
    })
  })

  describe('kyc_ine', () => {
    const imgs = { front_image: 'data:img', back_image: 'data:img' }
    const validPersonal = { curp: 'PERA900101HDFRNN09', clave_elector: 'PRRLNA90010109H800', numero_ocr: '1234567890123' }
    it('con proveedor KYC: basta front + back', () => {
      expect(legacyCanContinue(step('kyc_ine'), imgs, ctx({ hasKycProvider: true }))).toBe(true)
    })
    it('sin proveedor: exige CURP + clave + OCR válidos', () => {
      expect(legacyCanContinue(step('kyc_ine'), { ...imgs, personal: validPersonal }, ctx({ hasKycProvider: false }))).toBe(true)
      expect(legacyCanContinue(step('kyc_ine'), { ...imgs, personal: { ...validPersonal, curp: 'MAL' } }, ctx({ hasKycProvider: false }))).toBe(false)
      expect(legacyCanContinue(step('kyc_ine'), imgs, ctx({ hasKycProvider: false }))).toBe(false)
    })
    it('siempre exige ambas imágenes', () => {
      expect(legacyCanContinue(step('kyc_ine'), { front_image: 'x' }, ctx({ hasKycProvider: true }))).toBe(false)
    })
  })

  describe('personal_data', () => {
    const base = { first_name: 'Ana', last_name: 'Pérez', birth_date: '1990-01-01', gender: 'F', is_mexican: 'SI', birth_state: 'JAL', rfc: 'PEPA900101AB1' }
    it('acepta datos completos y válidos', () => {
      expect(legacyCanContinue(step('personal_data'), base, ctx())).toBe(true)
    })
    it('rechaza nombre/apellido corto, fecha != 10, género inválido', () => {
      expect(legacyCanContinue(step('personal_data'), { ...base, first_name: 'A' }, ctx())).toBe(false)
      expect(legacyCanContinue(step('personal_data'), { ...base, birth_date: '90-01-01' }, ctx())).toBe(false)
      expect(legacyCanContinue(step('personal_data'), { ...base, gender: 'X' }, ctx())).toBe(false)
    })
    it('mexicano exige birth_state; RFC debe cumplir regex', () => {
      expect(legacyCanContinue(step('personal_data'), { ...base, is_mexican: 'SI', birth_state: '' }, ctx())).toBe(false)
      expect(legacyCanContinue(step('personal_data'), { ...base, is_mexican: 'NO', birth_state: '' }, ctx())).toBe(true)
      expect(legacyCanContinue(step('personal_data'), { ...base, rfc: 'MALO' }, ctx())).toBe(false)
    })
  })

  describe('address', () => {
    const base = { postal_code: '44100', state: 'JAL', municipality: 'GDL', neighborhood: 'Centro', street: 'Juárez', ext_number: '100', housing_type: 'PROPIA', years_at_address: 2, months_at_address: 0 }
    it('acepta domicilio completo', () => {
      expect(legacyCanContinue(step('address'), base, ctx())).toBe(true)
    })
    it('rechaza CP != 5 dígitos o campos faltantes', () => {
      expect(legacyCanContinue(step('address'), { ...base, postal_code: '441' }, ctx())).toBe(false)
      expect(legacyCanContinue(step('address'), { ...base, street: '' }, ctx())).toBe(false)
    })
    it('exige antigüedad > 0 (años o meses)', () => {
      expect(legacyCanContinue(step('address'), { ...base, years_at_address: 0, months_at_address: 0 }, ctx())).toBe(false)
      expect(legacyCanContinue(step('address'), { ...base, years_at_address: 0, months_at_address: 6 }, ctx())).toBe(true)
    })
  })

  describe('kyc_selfie', () => {
    it('exige string no vacío', () => {
      expect(legacyCanContinue(step('kyc_selfie'), 'data:img', ctx())).toBe(true)
      expect(legacyCanContinue(step('kyc_selfie'), '', ctx())).toBe(false)
      expect(legacyCanContinue(step('kyc_selfie'), null, ctx())).toBe(false)
    })
  })

  describe('default (select / number_select)', () => {
    it('exige valor no vacío', () => {
      expect(legacyCanContinue(step('select'), 'PREPARATORIA', ctx())).toBe(true)
      expect(legacyCanContinue(step('number_select'), 0, ctx())).toBe(true)
      expect(legacyCanContinue(step('select'), '', ctx())).toBe(false)
      expect(legacyCanContinue(step('select'), null, ctx())).toBe(false)
    })
  })
})
