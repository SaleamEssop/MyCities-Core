<template>
  <div class="keypad">
    <div class="keypad-grid">
      <button
        v-for="key in keys"
        :key="key.id"
        class="keypad-btn"
        :class="{
          'keypad-btn--action': key.isAction,
          'keypad-btn--empty':  key.isEmpty
        }"
        :disabled="key.isEmpty"
        @click="handleKey(key)"
      >
        <i v-if="key.icon" :class="key.icon"></i>
        <span v-else>{{ key.label }}</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  showDecimal: { type: Boolean, default: false },
})

const emit = defineEmits(['digit', 'decimal', 'backspace'])

const keys = computed(() => [
  { id: '1', label: '1' },
  { id: '2', label: '2' },
  { id: '3', label: '3' },
  { id: '4', label: '4' },
  { id: '5', label: '5' },
  { id: '6', label: '6' },
  { id: '7', label: '7' },
  { id: '8', label: '8' },
  { id: '9', label: '9' },
  { id: 'dot', label: '.', isDecimal: true, isEmpty: !props.showDecimal },
  { id: '0',  label: '0' },
  { id: 'bs', label: '',  icon: 'fas fa-backspace', isAction: true },
])

function handleKey(key) {
  if (key.isEmpty)    return
  if (key.isDecimal)  { emit('decimal'); return }
  if (key.isAction)   { emit('backspace'); return }
  emit('digit', key.label)
}
</script>

<style scoped>
.keypad {
  padding: 8px 16px 16px;
  background: var(--ua-card);
}

.keypad-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.keypad-btn {
  height: 56px;
  border: 1px solid var(--ua-divider);
  border-radius: var(--ua-radius-sm);
  background: var(--ua-card);
  color: var(--ua-text);
  font-size: 1.25rem;
  font-weight: 500;
  font-family: 'Nunito', sans-serif;
  cursor: pointer;
  transition: background 0.1s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.keypad-btn:active,
.keypad-btn:hover    { background: var(--ua-bg); }

.keypad-btn--action {
  background: #FFF3E0;
  color: #E65100;
  border-color: #FFCC80;
}

.keypad-btn--action:hover { background: #FFE0B2; }

.keypad-btn--empty {
  visibility: hidden;
  cursor: default;
}
</style>
