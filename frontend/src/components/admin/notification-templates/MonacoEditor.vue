<template>
  <div class="monaco-editor-container">
    <div ref="editorContainer" class="editor"></div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch, onBeforeUnmount } from 'vue'
import * as monaco from 'monaco-editor'

interface Props {
  modelValue: string
  language?: string
  theme?: string
  height?: string
}

const props = withDefaults(defineProps<Props>(), {
  language: 'html',
  theme: 'vs-dark',
  height: '600px',
})

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()

const editorContainer = ref<HTMLElement>()
let editor: monaco.editor.IStandaloneCodeEditor | null = null

onMounted(async () => {
  if (!editorContainer.value) return

  // Configure Monaco
  monaco.languages.html.htmlDefaults.setOptions({
    format: {
      tabSize: 4,
      insertSpaces: true,
      wrapLineLength: 120,
      unformatted: '',
      contentUnformatted: 'pre,code,textarea',
      indentInnerHtml: true,
      preserveNewLines: false,
      maxPreserveNewLines: 1,
      indentHandlebars: false,
      endWithNewline: true,
      extraLiners: 'head, body, /html',
      wrapAttributes: 'auto',
    },
  })

  // Create editor
  editor = monaco.editor.create(editorContainer.value, {
    value: props.modelValue,
    language: props.language,
    theme: props.theme,
    automaticLayout: true,
    fontSize: 14,
    lineNumbers: 'on',
    roundedSelection: true,
    scrollBeyondLastLine: false,
    minimap: {
      enabled: true,
    },
    tabSize: 4,
    insertSpaces: true,
    detectIndentation: false,
    wordWrap: 'on',
    formatOnPaste: true,
    formatOnType: false,
  })

  // Auto-format on load if there's content
  if (props.modelValue) {
    setTimeout(async () => {
      if (editor) {
        await editor.getAction('editor.action.formatDocument')?.run()
      }
    }, 100)
  }

  // Add keyboard shortcut for formatting (Shift+Alt+F)
  editor.addCommand(monaco.KeyMod.Shift | monaco.KeyMod.Alt | monaco.KeyCode.KeyF, () => {
    editor?.getAction('editor.action.formatDocument')?.run()
  })

  // Listen for changes
  editor.onDidChangeModelContent(() => {
    if (editor) {
      emit('update:modelValue', editor.getValue())
    }
  })
})

// Watch for external changes
watch(
  () => props.modelValue,
  async (newValue) => {
    if (editor && editor.getValue() !== newValue) {
      editor.setValue(newValue)

      // Auto-format after setting new value
      if (newValue) {
        setTimeout(async () => {
          if (editor) {
            await editor.getAction('editor.action.formatDocument')?.run()
          }
        }, 100)
      }
    }
  }
)

onBeforeUnmount(() => {
  if (editor) {
    editor.dispose()
    editor = null
  }
})
</script>

<style scoped>
.monaco-editor-container {
  border: 1px solid #2d2d2d;
  border-radius: 8px;
  overflow: hidden;
  background-color: #1e1e1e;
}

.editor {
  height: v-bind(height);
  width: 100%;
}
</style>
