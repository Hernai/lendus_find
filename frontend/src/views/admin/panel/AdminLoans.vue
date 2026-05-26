<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { v2 } from '@/services/v2'
import type { V2Loan } from '@/types/v2/loan'

const router = useRouter()
const loans = ref<V2Loan[]>([])
const loading = ref(false)
const statusFilter = ref<string>('')

const formatMoney = (n: number) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(n)
const formatDate = (d: string | null) => d ? new Date(d).toLocaleDateString('es-MX') : '—'

const fetchLoans = async () => {
  loading.value = true
  try {
    const res = await v2.staff.loan.list(statusFilter.value ? { status: statusFilter.value } : undefined)
    loans.value = res.data?.loans ?? []
  } finally {
    loading.value = false
  }
}

const total = computed(() => loans.value.reduce((sum, l) => sum + Number(l.principal_amount), 0))
const totalOutstanding = computed(() => loans.value.reduce((sum, l) => sum + Number(l.outstanding_balance), 0))

onMounted(fetchLoans)
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Préstamos</h1>
        <p class="text-sm text-gray-500">Cartera de préstamos activos del tenant.</p>
      </div>
      <select
        v-model="statusFilter"
        class="px-3 py-2 border border-gray-200 rounded-lg text-sm"
        @change="fetchLoans"
      >
        <option value="">Todos</option>
        <option value="DISBURSED">Dispersados</option>
        <option value="ACTIVE">Activos</option>
        <option value="COMPLETED">Liquidados</option>
        <option value="DEFAULT">En mora</option>
      </select>
    </div>

    <div class="grid grid-cols-3 gap-3">
      <div class="bg-white p-4 rounded-xl border border-gray-100">
        <p class="text-xs text-gray-500">Préstamos</p>
        <p class="text-xl font-bold">{{ loans.length }}</p>
      </div>
      <div class="bg-white p-4 rounded-xl border border-gray-100">
        <p class="text-xs text-gray-500">Principal colocado</p>
        <p class="text-xl font-bold">{{ formatMoney(total) }}</p>
      </div>
      <div class="bg-white p-4 rounded-xl border border-gray-100">
        <p class="text-xs text-gray-500">Saldo pendiente</p>
        <p class="text-xl font-bold text-primary-700">{{ formatMoney(totalOutstanding) }}</p>
      </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
      <div v-if="loading" class="p-10 text-center">
        <div class="inline-block w-8 h-8 border-2 border-primary-600 border-t-transparent rounded-full animate-spin" />
      </div>
      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
          <tr>
            <th class="px-4 py-3">ID</th>
            <th class="px-4 py-3">Principal</th>
            <th class="px-4 py-3">Plazo</th>
            <th class="px-4 py-3">Saldo</th>
            <th class="px-4 py-3">Vence</th>
            <th class="px-4 py-3">Estado</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="l in loans" :key="l.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-xs">{{ l.id.slice(0, 8) }}</td>
            <td class="px-4 py-3 font-medium">{{ formatMoney(l.principal_amount) }}</td>
            <td class="px-4 py-3">{{ l.term_days }}d</td>
            <td class="px-4 py-3 text-primary-700 font-medium">{{ formatMoney(l.outstanding_balance) }}</td>
            <td class="px-4 py-3">{{ formatDate(l.due_date) }}</td>
            <td class="px-4 py-3">
              <span
                class="text-xs px-2 py-0.5 rounded-full font-medium"
                :class="{
                  'bg-green-100 text-green-700': l.status === 'ACTIVE' || l.status === 'DISBURSED',
                  'bg-gray-100 text-gray-700': l.status === 'COMPLETED',
                  'bg-red-100 text-red-700': l.status === 'DEFAULT',
                  'bg-amber-100 text-amber-700': l.status === 'RESTRUCTURED',
                }"
              >{{ l.status }}</span>
            </td>
            <td class="px-4 py-3 text-right">
              <button
                type="button"
                class="text-primary-600 font-medium text-sm"
                @click="router.push({ name: 'admin-loan-detail', params: { id: l.id } })"
              >Ver</button>
            </td>
          </tr>
          <tr v-if="loans.length === 0">
            <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">
              Aún no hay préstamos en esta cartera.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
