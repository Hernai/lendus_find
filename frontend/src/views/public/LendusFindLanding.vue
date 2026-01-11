<script setup lang="ts">
import { ref, onMounted } from 'vue'

// LendusFind Landing Page - Platform showcase for financial institutions

// Animation state
const isVisible = ref(false)
const heroVisible = ref(false)

onMounted(() => {
  // Set favicon for LendusFind
  const favicon = document.querySelector<HTMLLinkElement>("link[rel~='icon']")
  if (favicon) {
    favicon.href = 'https://lendus.com.mx/img/logos/find-icon.png'
  }

  // Set page title
  document.title = 'LendusFind - Plataforma de Originación de Crédito'

  // Trigger hero animation after mount
  setTimeout(() => {
    heroVisible.value = true
  }, 100)

  // Trigger other animations
  setTimeout(() => {
    isVisible.value = true
  }, 300)

  // Intersection Observer for scroll animations
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('animate-in')
        }
      })
    },
    { threshold: 0.1 }
  )

  // Observe all animated sections
  document.querySelectorAll('.animate-on-scroll').forEach((el) => {
    observer.observe(el)
  })
})

const features = [
  {
    icon: 'device',
    title: '100% Responsive',
    description: 'Diseño adaptable para cualquier dispositivo. Tu plataforma funciona perfectamente en web y móvil.'
  },
  {
    icon: 'shield',
    title: 'Seguridad Bancaria',
    description: 'Encriptación de extremo a extremo y cumplimiento con estándares de seguridad financiera mexicana.'
  },
  {
    icon: 'clock',
    title: 'Originación Rápida',
    description: 'Pre-aprobaciones automatizadas en minutos. Reduce tiempos de originación hasta un 80%.'
  },
  {
    icon: 'palette',
    title: 'White-Label',
    description: 'Personaliza colores, logos y dominio. Tu marca, tu experiencia, nuestra tecnología.'
  },
  {
    icon: 'integration',
    title: 'Integraciones',
    description: 'Webhooks y APIs para conectar con tu core bancario, SAP, y sistemas existentes.'
  },
  {
    icon: 'chart',
    title: 'Analytics',
    description: 'Dashboard en tiempo real con métricas de conversión, originación y seguimiento.'
  }
]

const stats = [
  { metric: '80%', label: 'Menos tiempo en originación' },
  { metric: '100%', label: 'Digital y sin papel' },
  { metric: '24/7', label: 'Disponibilidad' },
  { metric: '3x', label: 'Más solicitudes procesadas' }
]

const companyBenefits = [
  'Maneja tu propia marca con white-label completo',
  'Alcance de clientes a nivel nacional',
  'Revolvencia rápida y optimización de procesos',
  'Dashboard de métricas en tiempo real'
]

const operationalBenefits = [
  'Revolvencia rápida y segura',
  'Cumplimiento con actividades no presenciales',
  'Pagos referenciados (Oxxo y tiendas afiliadas)',
  'Integración con tu core bancario existente'
]

const customerBenefits = [
  { title: 'Web/móvil 100% responsivo', description: 'Accede desde cualquier dispositivo con una experiencia fluida y optimizada.' },
  { title: 'Simple', description: 'Un proceso intuitivo diseñado para completar la solicitud en pocos pasos.' },
  { title: 'Cómodo', description: 'Realiza tu onboarding desde donde estés, sin sucursales ni papeleo.' },
  { title: 'Seguro', description: 'Cifrado avanzado y cumplimiento de normativas de seguridad.' },
  { title: 'Rápido', description: 'Aprobaciones ágiles y procesos automatizados.' }
]

const openWhatsApp = () => {
  window.open('https://wa.me/525544159403?text=Hola,%20me%20interesa%20conocer%20más%20sobre%20LendusFind', '_blank')
}

const openEmail = () => {
  window.location.href = 'mailto:consultoria@lendus.com.mx?subject=Información%20sobre%20LendusFind'
}

const goToLendus = () => {
  window.open('https://lendus.com.mx', '_blank')
}
</script>

