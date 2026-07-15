/**
 * V2 Staff Decision Policy Service
 *
 * Configurador del motor de decisión: políticas versionadas por tenant/producto,
 * activación/rollback, modos off/shadow/active y probador (dry-run).
 * All endpoints are under /api/v2/staff/decision-policies
 */

import { api } from '@/services/api'
import type { V2ApiResponse } from '@/types/v2'

const BASE_PATH = '/v2/staff/decision-policies'

// =====================================================
// Types
// =====================================================

export type DecisionMode = 'OFF' | 'SHADOW' | 'ACTIVE'
export type DecisionOutcome = 'OFFER' | 'REVIEW' | 'REJECT' | 'NO_OFFER' | 'ALLOW' | 'FLAG' | 'BLOCK'

/** Reglas de la política de TENANT (product_id null): filtros de entrada. */
export interface TenantPolicyRules {
  phone_risk_gate?: {
    enabled: boolean
    flag_from: number
    block_from: number
    fail_mode: 'open'
  }
  cooldown?: { days: number }
}

export interface ScoringVariable {
  key: string
  label?: string
  points: Record<string, number>
}

export interface BandCutoff {
  min_score: number
  band: string
}

export interface OfferBand {
  key: string
  min_amount: number
  max_amount: number
  target_share_pct?: number
}

export interface GraduationLevel {
  level: number
  max_amount: number
  max_term_days: number
}

/** Reglas de la política de PRODUCTO: scoring, bandas, oferta y graduación. */
export interface ProductPolicyRules {
  scoring?: {
    variables: ScoringVariable[]
    band_cutoffs: BandCutoff[]
  }
  bands?: OfferBand[]
  first_credit?: { term_days: number }
  offer?: { validity_hours: number; reminder_hours_before?: number }
  review?: { input_timeout_minutes: number }
  reject?: Array<{ rule: string; states?: string[] }>
  graduation?: {
    levels: GraduationLevel[]
    advance: {
      on_time: number
      late_or_extension: number
      max_late_days_for_auto: number
    }
  }
}

export type DecisionPolicyRules = TenantPolicyRules & ProductPolicyRules

export interface V2DecisionPolicy {
  id: string
  product_id: string | null
  version: number
  mode: DecisionMode
  mode_label: string
  is_active: boolean
  rules: DecisionPolicyRules
  notes: string | null
  activated_at: string | null
  activated_by: string | null
  created_at: string | null
  product?: { id: string; name: string; code: string } | null
}

export interface V2DryRunProfile {
  requested_amount?: number
  requested_term_days?: number
  variables?: Record<string, string | number | null>
  kyc_status?: string
  identity_mismatch?: boolean
  phone_risk_score?: number | null
  phone_risk_level?: string | null
  renewal_history?: Array<{ on_time: boolean; used_extension: boolean; late_days: number }>
}

export interface V2DryRunResult {
  policy_version: number
  result: {
    outcome: DecisionOutcome
    score: number | null
    band: string | null
    range: {
      min_amount: number
      max_amount: number
      amount: number
      term_days: number
      min_term_days: number
      max_term_days: number
    } | null
    rule_hits: Array<{ rule: string; effect: string; detail?: Record<string, unknown> }>
    reasons: string[]
  }
}

// =====================================================
// API Functions
// =====================================================

export async function list(): Promise<V2ApiResponse<{ policies: V2DecisionPolicy[] }>> {
  const response = await api.get<V2ApiResponse<{ policies: V2DecisionPolicy[] }>>(BASE_PATH)
  return response.data
}

export async function createDraft(payload: {
  product_id?: string | null
  rules: DecisionPolicyRules
  notes?: string
  mode?: DecisionMode
}): Promise<V2ApiResponse<V2DecisionPolicy>> {
  const response = await api.post<V2ApiResponse<V2DecisionPolicy>>(BASE_PATH, payload)
  return response.data
}

export async function updateDraft(id: string, payload: {
  rules: DecisionPolicyRules
  notes?: string
  mode?: DecisionMode
}): Promise<V2ApiResponse<V2DecisionPolicy>> {
  const response = await api.put<V2ApiResponse<V2DecisionPolicy>>(`${BASE_PATH}/${id}`, payload)
  return response.data
}

export async function activate(id: string, mode?: DecisionMode): Promise<V2ApiResponse<V2DecisionPolicy>> {
  const response = await api.post<V2ApiResponse<V2DecisionPolicy>>(
    `${BASE_PATH}/${id}/activate`,
    mode ? { mode } : {}
  )
  return response.data
}

export async function dryRun(policyId: string, profile: V2DryRunProfile): Promise<V2ApiResponse<V2DryRunResult>> {
  const response = await api.post<V2ApiResponse<V2DryRunResult>>(`${BASE_PATH}/dry-run`, {
    policy_id: policyId,
    profile,
  })
  return response.data
}

export default { list, createDraft, updateDraft, activate, dryRun }
