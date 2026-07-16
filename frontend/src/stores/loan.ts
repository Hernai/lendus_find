/**
 * Loan store (módulo opt-in MoneyCapital).
 */

import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { v2 } from '@/services/v2'
import type { V2Loan, V2LoanExtensionQuote } from '@/types/v2/loan'

export const useLoanStore = defineStore('loan', () => {
  const loans = ref<V2Loan[]>([])
  const current = ref<V2Loan | null>(null)
  const isLoading = ref(false)
  const isSubmitting = ref(false)

  // Destaca el crédito dispersado (con acción de pago) antes que el autorizado
  // esperando dispersión: si hay uno ACTIVE/DISBURSED se muestra ese, y solo a
  // falta de él se destaca el PENDING_DISBURSEMENT.
  const activeLoan = computed(
    () =>
      loans.value.find((l) => l.status === 'ACTIVE' || l.status === 'DISBURSED') ??
      loans.value.find((l) => l.status === 'PENDING_DISBURSEMENT') ??
      null,
  )

  // Puntos de recompensa del cliente: suma de todos sus créditos (el card de
  // recompensas es a nivel cliente, no del crédito destacado).
  const rewardPoints = computed(() =>
    loans.value.reduce((sum, l) => sum + (l.reward_points ?? 0), 0),
  )

  const completedLoans = computed(() => loans.value.filter((l) => l.status === 'COMPLETED'))

  async function fetchAll(status?: string) {
    isLoading.value = true
    try {
      const res = await v2.applicant.loan.list(status ? { status } : undefined)
      loans.value = res.data?.loans ?? []
    } finally {
      isLoading.value = false
    }
  }

  async function fetchOne(id: string) {
    isLoading.value = true
    try {
      const res = await v2.applicant.loan.get(id)
      current.value = res.data?.loan ?? null
      if (current.value) {
        const idx = loans.value.findIndex((l) => l.id === id)
        if (idx >= 0) loans.value.splice(idx, 1, current.value)
        else loans.value.unshift(current.value)
      }
      return current.value
    } finally {
      isLoading.value = false
    }
  }

  async function quoteExtension(id: string, days: number): Promise<V2LoanExtensionQuote | null> {
    const res = await v2.applicant.loan.quoteExtension(id, days)
    return res.data ?? null
  }

  async function requestExtension(id: string, days: number) {
    isSubmitting.value = true
    try {
      const res = await v2.applicant.loan.requestExtension(id, days)
      await fetchOne(id)
      return res.data
    } finally {
      isSubmitting.value = false
    }
  }

  async function pay(id: string, amount: number, channel?: string) {
    isSubmitting.value = true
    try {
      const res = await v2.applicant.loan.pay(id, { amount, channel })
      await fetchOne(id)
      return res.data
    } finally {
      isSubmitting.value = false
    }
  }

  return {
    loans,
    current,
    isLoading,
    isSubmitting,
    activeLoan,
    rewardPoints,
    completedLoans,
    fetchAll,
    fetchOne,
    quoteExtension,
    requestExtension,
    pay,
  }
})
