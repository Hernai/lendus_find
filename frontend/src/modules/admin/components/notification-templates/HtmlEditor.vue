<template>
  <div class="html-editor-modern relative overflow-hidden">
    <div class="flex relative" style="height: 700px;">
      <!-- Main Editor Area -->
      <div class="flex-1 flex flex-col">
        <!-- Elegant Sub-Tabs -->
        <div class="flex bg-white border-b border-gray-200">
          <!-- Left-aligned tabs -->
          <div class="flex">
            <button
              @click="changeViewMode('visual')"
              :class="viewMode === 'visual' ? 'border-b-2 border-indigo-500 text-indigo-600 bg-indigo-50/50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'"
              class="px-4 py-2.5 font-medium text-sm transition-all flex items-center justify-center gap-1.5"
              type="button"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
              </svg>
              <span>Editor Visual</span>
              <span v-if="isEmailTemplate" class="ml-0.5 text-xs bg-yellow-100 text-yellow-700 px-1.5 py-0.5 rounded-full">⚠️</span>
            </button>
            <button
              @click="changeViewMode('code')"
              :class="viewMode === 'code' ? 'border-b-2 border-emerald-500 text-emerald-600 bg-emerald-50/50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'"
              class="px-4 py-2.5 font-medium text-sm transition-all flex items-center justify-center gap-1.5"
              type="button"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
              </svg>
              <span>Código HTML</span>
              <span v-if="isEmailTemplate" class="ml-0.5 text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full">✓</span>
            </button>
            <button
              @click="changeViewMode('preview')"
              :class="viewMode === 'preview' ? 'border-b-2 border-blue-500 text-blue-600 bg-blue-50/50' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'"
              class="px-4 py-2.5 font-medium text-sm transition-all flex items-center justify-center gap-1.5"
              type="button"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              <span>Vista Previa</span>
            </button>
          </div>

          <!-- Right-aligned buttons (hidden in preview mode) -->
          <div v-if="viewMode !== 'preview'" class="ml-auto flex">
            <button
              @click="showGallery = true"
              class="px-4 py-2.5 font-medium text-sm transition-all flex items-center justify-center gap-1.5 text-purple-600 hover:bg-purple-50 border-l border-gray-200"
              type="button"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
              </svg>
              <span>Plantillas</span>
            </button>
            <button
              @click="showVariablesPanel = !showVariablesPanel"
              :class="showVariablesPanel ? 'bg-green-100 text-green-700' : 'text-green-600 hover:bg-green-50'"
              class="px-4 py-2.5 font-medium text-sm transition-all flex items-center justify-center gap-1.5 border-l border-gray-200"
              type="button"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
              </svg>
              <span>Variables</span>
            </button>
          </div>
        </div>

        <!-- Visual Editor View -->
        <div v-show="viewMode === 'visual'" class="visual-editor-container" style="height: calc(700px - 56px); overflow: hidden;">
          <!-- Compact Toolbar -->
          <div v-if="editor" class="toolbar bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200 px-4 py-2">
        <div class="flex flex-wrap items-center gap-1">
          <!-- Text Formatting -->
          <div class="flex items-center gap-0.5 pr-2 border-r border-gray-300">
            <button
              @click="editor.chain().focus().toggleBold().run()"
              :class="editor.isActive('bold') ? 'bg-indigo-100 text-indigo-700' : 'hover:bg-gray-200'"
              class="p-2 rounded transition-colors"
              type="button"
              title="Negrita (Ctrl+B)"
            >
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path d="M7 3V2h7.586l-1.293 1.293A1 1 0 0113 4H7v1h3v1H7v1h3v1H7v1h4a1 1 0 01.707 1.707L10.414 11H7v1h3v1H7v1h3v1H7v1h3.586l1.293 1.293A1 1 0 0111 19H4V3h3z" />
              </svg>
            </button>
            <button
              @click="editor.chain().focus().toggleItalic().run()"
              :class="editor.isActive('italic') ? 'bg-indigo-100 text-indigo-700' : 'hover:bg-gray-200'"
              class="p-2 rounded transition-colors"
              type="button"
              title="Cursiva (Ctrl+I)"
            >
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 3v1H8l2 12h2l-2-12h2V3h-4zm0 14v1h4v-1h-4z" />
              </svg>
            </button>
            <button
              @click="editor.chain().focus().toggleStrike().run()"
              :class="editor.isActive('strike') ? 'bg-indigo-100 text-indigo-700' : 'hover:bg-gray-200'"
              class="p-2 rounded transition-colors"
              type="button"
              title="Tachado"
            >
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
              </svg>
            </button>
          </div>

          <!-- Headings -->
          <div class="flex items-center gap-0.5 px-2 border-r border-gray-300">
            <button
              @click="editor.chain().focus().toggleHeading({ level: 1 }).run()"
              :class="editor.isActive('heading', { level: 1 }) ? 'bg-indigo-100 text-indigo-700' : 'hover:bg-gray-200'"
              class="px-2.5 py-1.5 rounded transition-colors text-sm font-bold"
              type="button"
              title="Título 1"
            >
              H1
            </button>
            <button
              @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
              :class="editor.isActive('heading', { level: 2 }) ? 'bg-indigo-100 text-indigo-700' : 'hover:bg-gray-200'"
              class="px-2.5 py-1.5 rounded transition-colors text-sm font-bold"
              type="button"
              title="Título 2"
            >
              H2
            </button>
            <button
              @click="editor.chain().focus().setParagraph().run()"
              :class="editor.isActive('paragraph') ? 'bg-indigo-100 text-indigo-700' : 'hover:bg-gray-200'"
              class="px-2.5 py-1.5 rounded transition-colors text-sm"
              type="button"
              title="Párrafo"
            >
              P
            </button>
          </div>

          <!-- Lists -->
          <div class="flex items-center gap-0.5 px-2 border-r border-gray-300">
            <button
              @click="editor.chain().focus().toggleBulletList().run()"
              :class="editor.isActive('bulletList') ? 'bg-indigo-100 text-indigo-700' : 'hover:bg-gray-200'"
              class="p-2 rounded transition-colors"
              type="button"
              title="Lista con viñetas"
            >
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4 4a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zm0 4a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zm0 4a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zm0 4a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1z" clip-rule="evenodd" />
              </svg>
            </button>
            <button
              @click="editor.chain().focus().toggleOrderedList().run()"
              :class="editor.isActive('orderedList') ? 'bg-indigo-100 text-indigo-700' : 'hover:bg-gray-200'"
              class="p-2 rounded transition-colors"
              type="button"
              title="Lista numerada"
            >
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
              </svg>
            </button>
          </div>

          <!-- Alignment -->
          <div class="flex items-center gap-0.5 px-2 border-r border-gray-300">
            <button
              @click="editor.chain().focus().setTextAlign('left').run()"
              :class="editor.isActive({ textAlign: 'left' }) ? 'bg-indigo-100 text-indigo-700' : 'hover:bg-gray-200'"
              class="p-2 rounded transition-colors"
              type="button"
              title="Alinear izquierda"
            >
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h8a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h8a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
              </svg>
            </button>
            <button
              @click="editor.chain().focus().setTextAlign('center').run()"
              :class="editor.isActive({ textAlign: 'center' }) ? 'bg-indigo-100 text-indigo-700' : 'hover:bg-gray-200'"
              class="p-2 rounded transition-colors"
              type="button"
              title="Centrar"
            >
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm2 4a1 1 0 011-1h8a1 1 0 110 2H6a1 1 0 01-1-1zm-2 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm2 4a1 1 0 011-1h8a1 1 0 110 2H6a1 1 0 01-1-1z" clip-rule="evenodd" />
              </svg>
            </button>
            <button
              @click="editor.chain().focus().setTextAlign('right').run()"
              :class="editor.isActive({ textAlign: 'right' }) ? 'bg-indigo-100 text-indigo-700' : 'hover:bg-gray-200'"
              class="p-2 rounded transition-colors"
              type="button"
              title="Alinear derecha"
            >
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm4 4a1 1 0 011-1h8a1 1 0 110 2H8a1 1 0 01-1-1zm-4 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm4 4a1 1 0 011-1h8a1 1 0 110 2H8a1 1 0 01-1-1z" clip-rule="evenodd" />
              </svg>
            </button>
          </div>

          <!-- Link -->
          <div class="flex items-center gap-0.5 px-2">
            <button
              @click="setLink"
              :class="editor.isActive('link') ? 'bg-indigo-100 text-indigo-700' : 'hover:bg-gray-200'"
              class="p-2 rounded transition-colors"
              type="button"
              title="Agregar enlace"
            >
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M12.586 4.586a2 2 0 112.828 2.828l-3 3a2 2 0 01-2.828 0 1 1 0 00-1.414 1.414 4 4 0 005.656 0l3-3a4 4 0 00-5.656-5.656l-1.5 1.5a1 1 0 101.414 1.414l1.5-1.5zm-5 5a2 2 0 012.828 0 1 1 0 101.414-1.414 4 4 0 00-5.656 0l-3 3a4 4 0 105.656 5.656l1.5-1.5a1 1 0 10-1.414-1.414l-1.5 1.5a2 2 0 11-2.828-2.828l3-3z" clip-rule="evenodd" />
              </svg>
            </button>
          </div>
        </div>
      </div>

          <!-- Editor Content -->
          <div class="editor-content bg-white overflow-y-auto" style="height: calc(100% - 56px);">
            <editor-content :editor="editor" class="prose prose-sm max-w-none p-8" />
          </div>
        </div>

        <!-- Code View with Professional Monaco Editor -->
        <div v-show="viewMode === 'code'" class="code-editor-container" style="height: calc(700px - 56px); width: 100%;">
          <MonacoEditor
            v-model="htmlCode"
            language="html"
            theme="vs-dark"
            :height="`${700 - 56}px`"
            @update:modelValue="updateFromCode"
          />
        </div>

        <!-- Preview View -->
        <div v-show="viewMode === 'preview'" class="preview-container bg-gradient-to-br from-gray-50 to-gray-100 overflow-y-auto" style="height: calc(700px - 56px);">
          <div v-if="(htmlCode || props.modelValue) && props.channel" class="p-6">
            <NotificationPreview
              :body="htmlCode || props.modelValue"
              :html-body="htmlCode || props.htmlBody"
              :subject="props.subject"
              :channel="props.channel"
              :variables="props.previewVariables || {}"
            />
          </div>
          <div v-else class="flex items-center justify-center h-full">
            <div class="text-center px-6">
              <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gradient-to-br from-purple-100 to-indigo-100 mb-3">
                <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
              </div>
              <h3 class="text-base font-semibold text-gray-900 mb-1">Sin vista previa</h3>
              <p class="text-sm text-gray-600">
                <span v-if="!props.channel">Selecciona un canal en el formulario</span>
                <span v-else>Escribe contenido en el editor</span>
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Variables Sidebar Panel -->
      <transition name="slide-fade">
        <div v-if="showVariablesPanel" class="variables-sidebar">
          <div class="variables-header">
            <h3 class="text-base font-bold text-white flex items-center gap-2">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
              </svg>
              Variables
            </h3>
            <button
              @click="showVariablesPanel = false"
              class="p-1.5 text-white/70 hover:text-white hover:bg-white/10 rounded-lg transition-colors"
              type="button"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="variables-content">
            <div v-for="(variables, category) in variablesByCategory" :key="category" class="mb-4">
              <div class="category-header">
                <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wide">
                  {{ categoryLabels[category] }}
                </h4>
              </div>
              <div class="variables-list">
                <button
                  v-for="variable in variables"
                  :key="variable.key"
                  @click="insertVariable(variable.key)"
                  class="variable-item"
                  type="button"
                  :title="variable.description"
                >
                  <code class="variable-code">
                    {{ formatVariable(variable.key) }}
                  </code>
                  <div class="variable-label">{{ variable.label }}</div>
                  <div class="variable-example">Ej: {{ variable.example }}</div>
                </button>
              </div>
            </div>
          </div>
        </div>
      </transition>
    </div>

    <!-- Template Gallery Modal -->
    <div v-if="showGallery" class="template-gallery-modal">
      <div class="gallery-container">
        <div class="gallery-header">
          <h3>Selecciona una Plantilla Profesional</h3>
          <button @click="showGallery = false" class="close-btn">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="templates-grid">
          <div
            v-for="template in emailTemplates"
            :key="template.id"
            @click="selectTemplate(template)"
            class="template-card"
          >
            <div class="template-icon">{{ template.thumbnail }}</div>
            <h4>{{ template.name }}</h4>
            <p>{{ template.description }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onBeforeUnmount, computed } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import TextAlign from '@tiptap/extension-text-align'
