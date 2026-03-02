<template>
  <div
    class="mi"
    :class="{ 'mi--focused': isFocused, 'mi--disabled': disabled }"
    :tabindex="disabled ? -1 : 0"
    @keydown="onKeyDown"
    @focus="onFocus"
    @blur="onBlur"
    @click="onWrapClick"
  >
    <!-- ── Digit slots ── -->
    <div class="mi-display">

      <!-- Whole part: 4 digits (kL) -->
      <div
        v-for="i in 4"
        :key="'w' + i"
        class="mi-slot"
        :class="{
          'mi-slot--active': isFocused && cursorPos === i - 1,
          'mi-slot--zero':   isLeadingZero(i - 1),
        }"
        @click.stop="setCursor(i - 1)"
      >
        {{ digits[i - 1] }}
      </div>

      <!-- Decimal separator -->
      <div class="mi-sep">.</div>

      <!-- Fractional part: 2 digits (hundredths of kL) -->
      <div
        v-for="i in 2"
        :key="'f' + i"
        class="mi-slot mi-slot--frac"
        :class="{
          'mi-slot--active': isFocused && cursorPos === i + 3,
          'mi-slot--zero':   false,
        }"
        @click.stop="setCursor(i + 3)"
      >
        {{ digits[i + 3] }}
      </div>

      <!-- kL label -->
      <div class="mi-unit">kL</div>
    </div>

    <!-- ── Backspace ── -->
    <button
      class="mi-back"
      type="button"
      tabindex="-1"
      :disabled="disabled"
      @click.stop="backspace"
      title="Clear last digit (returns to 0)"
    >
      <i class="fas fa-backspace"></i>
    </button>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '0000.00' },
  disabled:   { type: Boolean, default: false },
  size:       { type: String,  default: 'md' }, // 'sm' | 'md'
})

const emit = defineEmits(['update:modelValue', 'change'])

// ── Internal state ────────────────────────────────────────
const digits    = ref([0, 0, 0, 0, 0, 0]) // [w0, w1, w2, w3, f0, f1]
const cursorPos = ref(0)   // 0–5 (active slot)
const isFocused = ref(false)

// ── Conversion helpers ────────────────────────────────────
function klToDigits (kl) {
  const v     = parseFloat(kl) || 0
  const whole = Math.min(Math.floor(v), 9999)
  const frac  = Math.min(Math.round((v % 1) * 100), 99)
  const ws    = String(whole).padStart(4, '0')
  const fs    = String(frac).padStart(2, '0')
  return [...ws.split(''), ...fs.split('')].map(Number)
}
function digitsToKl () {
  const w = digits.value.slice(0, 4).join('')
  const f = digits.value.slice(4, 6).join('')
  return `${w}.${f}`
}

// ── Sync from prop ────────────────────────────────────────
watch(() => props.modelValue, (v) => {
  if (v !== digitsToKl()) {
    digits.value = klToDigits(v)
  }
}, { immediate: true })

// ── Leading-zero detection (only grey zeros before the first non-zero digit) ─
function isLeadingZero (pos) {
  for (let j = 0; j <= pos; j++) {
    if (digits.value[j] !== 0) return false
  }
  return true
}

// ── Focus / blur ──────────────────────────────────────────
function onFocus ()    { isFocused.value = true }
function onBlur ()     {
  isFocused.value = false
  emit('change', digitsToKl())  // reconciliation runs once when user leaves the field
}
function onWrapClick () {
  if (props.disabled) return
  // If already focused, don't reset cursor (user may have clicked a slot)
  if (!isFocused.value) {
    cursorPos.value = 0
    isFocused.value = true
  }
}
function setCursor (pos) {
  if (props.disabled) return
  cursorPos.value = pos
  if (!isFocused.value) isFocused.value = true
}

// ── Keyboard ──────────────────────────────────────────────
function onKeyDown (e) {
  if (props.disabled) return
  if (e.key >= '0' && e.key <= '9') {
    e.preventDefault()
    enterDigit(parseInt(e.key))
  } else if (e.key === 'Backspace') {
    e.preventDefault()
    backspace()
  } else if (e.key === 'Delete') {
    e.preventDefault()
    clearAll()
  } else if (e.key === 'ArrowLeft') {
    e.preventDefault()
    if (cursorPos.value > 0) cursorPos.value--
  } else if (e.key === 'ArrowRight') {
    e.preventDefault()
    if (cursorPos.value < 5) cursorPos.value++
  } else if (e.key === 'Tab') {
    // Let tab propagate for normal tab navigation
  } else {
    e.preventDefault() // block letters, etc.
  }
}