<template>
  <div class="min-h-screen flex flex-col bg-white">
    <!-- Header -->
    <header class="bg-white border-b border-gray-100 sticky top-0 z-50">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
          <div class="flex items-center gap-8">
            <img
              src="https://lendus.com.mx/img/logos/find.png"
              alt="LendusFind"
              class="h-8"
            >
            <nav class="hidden md:flex items-center gap-6">
              <a href="#benefits" class="text-gray-600 hover:text-[#7209b7] font-medium transition-colors">Beneficios</a>
              <a href="#features" class="text-gray-600 hover:text-[#7209b7] font-medium transition-colors">Características</a>
              <a href="#process" class="text-gray-600 hover:text-[#7209b7] font-medium transition-colors">Proceso</a>
            </nav>
          </div>
          <div class="flex items-center gap-3">
            <button
              class="hidden sm:block px-4 py-2 text-[#7209b7] font-medium hover:bg-purple-50 rounded-lg transition-colors"
              @click="goToLendus"
            >
              Ir a Lendus
            </button>
            <button
              class="px-4 py-2 bg-[#7209b7] text-white rounded-lg font-medium hover:bg-[#480ca8] transition-colors"
              @click="openWhatsApp"
            >
              Contactar
            </button>
          </div>
        </div>
      </div>
    </header>

    <main class="flex-1">
      <!-- Hero Section -->
      <section class="bg-gradient-to-br from-[#7209b7] via-[#480ca8] to-[#7209b7] py-20 md:py-28 relative overflow-hidden">
        <!-- Animated background decoration -->
        <div class="absolute inset-0 opacity-10">
          <div class="absolute top-0 left-0 w-96 h-96 bg-white rounded-full blur-3xl -translate-x-1/2 -translate-y-1/2 animate-blob"></div>
          <div class="absolute bottom-0 right-0 w-96 h-96 bg-white rounded-full blur-3xl translate-x-1/2 translate-y-1/2 animate-blob animation-delay-2000"></div>
          <div class="absolute top-1/2 left-1/2 w-72 h-72 bg-purple-300 rounded-full blur-3xl -translate-x-1/2 -translate-y-1/2 animate-blob animation-delay-4000"></div>
        </div>

        <!-- Floating particles -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
          <div class="particle particle-1"></div>
          <div class="particle particle-2"></div>
          <div class="particle particle-3"></div>
          <div class="particle particle-4"></div>
          <div class="particle particle-5"></div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div
              class="text-center lg:text-left transition-all duration-1000"
              :class="heroVisible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-10'"
            >
              <span class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-medium bg-white/20 text-white mb-6 backdrop-blur-sm animate-pulse-slow">
                Plataforma SaaS para SOFOMES y Fintech
              </span>
              <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold text-white mb-6 leading-tight">
                Digitaliza tu
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-purple-200 to-white animate-gradient">originación de crédito</span>
              </h1>
              <p class="text-xl text-purple-100 mb-8 max-w-xl mx-auto lg:mx-0">
                LendusFind es la plataforma white-label que te permite captar clientes, validar identidad (KYC) y originar créditos de forma 100% digital.
              </p>
              <div class="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start">
                <button
                  class="px-8 py-4 bg-white text-[#7209b7] rounded-xl font-semibold text-lg hover:bg-purple-50 transition-all shadow-lg shadow-black/20 hover:shadow-xl hover:scale-105 active:scale-95"
                  @click="openWhatsApp"
                >
                  Solicitar Demo
                </button>
                <button
                  class="px-8 py-4 border-2 border-white/50 text-white rounded-xl font-semibold text-lg hover:bg-white/10 transition-all backdrop-blur-sm hover:scale-105 active:scale-95 hover:border-white"
                  @click="openEmail"
                >
                  Más Información
                </button>
              </div>
            </div>

            <!-- Hero illustration with animation -->
            <div
              class="hidden lg:block transition-all duration-1000 delay-300"
              :class="heroVisible ? 'opacity-100 translate-x-0' : 'opacity-0 translate-x-10'"
            >
              <div class="bg-white/10 backdrop-blur-lg rounded-2xl p-6 border border-white/20 shadow-2xl hover:shadow-purple-500/20 transition-shadow duration-500 animate-float">
                <div class="bg-white rounded-xl p-5 mb-4">
                  <div class="flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 bg-[#7209b7] rounded-full flex items-center justify-center">
                      <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                      </svg>
                    </div>
                    <div>
                      <p class="font-semibold text-gray-900">Nueva Solicitud</p>
                      <p class="text-sm text-gray-500">Crédito Personal - $150,000</p>
                    </div>
                    <span class="ml-auto px-3 py-1 bg-green-100 text-green-700 text-sm font-medium rounded-full">Pre-aprobado</span>
                  </div>
                  <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                      <span class="text-gray-500">Progreso</span>
                      <span class="font-medium text-[#7209b7]">85%</span>
                    </div>
                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                      <div class="h-full bg-[#7209b7] rounded-full" style="width: 85%"></div>
                    </div>
                  </div>
                </div>
                <div class="grid grid-cols-3 gap-3">
                  <div class="bg-white/80 backdrop-blur rounded-lg p-3 text-center">
                    <p class="text-2xl font-bold text-[#7209b7]">85%</p>
                    <p class="text-xs text-gray-600">Aprobación</p>
                  </div>
                  <div class="bg-white/80 backdrop-blur rounded-lg p-3 text-center">
                    <p class="text-2xl font-bold text-[#7209b7]">3min</p>
                    <p class="text-xs text-gray-600">Pre-score</p>
                  </div>
                  <div class="bg-white/80 backdrop-blur rounded-lg p-3 text-center">
                    <p class="text-2xl font-bold text-[#7209b7]">24h</p>
                    <p class="text-xs text-gray-600">Respuesta</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Stats Section -->
      <section class="py-12 bg-gray-50 border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
            <div
              v-for="(stat, index) in stats"
              :key="stat.label"
              class="text-center animate-on-scroll"
              :style="{ transitionDelay: `${index * 0.1}s` }"
            >
              <p class="text-3xl md:text-4xl font-extrabold text-[#7209b7] mb-1 counter-animate">{{ stat.metric }}</p>
              <p class="text-gray-600 text-sm">{{ stat.label }}</p>
            </div>
          </div>
        </div>
      </section>

      <!-- Benefits Section -->
      <section id="benefits" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="text-center mb-16">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-[#7209b7] mb-4">
              Beneficios
            </span>
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
              <span class="text-[#7209b7]">Créditos fáciles</span> y rápidos
            </h2>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">
              Beneficios tanto para tu empresa como para tus clientes finales.
            </p>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-16">
            <!-- Beneficios para tu empresa -->
            <div class="bg-gray-50 rounded-2xl p-8 hover:shadow-lg transition-all hover:-translate-y-1 animate-on-scroll">
              <div class="w-14 h-14 bg-[#7209b7] rounded-xl flex items-center justify-center mb-6">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
              </div>
              <h3 class="text-2xl font-bold text-gray-900 mb-6">Beneficios para tu empresa</h3>
              <ul class="space-y-4">
                <li
                  v-for="(benefit, index) in companyBenefits"
                  :key="benefit"
                  class="flex items-start gap-3 benefit-item"
                  :style="{ animationDelay: `${index * 0.1}s` }"
                >
                  <svg class="w-6 h-6 text-[#7209b7] flex-shrink-0 mt-0.5 check-icon" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                  </svg>
                  <span class="text-gray-700">{{ benefit }}</span>
                </li>
              </ul>
            </div>

            <!-- Beneficios operacionales -->
            <div class="bg-gray-50 rounded-2xl p-8 hover:shadow-lg transition-all hover:-translate-y-1 animate-on-scroll" style="transition-delay: 0.15s">
              <div class="w-14 h-14 bg-[#7209b7] rounded-xl flex items-center justify-center mb-6">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
              </div>
              <h3 class="text-2xl font-bold text-gray-900 mb-6">Beneficios operacionales</h3>
              <ul class="space-y-4">
                <li
                  v-for="(benefit, index) in operationalBenefits"
                  :key="benefit"
                  class="flex items-start gap-3 benefit-item"
                  :style="{ animationDelay: `${index * 0.1}s` }"
                >
                  <svg class="w-6 h-6 text-[#7209b7] flex-shrink-0 mt-0.5 check-icon" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                  </svg>
                  <span class="text-gray-700">{{ benefit }}</span>
                </li>
              </ul>
            </div>
          </div>

          <!-- Beneficios para clientes -->
          <div class="bg-gradient-to-br from-[#7209b7] to-[#480ca8] rounded-2xl p-8 md:p-12 animate-on-scroll" style="transition-delay: 0.3s">
            <h3 class="text-2xl font-bold text-white mb-8 text-center">Beneficios para tus clientes</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
              <div
                v-for="(benefit, index) in customerBenefits"
                :key="benefit.title"
                class="bg-white/10 backdrop-blur rounded-xl p-4 hover:bg-white/20 transition-all hover:scale-105 hover:-translate-y-1"
                :style="{ animationDelay: `${index * 0.1}s` }"
              >
                <h4 class="font-semibold text-white mb-2">{{ benefit.title }}</h4>
                <p class="text-purple-100 text-sm">{{ benefit.description }}</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Features Section -->
      <section id="features" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="text-center mb-16">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-[#7209b7] mb-4">
              Características
            </span>
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
              Todo lo que necesitas para originar créditos
            </h2>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">
              Una plataforma completa que se adapta a tu operación y te permite escalar sin complicaciones técnicas.
            </p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <div
              v-for="(feature, index) in features"
              :key="feature.title"
              class="p-6 bg-white rounded-2xl border border-gray-100 hover:border-[#7209b7]/30 hover:shadow-lg transition-all group animate-on-scroll hover:-translate-y-1"
              :style="{ transitionDelay: `${index * 0.1}s` }"
            >
              <div class="w-14 h-14 bg-purple-50 rounded-xl flex items-center justify-center mb-4 group-hover:bg-[#7209b7] transition-colors">
                <!-- Device Icon -->
                <svg v-if="feature.icon === 'device'" class="w-7 h-7 text-[#7209b7] group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
                <!-- Shield Icon -->
                <svg v-else-if="feature.icon === 'shield'" class="w-7 h-7 text-[#7209b7] group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <!-- Clock Icon -->
                <svg v-else-if="feature.icon === 'clock'" class="w-7 h-7 text-[#7209b7] group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <!-- Palette Icon -->
                <svg v-else-if="feature.icon === 'palette'" class="w-7 h-7 text-[#7209b7] group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                </svg>
                <!-- Integration Icon -->
                <svg v-else-if="feature.icon === 'integration'" class="w-7 h-7 text-[#7209b7] group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <!-- Chart Icon -->
                <svg v-else-if="feature.icon === 'chart'" class="w-7 h-7 text-[#7209b7] group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
              </div>
              <h3 class="text-xl font-semibold text-gray-900 mb-2">{{ feature.title }}</h3>
              <p class="text-gray-600">{{ feature.description }}</p>
            </div>
          </div>
        </div>
      </section>

      <!-- How it Works Section -->
      <section id="process" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div class="text-center mb-16">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-[#7209b7] mb-4">
              Proceso
            </span>
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
              Implementa en semanas, no meses
            </h2>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">
              Nuestro equipo te acompaña en todo el proceso de implementación.
            </p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <div class="text-center relative animate-on-scroll" style="transition-delay: 0s">
              <div class="w-16 h-16 bg-[#7209b7] rounded-2xl flex items-center justify-center mx-auto mb-4 text-white text-2xl font-bold shadow-lg shadow-[#7209b7]/30 hover:scale-110 transition-transform cursor-default">
                1
              </div>
              <h3 class="font-semibold text-gray-900 mb-2 text-lg">Consultoría</h3>
              <p class="text-gray-600">Entendemos tu operación y productos de crédito</p>
              <!-- Connector line -->
              <div class="hidden md:block absolute top-8 left-[60%] w-[80%] h-0.5 bg-gradient-to-r from-[#7209b7] to-[#7209b7]/30"></div>
            </div>
            <div class="text-center relative animate-on-scroll" style="transition-delay: 0.15s">
              <div class="w-16 h-16 bg-[#7209b7] rounded-2xl flex items-center justify-center mx-auto mb-4 text-white text-2xl font-bold shadow-lg shadow-[#7209b7]/30 hover:scale-110 transition-transform cursor-default">
                2
              </div>
              <h3 class="font-semibold text-gray-900 mb-2 text-lg">Configuración</h3>
              <p class="text-gray-600">Personalizamos la plataforma con tu marca</p>
              <div class="hidden md:block absolute top-8 left-[60%] w-[80%] h-0.5 bg-gradient-to-r from-[#7209b7] to-[#7209b7]/30"></div>
            </div>
            <div class="text-center relative animate-on-scroll" style="transition-delay: 0.3s">
              <div class="w-16 h-16 bg-[#7209b7] rounded-2xl flex items-center justify-center mx-auto mb-4 text-white text-2xl font-bold shadow-lg shadow-[#7209b7]/30 hover:scale-110 transition-transform cursor-default">
                3
              </div>
              <h3 class="font-semibold text-gray-900 mb-2 text-lg">Integración</h3>
              <p class="text-gray-600">Conectamos con tus sistemas existentes</p>
              <div class="hidden md:block absolute top-8 left-[60%] w-[80%] h-0.5 bg-gradient-to-r from-[#7209b7] to-[#7209b7]/30"></div>
            </div>
            <div class="text-center animate-on-scroll" style="transition-delay: 0.45s">
              <div class="w-16 h-16 bg-[#7209b7] rounded-2xl flex items-center justify-center mx-auto mb-4 text-white text-2xl font-bold shadow-lg shadow-[#7209b7]/30 hover:scale-110 transition-transform cursor-default">
                4
              </div>
              <h3 class="font-semibold text-gray-900 mb-2 text-lg">Lanzamiento</h3>
              <p class="text-gray-600">Go-live con soporte continuo</p>
            </div>
          </div>
        </div>
      </section>

      <!-- CTA Section -->
      <section class="py-20 bg-[#7209b7] relative overflow-hidden">
        <div class="absolute inset-0 opacity-10">
          <div class="absolute top-0 right-0 w-96 h-96 bg-white rounded-full blur-3xl translate-x-1/2 -translate-y-1/2 animate-blob"></div>
          <div class="absolute bottom-0 left-0 w-96 h-96 bg-white rounded-full blur-3xl -translate-x-1/2 translate-y-1/2 animate-blob animation-delay-2000"></div>
        </div>
        <div class="max-w-4xl mx-auto px-4 text-center relative animate-on-scroll">
          <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">
            Transforma tu proceso de originación
          </h2>
          <p class="text-xl text-purple-100 mb-8">
            Agenda una demo y descubre cómo LendusFind puede ayudarte a crecer.
          </p>
          <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <button
              class="px-8 py-4 bg-white text-[#7209b7] rounded-xl font-semibold text-lg hover:bg-purple-50 transition-all shadow-lg hover:shadow-xl hover:scale-105 active:scale-95"
              @click="openWhatsApp"
            >
              <span class="flex items-center justify-center gap-2">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
                WhatsApp
              </span>
            </button>
            <button
              class="px-8 py-4 border-2 border-white/50 text-white rounded-xl font-semibold text-lg hover:bg-white/10 transition-all backdrop-blur-sm hover:scale-105 active:scale-95 hover:border-white"
              @click="openEmail"
            >
              Enviar Email
            </button>
          </div>
        </div>
      </section>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-400 py-12">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
          <div class="md:col-span-2">
            <img
              src="https://lendus.com.mx/img/logos/find.png"
              alt="LendusFind"
              class="h-8 mb-4 brightness-0 invert"
            >
            <p class="text-sm mb-4 max-w-md">
              Plataforma de originación digital de crédito para instituciones financieras en México.
              Captura clientes, valida identidad (KYC) y origina créditos de forma 100% digital.
            </p>
            <a
              href="https://lendus.com.mx"
              target="_blank"
              class="text-purple-400 hover:text-purple-300 font-medium inline-flex items-center gap-1"
            >
              Visitar Lendus.com.mx
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
              </svg>
            </a>
          </div>
          <div>
            <h4 class="text-white font-semibold mb-4">Contacto</h4>
            <ul class="space-y-3 text-sm">
              <li class="flex items-center gap-2">
                <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
                <a href="https://wa.me/525544159403" class="hover:text-white transition-colors">55 4415 9403</a>
              </li>
              <li class="flex items-center gap-2">
                <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                <a href="mailto:consultoria@lendus.com.mx" class="hover:text-white transition-colors">consultoria@lendus.com.mx</a>
              </li>
            </ul>
          </div>
          <div>
            <h4 class="text-white font-semibold mb-4">Legal</h4>
            <ul class="space-y-3 text-sm">
              <li><a href="https://lendus.com.mx/aviso-de-privacidad" target="_blank" class="hover:text-white transition-colors">Aviso de Privacidad</a></li>
              <li><a href="https://lendus.com.mx/terminos-y-condiciones" target="_blank" class="hover:text-white transition-colors">Términos y Condiciones</a></li>
            </ul>
          </div>
        </div>
        <div class="border-t border-gray-800 mt-8 pt-8 flex flex-col md:flex-row justify-between items-center gap-4">
          <p class="text-sm">&copy; {{ new Date().getFullYear() }} Lendus. Todos los derechos reservados.</p>
          <a
            href="https://lendus.com.mx"
            target="_blank"
            class="text-sm text-gray-500 hover:text-white transition-colors"
          >
            Un producto de Lendus
          </a>
        </div>
      </div>
    </footer>
  </div>
</template>

<style scoped>
/* Blob animation for background */
@keyframes blob {
  0%, 100% {
    transform: translate(0, 0) scale(1);
  }
  25% {
    transform: translate(20px, -30px) scale(1.1);
  }
  50% {
    transform: translate(-20px, 20px) scale(0.9);
  }
  75% {
    transform: translate(30px, 10px) scale(1.05);
  }
}

.animate-blob {
  animation: blob 8s ease-in-out infinite;
}

.animation-delay-2000 {
  animation-delay: 2s;
}

.animation-delay-4000 {
  animation-delay: 4s;
}

/* Float animation for cards */
@keyframes float {
  0%, 100% {
    transform: translateY(0px);
  }
  50% {
    transform: translateY(-10px);
  }
}

.animate-float {
  animation: float 4s ease-in-out infinite;
}

/* Slow pulse for badges */
@keyframes pulse-slow {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.8;
  }
}

.animate-pulse-slow {
  animation: pulse-slow 3s ease-in-out infinite;
}

/* Gradient text animation */
@keyframes gradient-shift {
  0%, 100% {
    background-position: 0% 50%;
  }
  50% {
    background-position: 100% 50%;
  }
}

.animate-gradient {
  background-size: 200% 200%;
  animation: gradient-shift 4s ease infinite;
}

/* Floating particles */
@keyframes particle-float {
  0%, 100% {
    transform: translateY(0) translateX(0) rotate(0deg);
    opacity: 0;
  }
  10% {
    opacity: 0.6;
  }
  90% {
    opacity: 0.6;
  }
  100% {
    transform: translateY(-100vh) translateX(20px) rotate(360deg);
    opacity: 0;
  }
}

.particle {
  position: absolute;
  width: 8px;
  height: 8px;
  background: rgba(255, 255, 255, 0.4);
  border-radius: 50%;
  animation: particle-float 12s ease-in-out infinite;
}

.particle-1 {
  left: 10%;
  top: 100%;
  animation-delay: 0s;
  animation-duration: 10s;
}

.particle-2 {
  left: 30%;
  top: 100%;
  animation-delay: 2s;
  animation-duration: 12s;
}

.particle-3 {
  left: 50%;
  top: 100%;
  animation-delay: 4s;
  animation-duration: 11s;
}

.particle-4 {
  left: 70%;
  top: 100%;
  animation-delay: 1s;
  animation-duration: 13s;
}

.particle-5 {
  left: 90%;
  top: 100%;
  animation-delay: 3s;
  animation-duration: 10s;
}

/* Scroll-triggered animations */
.animate-on-scroll {
  opacity: 0;
  transform: translateY(30px);
  transition: opacity 0.6s ease-out, transform 0.6s ease-out;
}

.animate-on-scroll.animate-in {
  opacity: 1;
  transform: translateY(0);
}

/* Staggered animations for grid items */
.animate-on-scroll:nth-child(2) {
  transition-delay: 0.1s;
}

.animate-on-scroll:nth-child(3) {
  transition-delay: 0.2s;
}

.animate-on-scroll:nth-child(4) {
  transition-delay: 0.3s;
}

.animate-on-scroll:nth-child(5) {
  transition-delay: 0.4s;
}

.animate-on-scroll:nth-child(6) {
  transition-delay: 0.5s;
}

/* Hover scale effect for cards */
.hover-lift {
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.hover-lift:hover {
  transform: translateY(-5px);
  box-shadow: 0 20px 40px rgba(114, 9, 183, 0.15);
}

/* Benefit item hover animation */
.benefit-item {
  transition: transform 0.2s ease;
}

.benefit-item:hover {
  transform: translateX(5px);
}

.benefit-item:hover .check-icon {
  animation: check-bounce 0.4s ease;
}

@keyframes check-bounce {
  0%, 100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.2);
  }
}

/* Icon bounce animation on card hover */
.group:hover .w-14 {
  animation: icon-wiggle 0.5s ease;
}

@keyframes icon-wiggle {
  0%, 100% {
    transform: rotate(0deg);
  }
  25% {
    transform: rotate(-5deg);
  }
  75% {
    transform: rotate(5deg);
  }
}

/* Smooth scroll behavior */
html {
  scroll-behavior: smooth;
}
</style>