import MonacoEditor from './MonacoEditor.vue'
import NotificationPreview from './NotificationPreview.vue'
import { emailTemplates } from '@/utils/emailTemplates'
import {
  notificationVariables,
  variablesByCategory,
  categoryLabels,
  formatVariable,
} from '@/utils/notificationVariables'

interface Props {
  modelValue: string
  htmlBody?: string | null
  availableVariables?: Record<string, string>
  channel?: string | null
  subject?: string | null
  previewVariables?: Record<string, any>
}

const props = defineProps<Props>()
const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
  (e: 'update:htmlBody', value: string | null): void
}>()

const viewMode = ref<'visual' | 'code' | 'preview'>('code')
const htmlCode = ref(props.htmlBody || props.modelValue || '')
const showGallery = ref(false)
const showVariablesPanel = ref(false)

// Check if current content is a complex email template
const isEmailTemplate = computed(() => {
  return htmlCode.value.includes('<table') || htmlCode.value.includes('<!DOCTYPE')
})

// Handle view mode changes with warning for email templates
const changeViewMode = (mode: 'visual' | 'code' | 'preview') => {
  if (mode === 'visual' && isEmailTemplate.value) {
    const confirmed = window.confirm(
      '⚠️ Advertencia: Este contenido parece ser una plantilla de email con diseño complejo.\n\n' +
      'El Editor Visual puede eliminar elementos de diseño como tablas, gradientes y estilos inline.\n\n' +
      '¿Deseas continuar de todos modos?\n\n' +
      'Recomendación: Usa "Código HTML" para editar plantillas de email profesionales.'
    )
    if (!confirmed) {
      return
    }
  }
  viewMode.value = mode
}