// ── Digit entry: fill left → right ───────────────────────
function enterDigit (d) {
  const next    = [...digits.value]
  next[cursorPos.value] = d
  digits.value  = next
  // Advance cursor (stop at last slot)
  if (cursorPos.value < 5) cursorPos.value++
  emitChange()
}

// ── Backspace: zero current slot, step back ───────────────
// "Clearing never erases, just returns to 0"
function backspace () {
  const next    = [...digits.value]
  next[cursorPos.value] = 0
  digits.value  = next
  if (cursorPos.value > 0) cursorPos.value--
  emitChange()
}

// ── Delete all → 0000.00 ──────────────────────────────────
function clearAll () {
  digits.value  = [0, 0, 0, 0, 0, 0]
  cursorPos.value = 0
  emitChange()
}

function emitChange () {
  const kl = digitsToKl()
  emit('update:modelValue', kl)
  // 'change' emitted only on blur so parent reconciliation runs after user finishes the reading
}
</script>

<style scoped>
/* ── Wrapper ─────────────────────────────────────────────── */
.mi {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 8px 5px 10px;
  border: 1.5px solid #B0D3DF;
  border-radius: 8px;
  background: #fff;
  box-shadow: 0 1px 4px rgba(50, 148, 184, 0.09);
  cursor: text;
  outline: none;
  transition: border-color 0.15s, box-shadow 0.15s;
  user-select: none;
}
.mi:focus,
.mi--focused {
  border-color: #3294B8;
  box-shadow: 0 0 0 3px rgba(50, 148, 184, 0.13);
}
.mi--disabled {
  background: #f7fafb;
  cursor: not-allowed;
  opacity: 0.65;
}

/* ── Display row ─────────────────────────────────────────── */
.mi-display {
  display: flex;
  align-items: center;
  gap: 3px;
}

/* ── Individual digit slot ───────────────────────────────── */
.mi-slot {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 38px;
  font-size: 1.25rem;
  font-weight: 800;
  font-family: 'Courier New', monospace;
  color: #1a2b3c;
  background: #f7fafb;
  border: 1.5px solid #dce8f0;
  border-radius: 5px;
  transition: border-color 0.12s, background 0.12s, box-shadow 0.12s;
  cursor: pointer;
}
/* Fractional slots: teal colour */
.mi-slot--frac {
  color: #3294B8;
  background: #ebf7fc;
  border-color: #B0D3DF;
}
/* Dim zero slots slightly for readability */
.mi-slot--zero {
  color: #b0c4ce;
}
.mi-slot--frac.mi-slot--zero {
  color: #90c4d4;
}
/* Active (cursor) slot */
.mi-slot--active {
  border-color: #3294B8 !important;
  background: #fff !important;
  box-shadow: 0 0 0 2px rgba(50, 148, 184, 0.18);
  color: #1a2b3c !important;
}
/* Blinking cursor underline inside active slot */
.mi-slot--active::after {
  content: '';
  position: absolute;
  bottom: 4px;
  left: 50%;
  transform: translateX(-50%);
  width: 55%;
  height: 2px;
  background: #3294B8;
  border-radius: 1px;
  animation: mi-blink 1.1s step-end infinite;
}
@keyframes mi-blink {
  0%, 100% { opacity: 1; }
  50%       { opacity: 0; }
}

/* ── Decimal separator ───────────────────────────────────── */
.mi-sep {
  font-size: 1.5rem;
  font-weight: 900;
  color: #3294B8;
  line-height: 1;
  padding: 0 1px;
  margin-top: 2px;
}

/* ── kL unit label ───────────────────────────────────────── */
.mi-unit {
  font-size: 0.68rem;
  font-weight: 800;
  color: #3294B8;
  letter-spacing: 0.04em;
  margin-left: 2px;
  align-self: flex-end;
  padding-bottom: 5px;
}

/* ── Backspace button ────────────────────────────────────── */
.mi-back {
  border: none;
  background: none;
  color: #b0c4ce;
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
.mi-back:hover:not(:disabled) {
  color: #e53e3e;
  background: #fff5f5;
}
.mi-back:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
</style>
