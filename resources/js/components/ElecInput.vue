<template>
  <div
    class="ei"
    :class="{ 'ei--focused': isFocused, 'ei--disabled': disabled }"
    :tabindex="disabled ? -1 : 0"
    @keydown="onKeyDown"
    @focus="onFocus"
    @blur="onBlur"
    @click="onWrapClick"
  >
    <div class="ei-display">
      <div
        v-for="i in 6"
        :key="i"
        class="ei-slot"
        :class="{
          'ei-slot--active': isFocused && cursorPos === i - 1,
          'ei-slot--zero':   isLeadingZero(i - 1),
        }"
        @click.stop="setCursor(i - 1)"
      >
        {{ digits[i - 1] }}
      </div>
      <div class="ei-unit">kWh</div>
    </div>
    <button
      class="ei-back"
      type="button"
      tabindex="-1"
      :disabled="disabled"
      @click.stop="backspace"
      title="Clear last digit"
    >
      <i class="fas fa-backspace"></i>
    </button>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '000000' },
  disabled:   { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'change'])

const digits    = ref([0, 0, 0, 0, 0, 0])
const cursorPos = ref(0)
const isFocused = ref(false)

function strToDigits (s) {
  return String(Math.max(0, Math.min(999999, parseInt(s) || 0)))
    .padStart(6, '0')
    .split('').map(Number)
}
function digitsToStr () { return digits.value.join('') }

function isLeadingZero (pos) {
  for (let j = 0; j <= pos; j++) {
    if (digits.value[j] !== 0) return false
  }
  return true
}

watch(() => props.modelValue, (v) => {
  if (v !== digitsToStr()) digits.value = strToDigits(v)
}, { immediate: true })

function onFocus ()    { isFocused.value = true }
function onBlur ()     {
  isFocused.value = false
  emit('change', digitsToStr())  // reconciliation runs once when user leaves the field
}
function onWrapClick () {
  if (props.disabled) return
  if (!isFocused.value) { cursorPos.value = 0; isFocused.value = true }
}
function setCursor (pos) {
  if (props.disabled) return
  cursorPos.value = pos
  if (!isFocused.value) isFocused.value = true
}

function onKeyDown (e) {
  if (props.disabled) return
  if (e.key >= '0' && e.key <= '9') { e.preventDefault(); enterDigit(parseInt(e.key)) }
  else if (e.key === 'Backspace')    { e.preventDefault(); backspace() }
  else if (e.key === 'Delete')       { e.preventDefault(); clearAll() }
  else if (e.key === 'ArrowLeft')    { e.preventDefault(); if (cursorPos.value > 0) cursorPos.value-- }
  else if (e.key === 'ArrowRight')   { e.preventDefault(); if (cursorPos.value < 5) cursorPos.value++ }
  else if (e.key !== 'Tab')          { e.preventDefault() }
}

function enterDigit (d) {
  const next = [...digits.value]
  next[cursorPos.value] = d
  digits.value = next
  if (cursorPos.value < 5) cursorPos.value++
  emitChange()
}
function backspace () {
  const next = [...digits.value]
  next[cursorPos.value] = 0
  digits.value = next
  if (cursorPos.value > 0) cursorPos.value--
  emitChange()
}
function clearAll () {
  digits.value = [0, 0, 0, 0, 0, 0]
  cursorPos.value = 0
  emitChange()
}
function emitChange () {
  const s = digitsToStr()
  emit('update:modelValue', s)
  // 'change' emitted only on blur so parent reconciliation runs after user finishes the reading
}
</script>

<style scoped>
.ei {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 8px 5px 10px;
  border: 1.5px solid #4a5568;
  border-radius: 8px;
  background: #2d3748;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.25);
  cursor: text;
  outline: none;
  transition: border-color 0.15s, box-shadow 0.15s;
  user-select: none;
}
.ei:focus,
.ei--focused {
  border-color: #a0aec0;
  box-shadow: 0 0 0 3px rgba(160, 174, 192, 0.2);
}
.ei--disabled { opacity: 0.6; cursor: not-allowed; }

.ei-display { display: flex; align-items: center; gap: 3px; }

.ei-slot {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 38px;
  font-size: 1.25rem;
  font-weight: 800;
  font-family: 'Courier New', monospace;
  color: #e2e8f0;
  background: #1a202c;
  border: 1.5px solid #4a5568;
  border-radius: 5px;
  transition: border-color 0.12s, background 0.12s, box-shadow 0.12s;
  cursor: pointer;
}
.ei-slot--zero  { color: #718096; }
.ei-slot--active {
  border-color: #cbd5e0 !important;
  background: #2d3748 !important;
  box-shadow: 0 0 0 2px rgba(203, 213, 224, 0.2);
  color: #fff !important;
}
.ei-slot--active::after {
  content: '';
  position: absolute;
  bottom: 4px;
  left: 50%;
  transform: translateX(-50%);
  width: 55%;
  height: 2px;
  background: #a0aec0;
  border-radius: 1px;
  animation: ei-blink 1.1s step-end infinite;
}
@keyframes ei-blink {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0; }
}

.ei-unit {
  font-size: 0.68rem;
  font-weight: 800;
  color: #a0aec0;
  letter-spacing: 0.04em;
  margin-left: 2px;
  align-self: flex-end;
  padding-bottom: 5px;
}

.ei-back {
  border: none;
  background: none;
  color: #718096;
  font-size: 1rem;
  cursor: pointer;
  padding: 0.25rem 0.35rem;
  border-radius: 5px;
  transition: color 0.14s, background 0.14s;
  display: flex;
  align-items: center;
  line-height: 1;
  margin-left: 1px;
}
.ei-back:hover:not(:disabled) { color: #fc8181; background: rgba(252, 129, 129, 0.12); }
.ei-back:disabled { opacity: 0.4; cursor: not-allowed; }
</style>