// Select template from gallery
const selectTemplate = (template: any) => {
  htmlCode.value = template.html
  emit('update:htmlBody', template.html)
  emit('update:modelValue', template.html)
  // Don't load complex email HTML into TipTap - it will strip the structure
  // Keep it only in code mode (Monaco Editor)
  showGallery.value = false
  viewMode.value = 'code'
}

const editor = useEditor({
  content: props.modelValue || '',
  extensions: [
    StarterKit,
    TextAlign.configure({
      types: ['heading', 'paragraph'],
    }),
  ],
  onUpdate: ({ editor }) => {
    const html = editor.getHTML()
    htmlCode.value = html
    emit('update:modelValue', html)
  },
})

const updateFromCode = (newCode: string) => {
  htmlCode.value = newCode
  emit('update:htmlBody', newCode)
  // Don't sync complex email HTML to TipTap - it will strip table structures and inline styles
  // Only sync if the code is simple enough (no tables, no complex structures)
  if (editor.value && !newCode.includes('<table') && !newCode.includes('<!DOCTYPE')) {
    editor.value.commands.setContent(newCode)
  }
  emit('update:modelValue', newCode)
}

// Insert variable into editor
const insertVariable = (variableKey: string) => {
  const formattedVar = formatVariable(variableKey)

  if (viewMode.value === 'visual' && editor.value) {
    editor.value.commands.insertContent(formattedVar)
  } else {
    // In code mode, insert at cursor or append
    htmlCode.value += formattedVar
    emit('update:htmlBody', htmlCode.value)
    emit('update:modelValue', htmlCode.value)
  }
}

