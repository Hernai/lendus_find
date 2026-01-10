<script setup lang="ts">
import { useTenantStore } from '@/stores'
import { computed } from 'vue'

const tenantStore = useTenantStore()
const tenantName = computed(() => tenantStore.name || 'LendusFind')
const year = new Date().getFullYear()
</script>

<template>
  <footer class="text-gray-300" :style="{ backgroundColor: `rgb(var(--tenant-dark-rgb))` }">
    <!-- Accent bar with tenant primary color -->
    <div class="h-1 w-full" :style="{ background: `linear-gradient(to right, rgb(var(--primary-700-rgb)), rgb(var(--primary-500-rgb)), rgb(var(--primary-700-rgb)))` }"></div>

    <div class="max-w-7xl mx-auto px-4 py-12">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
        <!-- Brand -->
        <div class="col-span-1">
          <div class="flex items-center gap-2 mb-4">
            <div class="h-10 w-10 rounded-lg flex items-center justify-center" :style="{ backgroundColor: `rgb(var(--primary-600-rgb) / 0.2)` }">
              <svg class="w-6 h-6" :style="{ color: `rgb(var(--primary-400-rgb))` }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <span class="text-white font-bold">{{ tenantName }}</span>
          </div>
          <p class="text-sm text-gray-400">
            Tu crédito 100% digital, sin papeleos y en minutos.
          </p>
        </div>

        <!-- Links -->
        <div>
          <h4 class="text-white font-semibold mb-4">Productos</h4>
          <ul class="space-y-2 text-sm">
            <li><a href="#" class="hover:text-primary-400 transition-colors">Crédito Personal</a></li>
            <li><a href="#" class="hover:text-primary-400 transition-colors">Crédito de Nómina</a></li>
            <li><a href="#" class="hover:text-primary-400 transition-colors">Crédito PyME</a></li>
          </ul>
        </div>

        <div>
          <h4 class="text-white font-semibold mb-4">Legal</h4>
          <ul class="space-y-2 text-sm">
            <li><a href="#" class="hover:text-primary-400 transition-colors">Aviso de Privacidad</a></li>
            <li><a href="#" class="hover:text-primary-400 transition-colors">Términos y Condiciones</a></li>
            <li><a href="#" class="hover:text-primary-400 transition-colors">CAT Informativo</a></li>
          </ul>
        </div>

        <div>
          <h4 class="text-white font-semibold mb-4">Contacto</h4>
          <ul class="space-y-2 text-sm">
            <li>contacto@lendus.mx</li>
            <li>+52 (55) 1234-5678</li>
            <li>Lunes a Viernes 9:00 - 18:00</li>
          </ul>
        </div>
      </div>

      <!-- Bottom -->
      <div class="border-t border-gray-800 mt-8 pt-8 text-center text-sm text-gray-500">
        <p>&copy; {{ year }} {{ tenantName }}. Todos los derechos reservados.</p>
        <p class="mt-2">
          Entidad regulada por CNBV | CONDUSEF: 555-123-4567
        </p>
      </div>
    </div>
  </footer>
</template>
