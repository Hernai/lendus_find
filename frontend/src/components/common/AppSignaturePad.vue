<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue'

interface Props {
  modelValue?: string | null
  label?: string
  error?: string
  required?: boolean
  height?: number
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: null,
  required: false,
  height: 150
})

const emit = defineEmits<{
  'update:modelValue': [value: string | null]
}>()

const canvasRef = ref<HTMLCanvasElement | null>(null)
const isDrawing = ref(false)
const isEmpty = ref(true)
let ctx: CanvasRenderingContext2D | null = null
let lastX = 0
let lastY = 0

const initCanvas = () => {
  if (!canvasRef.value) return

  const canvas = canvasRef.value
  const rect = canvas.getBoundingClientRect()

  // Set canvas size based on container
  canvas.width = rect.width * window.devicePixelRatio
  canvas.height = props.height * window.devicePixelRatio

  // Style the canvas
  canvas.style.width = `${rect.width}px`
  canvas.style.height = `${props.height}px`

  ctx = canvas.getContext('2d')
  if (!ctx) return

  // Scale for retina displays
  ctx.scale(window.devicePixelRatio, window.devicePixelRatio)

  // Set drawing styles
  ctx.strokeStyle = '#1f2937'
  ctx.lineWidth = 2
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'

  // If there's a previous value, load it
  if (props.modelValue) {
    const img = new Image()
    img.onload = () => {
      if (ctx && canvasRef.value) {
        ctx.drawImage(img, 0, 0, canvasRef.value.width / window.devicePixelRatio, props.height)
        isEmpty.value = false
      }
    }
    img.src = props.modelValue
  }
}

const getCoordinates = (e: MouseEvent | TouchEvent) => {
  if (!canvasRef.value) return { x: 0, y: 0 }

  const rect = canvasRef.value.getBoundingClientRect()

  if ('touches' in e) {
    const touch = e.touches[0]
    if (!touch) return { x: 0, y: 0 }
    return {
      x: touch.clientX - rect.left,
      y: touch.clientY - rect.top
    }
  } else {
    return {
      x: e.clientX - rect.left,
      y: e.clientY - rect.top
    }
  }
}

const startDrawing = (e: MouseEvent | TouchEvent) => {
  e.preventDefault()
  isDrawing.value = true
  const { x, y } = getCoordinates(e)
  lastX = x
  lastY = y
}

const draw = (e: MouseEvent | TouchEvent) => {
  if (!isDrawing.value || !ctx) return
  e.preventDefault()

  const { x, y } = getCoordinates(e)

  ctx.beginPath()
  ctx.moveTo(lastX, lastY)
  ctx.lineTo(x, y)
  ctx.stroke()

  lastX = x
  lastY = y
  isEmpty.value = false
}

const stopDrawing = () => {
  if (isDrawing.value) {
    isDrawing.value = false
    saveSignature()
  }
}

const saveSignature = () => {
  if (!canvasRef.value || isEmpty.value) {
    emit('update:modelValue', null)
    return
  }

  const dataUrl = canvasRef.value.toDataURL('image/png')
  emit('update:modelValue', dataUrl)
}

const clearSignature = () => {
  if (!ctx || !canvasRef.value) return

  ctx.clearRect(0, 0, canvasRef.value.width, canvasRef.value.height)
  isEmpty.value = true
  emit('update:modelValue', null)
}

// Watch for external value changes
watch(() => props.modelValue, (newVal) => {
  if (!newVal && canvasRef.value && ctx) {
    ctx.clearRect(0, 0, canvasRef.value.width, canvasRef.value.height)
    isEmpty.value = true
  }
})

onMounted(() => {
  initCanvas()
  window.addEventListener('resize', initCanvas)
})

onUnmounted(() => {
  window.removeEventListener('resize', initCanvas)
})
</script>

<template>
  <div class="w-full">
    <!-- Label -->
    <label v-if="label" class="block text-sm font-medium text-gray-700 mb-2">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>

    <!-- Signature Canvas Container -->
    <div
      class="relative border-2 rounded-xl overflow-hidden bg-white"
      :class="{
        'border-gray-300': !error,
        'border-red-500': error
      }"
    >
      <!-- Canvas -->
      <canvas
        ref="canvasRef"
        class="w-full touch-none cursor-crosshair"
        :style="{ height: `${height}px` }"
        @mousedown="startDrawing"
        @mousemove="draw"
        @mouseup="stopDrawing"
        @mouseleave="stopDrawing"
        @touchstart="startDrawing"
        @touchmove="draw"
        @touchend="stopDrawing"
      />

      <!-- Placeholder text -->
      <div
        v-if="isEmpty"
        class="absolute inset-0 flex items-center justify-center pointer-events-none"
      >
        <p class="text-gray-400 text-sm">Dibuja tu firma aquí</p>
      </div>

      <!-- Clear button -->
      <button
        v-if="!isEmpty"
        type="button"
        class="absolute top-2 right-2 p-1.5 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
        title="Borrar firma"
        @click="clearSignature"
      >
        <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
        </svg>
      </button>

      <!-- Signature line -->
      <div class="absolute bottom-4 left-4 right-4 border-b border-dashed border-gray-300" />
    </div>

    <!-- Helper text -->
    <p class="mt-1 text-xs text-gray-500">
      Usa tu dedo o mouse para firmar
    </p>

    <!-- Error message -->
    <p v-if="error" class="mt-1 text-sm text-red-600">
      {{ error }}
    </p>
  </div>
</template>
