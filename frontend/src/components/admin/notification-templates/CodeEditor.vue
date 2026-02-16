<template>
  <div class="code-editor">
    <!-- VS Code style header -->
    <div class="bg-[#1e1e1e] px-4 py-2 flex items-center justify-between border-b border-[#2d2d2d]">
      <div class="flex items-center gap-3">
        <div class="flex gap-2">
          <div class="w-3 h-3 rounded-full bg-[#ff5f57]"></div>
          <div class="w-3 h-3 rounded-full bg-[#febc2e]"></div>
          <div class="w-3 h-3 rounded-full bg-[#28c840]"></div>
        </div>
        <div class="flex items-center gap-2 ml-4">
          <svg class="w-4 h-4 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd" />
          </svg>
          <span class="text-sm font-medium text-gray-400">template.html</span>
        </div>
      </div>
      <span class="text-xs text-gray-500 font-mono">HTML</span>
    </div>

    <!-- Code editor area -->
    <div class="relative bg-[#1e1e1e]">
      <!-- Line numbers -->
      <div class="absolute left-0 top-0 bottom-0 w-16 bg-[#1e1e1e] border-r border-[#2d2d2d] select-none overflow-hidden">
        <div class="py-4 px-3 text-right font-mono text-sm" style="color: #858585; line-height: 21px;">
          <div v-for="n in lineNumbers" :key="n" class="line-number">{{ n }}</div>
        </div>
      </div>

      <!-- Code textarea -->
      <textarea
        ref="codeTextarea"
        v-model="localValue"
        @input="handleInput"
        @scroll="handleScroll"
        @keydown="handleKeyDown"
        class="code-textarea"
        spellcheck="false"
        autocomplete="off"
        autocorrect="off"
        autocapitalize="off"
      />

      <!-- Highlighted code overlay -->
      <pre
        ref="highlightedCode"
        class="highlighted-code"
        v-html="highlightedHtml"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted } from 'vue'
import Prism from 'prismjs'
import 'prismjs/components/prism-markup'
import 'prismjs/components/prism-markup-templating'
// Don't import Prism CSS, we'll use custom VS Code colors

interface Props {
  modelValue: string
}

const props = defineProps<Props>()
const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()

const codeTextarea = ref<HTMLTextAreaElement>()
const highlightedCode = ref<HTMLPreElement>()
const localValue = ref(props.modelValue || '')

// Calculate line numbers
const lineNumbers = computed(() => {
  const count = localValue.value.split('\n').length
  return Array.from({ length: count }, (_, i) => i + 1)
})

// Highlight HTML syntax
const highlightedHtml = computed(() => {
  try {
    if (!localValue.value) return ''
    const highlighted = Prism.highlight(localValue.value, Prism.languages.markup || Prism.languages.html, 'html')
    return highlighted
  } catch (e) {
    console.error('Prism highlighting error:', e)
    return localValue.value
  }
})

// Handle input changes
const handleInput = () => {
  emit('update:modelValue', localValue.value)
}

// Sync scroll between textarea and highlighted code
const handleScroll = () => {
  if (codeTextarea.value && highlightedCode.value) {
    highlightedCode.value.scrollTop = codeTextarea.value.scrollTop
    highlightedCode.value.scrollLeft = codeTextarea.value.scrollLeft
  }
}

// Handle tab key for indentation
const handleKeyDown = (e: KeyboardEvent) => {
  if (e.key === 'Tab') {
    e.preventDefault()
    const textarea = codeTextarea.value
    if (!textarea) return

    const start = textarea.selectionStart
    const end = textarea.selectionEnd

    // Insert 2 spaces
    const spaces = '  '
    localValue.value = localValue.value.substring(0, start) + spaces + localValue.value.substring(end)

    nextTick(() => {
      textarea.selectionStart = textarea.selectionEnd = start + spaces.length
    })
  }
}

// Watch for external changes
watch(() => props.modelValue, (newValue) => {
  if (newValue !== localValue.value) {
    localValue.value = newValue || ''
  }
})
</script>

<style scoped>
.code-editor {
  border: 1px solid #2d2d2d;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.code-textarea {
  position: relative;
  z-index: 1;
  width: 100%;
  height: 500px;
  padding: 16px 16px 16px 68px;
  margin: 0;
  border: none;
  background: transparent;
  color: transparent;
  caret-color: #ffffff;
  font-family: 'Consolas', 'Monaco', 'Courier New', 'Menlo', monospace;
  font-size: 14px;
  line-height: 21px;
  tab-size: 2;
  resize: none;
  overflow: auto;
  white-space: pre;
  word-wrap: normal;
}

.code-textarea:focus {
  outline: none;
}

.highlighted-code {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  padding: 16px 16px 16px 68px;
  margin: 0;
  border: none;
  background: #1e1e1e;
  color: #d4d4d4;
  font-family: 'Consolas', 'Monaco', 'Courier New', 'Menlo', monospace;
  font-size: 14px;
  line-height: 21px;
  tab-size: 2;
  overflow: auto;
  white-space: pre;
  word-wrap: normal;
  pointer-events: none;
}

.line-number {
  height: 21px;
  user-select: none;
  color: #858585;
  text-align: right;
  padding-right: 8px;
}

/* VS Code Dark+ Theme Colors */
:deep(.token.tag) {
  color: #569cd6; /* Blue for tags */
}

:deep(.token.attr-name) {
  color: #9cdcfe; /* Light blue for attributes */
}

:deep(.token.attr-value) {
  color: #ce9178; /* Orange for values */
}

:deep(.token.string) {
  color: #ce9178; /* Orange for strings */
}

:deep(.token.punctuation) {
  color: #d4d4d4; /* Light gray for punctuation */
}

:deep(.token.doctype) {
  color: #569cd6; /* Blue for doctype */
}

:deep(.token.comment) {
  color: #6a9955; /* Green for comments */
  font-style: italic;
}

:deep(.token.operator) {
  color: #d4d4d4;
}

:deep(.token.entity) {
  color: #9cdcfe;
}

:deep(.token.url) {
  color: #4ec9b0;
}

:deep(.token.variable) {
  color: #9cdcfe;
}

:deep(.token.number) {
  color: #b5cea8;
}

:deep(.token.boolean) {
  color: #569cd6;
}

:deep(.token.selector) {
  color: #d7ba7d;
}

:deep(.token.important) {
  color: #c586c0;
  font-weight: bold;
}
</style>