const setLink = () => {
  const previousUrl = editor.value?.getAttributes('link').href
  const url = window.prompt('URL del enlace:', previousUrl)

  if (url === null) {
    return
  }

  if (url === '') {
    editor.value?.chain().focus().extendMarkRange('link').unsetLink().run()
    return
  }

  editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run()
}

watch(() => props.modelValue, (newValue) => {
  const safeValue = newValue || ''
  htmlCode.value = safeValue
  // Only update TipTap if it's simple HTML (not email templates)
  if (newValue !== editor.value?.getHTML() && !safeValue.includes('<table') && !safeValue.includes('<!DOCTYPE')) {
    editor.value?.commands.setContent(safeValue)
  }
})

watch(() => props.htmlBody, (newValue) => {
  if (newValue && newValue !== htmlCode.value) {
    htmlCode.value = newValue
    // Only update TipTap if it's simple HTML (not email templates)
    if (editor.value && !newValue.includes('<table') && !newValue.includes('<!DOCTYPE')) {
      editor.value.commands.setContent(newValue)
    }
  }
})

onBeforeUnmount(() => {
  editor.value?.destroy()
})
</script>

<style scoped>
.html-editor-modern {
  @apply overflow-hidden;
}

/* TipTap Editor Styles */
:deep(.ProseMirror) {
  min-height: 400px;
  outline: none;
}

:deep(.ProseMirror:focus) {
  outline: none;
}

:deep(.ProseMirror p) {
  margin: 0.75em 0;
  line-height: 1.6;
}

:deep(.ProseMirror h1) {
  font-size: 2em;
  font-weight: 700;
  margin: 0.75em 0;
  color: #1f2937;
}

:deep(.ProseMirror h2) {
  font-size: 1.5em;
  font-weight: 600;
  margin: 0.75em 0;
  color: #374151;
}

:deep(.ProseMirror ul),
:deep(.ProseMirror ol) {
  padding-left: 1.5em;
  margin: 0.75em 0;
}

