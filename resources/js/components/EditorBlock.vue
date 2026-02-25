<template>
  <div>
    <div class="editorjs-hint alert alert-info py-2 px-3 mb-2 small">
      <strong><i class="fas fa-keyboard mr-1"></i>Block Editor</strong> —
      Click inside, then:
      <kbd>/</kbd> to choose a block type &nbsp;·&nbsp;
      <kbd>Enter</kbd> for a new paragraph &nbsp;·&nbsp;
      click <strong>+</strong> on the left margin to add any block
    </div>

    <div
      :id="holderId"
      class="editorjs-holder"
    ></div>

    <div class="editorjs-status small mt-1" :class="statusClass">{{ statusText }}</div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import EditorJS   from '@editorjs/editorjs'
import Header     from '@editorjs/header'
import List       from '@editorjs/list'
import Quote      from '@editorjs/quote'
import Delimiter  from '@editorjs/delimiter'
import ImageTool  from '@editorjs/image'
import Underline  from '@editorjs/underline'
import Marker     from '@editorjs/marker'
import InlineCode from '@editorjs/inline-code'
import Table      from '@editorjs/table'

const props = defineProps({
  modelValue:     { type: String, default: '' },
  imageUploadUrl: { type: String, required: true },
  imageByUrlUrl:  { type: String, required: true },
  csrfToken:      { type: String, required: true },
  placeholder:    { type: String, default: 'Click here or press / to choose a block type...' },
})

const emit = defineEmits(['update:modelValue'])

// Unique holder id so multiple editors can coexist on one page
const holderId   = `editorjs-${Math.random().toString(36).slice(2, 8)}`
const statusText = ref('Loading editor…')
const statusClass = ref('text-muted')

let editor = null

function parseInitialData(raw) {
  if (!raw) return { blocks: [] }
  try {
    const parsed = JSON.parse(raw)
    if (parsed && Array.isArray(parsed.blocks)) return parsed
    return { blocks: [] }
  } catch {
    // Legacy HTML — wrap as paragraph
    return { blocks: [{ type: 'paragraph', data: { text: raw } }] }
  }
}

onMounted(async () => {
  editor = new EditorJS({
    holder: holderId,
    autofocus: true,
    tools: {
      header:    { class: Header,    inlineToolbar: true, config: { levels: [1,2,3,4], defaultLevel: 2 } },
      list:      { class: List,      inlineToolbar: true },
      quote:     { class: Quote,     inlineToolbar: true },
      delimiter: Delimiter,
      image: {
        class: ImageTool,
        config: {
          endpoints: {
            byFile: props.imageUploadUrl,
            byUrl:  props.imageByUrlUrl,
          },
          additionalRequestHeaders: { 'X-CSRF-TOKEN': props.csrfToken },
          captionPlaceholder: 'Image caption (optional)',
        },
      },
      underline:  Underline,
      marker:     Marker,
      inlineCode: InlineCode,
      table:      { class: Table, inlineToolbar: true },
    },
    data: parseInitialData(props.modelValue),
    placeholder: props.placeholder,
    onChange: async () => {
      const saved = await editor.save()
      emit('update:modelValue', JSON.stringify(saved))
    },
    onReady: () => {
      statusText.value  = '✓ Editor ready — click inside to begin'
      statusClass.value = 'text-success'
      try {
        import('editorjs-undo').then(({ default: Undo }) => new Undo({ editor }))
      } catch {}
    },
  })
})

onBeforeUnmount(async () => {
  if (editor) {
    try { await editor.destroy() } catch {}
    editor = null
  }
})
</script>

<style scoped>
.editorjs-holder {
  border: 2px solid #4e73df;
  border-radius: 0.35rem;
  min-height: 400px;
  background: #fff;
  padding: 8px 0;
  cursor: text;
}
.editorjs-holder :deep(.ce-block__content),
.editorjs-holder :deep(.ce-toolbar__content) {
  max-width: 100% !important;
}
.editorjs-holder :deep(.ce-toolbar__actions) {
  opacity: 1 !important;
}
.editorjs-holder :deep(.codex-editor) {
  font-family: 'Nunito', sans-serif;
}
</style>
