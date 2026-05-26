<script setup lang="ts">
import { ref, watch } from 'vue'
import { useLoanStore } from '@/stores'
import { useToast } from '@/composables/useToast'
import type { V2LoanExtensionQuote } from '@/types/v2/loan'

const props = defineProps<{
  open: boolean
  loanId: string
}>()

const emit = defineEmits<{ close: []; success: [] }>()

const loanStore = useLoanStore()
const toast = useToast()

const days = ref<7 | 15>(7)
const quote = ref<V2LoanExtensionQuote | null>(null)
const loadingQuote = ref(false)
const submitting = ref(false)

const formatMoney = (n: number) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(n)
const formatDate = (d: string) => new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'long', year: 'numeric' })

const loadQuote = async () => {
  loadingQuote.value = true
  try {
    quote.value = await loanStore.quoteExtension(props.loanId, days.value)
  } finally {
    loadingQuote.value = false
  }
}

watch(() => [props.open, days.value], ([open]) => {
  if (open) loadQuote()
})

const submit = async () => {
  submitting.value = true
  try {
    await loanStore.requestExtension(props.loanId, days.value)
    toast.success('Prórroga solicitada')
    emit('success')
    emit('close')
  } catch {
    toast.error('No fue posible solicitar la prórroga')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="open" class="fixed inset-0 z-50 bg-black/60 flex items-end justify-center" @click.self="emit('close')">
        <div class="bg-white w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl p-5 space-y-4 shadow-xl">
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Solicitar prórroga</h3>
            <button type="button" class="text-gray-400 hover:text-gray-600" @click="emit('close')">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <button
              type="button"
              class="py-4 rounded-xl border-2 font-semibold transition-colors"
              :class="days === 7 ? 'border-primary-600 bg-primary-50 text-primary-700' : 'border-gray-200 text-gray-600'"
              @click="days = 7"
            >
              7 días
            </button>
            <button
              type="button"
              class="py-4 rounded-xl border-2 font-semibold transition-colors"
              :class="days === 15 ? 'border-primary-600 bg-primary-50 text-primary-700' : 'border-gray-200 text-gray-600'"
              @click="days = 15"
            >
              15 días
            </button>
          </div>

          <div class="bg-gray-50 rounded-xl p-4 text-sm space-y-1">
            <div v-if="loadingQuote" class="flex items-center justify-center py-4">
              <div class="w-6 h-6 border-2 border-primary-600 border-t-transparent rounded-full animate-spin" />
            </div>
            <template v-else-if="quote">
              <div class="flex justify-between">
                <span class="text-gray-500">Costo de prórroga</span>
                <span class="font-semibold text-gray-900">{{ formatMoney(quote.fee) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-gray-500">Nueva fecha de vencimiento</span>
                <span class="font-semibold text-gray-900">{{ formatDate(quote.new_due_date) }}</span>
              </div>
            </template>
          </div>

          <button
            type="button"
            class="w-full py-3 bg-primary-600 text-white rounded-xl font-semibold disabled:opacity-50"
            :disabled="submitting || loadingQuote || !quote"
            @click="submit"
          >
            <span v-if="submitting">Procesando...</span>
            <span v-else>Confirmar prórroga</span>
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