:deep(.ProseMirror a) {
  color: #3b82f6;
  text-decoration: underline;
  cursor: pointer;
}

:deep(.ProseMirror a:hover) {
  color: #2563eb;
}

:deep(.ProseMirror code) {
  background-color: #f3f4f6;
  padding: 0.2em 0.4em;
  border-radius: 0.25rem;
  font-family: 'Consolas', 'Monaco', monospace;
  font-size: 0.9em;
  color: #e11d48;
}

:deep(.ProseMirror pre) {
  background-color: #1f2937;
  color: #f3f4f6;
  padding: 1em;
  border-radius: 0.5rem;
  overflow-x: auto;
  font-family: 'Consolas', 'Monaco', monospace;
}

:deep(.ProseMirror blockquote) {
  border-left: 3px solid #d1d5db;
  padding-left: 1em;
  margin-left: 0;
  color: #6b7280;
  font-style: italic;
}

/* Template Gallery Modal */
.template-gallery-modal {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.75);
  z-index: 50;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  animation: fadeIn 0.2s ease-in-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.gallery-container {
  background-color: white;
  border-radius: 16px;
  max-width: 1200px;
  width: 100%;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.gallery-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24px 30px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.gallery-header h3 {
  margin: 0;
  font-size: 22px;
  font-weight: 700;
}

.close-btn {
  padding: 8px;
  background: rgba(255, 255, 255, 0.2);
  border: none;
  color: white;
  cursor: pointer;
  border-radius: 8px;
  transition: all 0.2s;
}

.close-btn:hover {
  background-color: rgba(255, 255, 255, 0.3);
}

.templates-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 24px;
  padding: 30px;
  overflow-y: auto;
}

.template-card {
  padding: 30px;
  background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
  border: 2px solid #e5e7eb;
  border-radius: 16px;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  text-align: center;
}

.template-card:hover {
  transform: translateY(-8px);
  box-shadow: 0 20px 40px rgba(99, 102, 241, 0.2);
  border-color: #6366f1;
  background: linear-gradient(135deg, #ffffff 0%, #f9fafb 100%);
}

.template-icon {
  font-size: 56px;
  margin-bottom: 20px;
  filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.1));
}

.template-card h4 {
  margin: 0 0 10px 0;
  font-size: 18px;
  font-weight: 700;
  color: #111827;
}

.template-card p {
  margin: 0;
  font-size: 14px;
  color: #6b7280;
  line-height: 1.6;
}

/* Variables Sidebar */
.variables-sidebar {
  position: absolute;
  top: 0;
  right: 0;
  width: 288px;
  height: 100%;
  background: white;
  box-shadow: -4px 0 16px rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
  z-index: 10;
  border-left: 1px solid #e5e7eb;
}

.variables-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  flex-shrink: 0;
}

.variables-header h3 {
  margin: 0;
  font-size: 14px;
  font-weight: 700;
}

.variables-content {
  flex: 1;
  overflow-y: auto;
  padding: 16px 12px;
  background: linear-gradient(to bottom, #f9fafb 0%, #ffffff 100%);
}

.category-header {
  margin-bottom: 8px;
  padding-bottom: 6px;
  border-bottom: 1px solid #e5e7eb;
}

.category-header h4 {
  margin: 0;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #6b7280;
}

.variables-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 16px;
}

.variable-item {
  display: block;
  width: 100%;
  text-align: left;
  padding: 8px 10px;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.variable-item:hover {
  background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
  border-color: #10b981;
  transform: translateX(-2px);
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.1);
}

.variable-code {
  display: inline-block;
  font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
  font-size: 11px;
  font-weight: 600;
  color: #059669;
  background: #d1fae5;
  padding: 3px 6px;
  border-radius: 4px;
  margin-bottom: 4px;
}

.variable-item:hover .variable-code {
  background: #a7f3d0;
  color: #047857;
}

.variable-label {
  font-size: 12px;
  font-weight: 500;
  color: #374151;
  margin-bottom: 2px;
  line-height: 1.3;
}

.variable-example {
  font-size: 11px;
  color: #9ca3af;
  font-style: italic;
  line-height: 1.2;
}

/* Slide Fade Transition */
.slide-fade-enter-active {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.slide-fade-leave-active {
  transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.slide-fade-enter-from {
  transform: translateX(100%);
}

.slide-fade-enter-to {
  transform: translateX(0);
}

.slide-fade-leave-from {
  transform: translateX(0);
}

.slide-fade-leave-to {
  transform: translateX(100%);
}
</style>
