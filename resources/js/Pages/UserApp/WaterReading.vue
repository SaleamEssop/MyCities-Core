<template>
  <UserAppLayout title="ENTER READINGS" :showBack="true">

    <!-- ORANGE PERIOD SUBHEADER -->
    <div class="reading-subheader" v-if="periodLabel">
      <button class="reading-nav-btn" disabled>◄</button>
      <span>Period: {{ periodLabel }}</span>
      <button class="reading-nav-btn" disabled>►</button>
    </div>

    <!-- SECTION HEADER: Water -->
    <div class="reading-section-header">
      <i class="fas fa-tint reading-type-icon reading-type-icon--water"></i>
      <span class="reading-type-label">Water</span>
    </div>

    <!-- METER INFO -->
    <div class="reading-meter-info">
      Meter Number #{{ meter.meter_number }}
    </div>

    <!-- READING HISTORY LIST -->
    <div class="reading-history-list">
      <div v-if="meter.start_date" class="reading-history-row reading-history-row--start">
        <span class="rh-date">Start Reading {{ meter.start_date }}</span>
        <span class="rh-value">{{ meter.start_reading }}</span>
        <span class="rh-badge rh-badge--estimated">Estimated</span>
      </div>

      <div v-for="r in readings" :key="r.id" class="reading-history-row">
        <span class="rh-date">{{ r.reading_date }}</span>
        <span class="rh-value">{{ r.reading_value }}</span>
        <span class="rh-badge" :class="badgeClass(r.reading_type)">{{ r.reading_type }}</span>
      </div>

      <div v-if="readings.length === 0 && !addingNew" class="reading-empty">
        Start your first reading now. Once more than 24 hours have passed you can add another reading.
      </div>
    </div>

    <!-- ADD NEW READING TOGGLE -->
    <button v-if="!addingNew" class="reading-add-btn" @click="addingNew = true">
      + Add new reading
    </button>

    <!-- DATE SELECTOR (when adding) -->
    <div v-if="addingNew" class="reading-date-row">
      <label class="ua-label">Reading Date</label>
      <input type="date" v-model="readingDate" class="ua-input" :max="today">
    </div>

    <!-- METER DISPLAY -->
    <MeterDisplay v-if="addingNew" :digits="digits" type="water" />

    <!-- KEYPAD — no decimal key (right-to-left input handles placement) -->
    <NumericKeypad
      v-if="addingNew"
      :showDecimal="false"
      @digit="onDigit"
      @backspace="onBackspace"
    />

    <!-- ACTION BUTTONS -->
    <div v-if="addingNew" class="reading-actions">
      <button class="reading-btn-cancel" @click="cancelAdd">CANCEL</button>
      <button class="reading-btn-enter" @click="submitReading" :disabled="submitting || !hasInput">
        ENTER
      </button>
    </div>

    <!-- FEEDBACK -->
    <div v-if="feedbackMsg" class="reading-feedback" :class="feedbackOk ? 'reading-feedback--ok' : 'reading-feedback--err'">
      {{ feedbackMsg }}
    </div>

  </UserAppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import UserAppLayout  from '@/Layouts/UserAppLayout.vue'
import NumericKeypad  from '@/components/NumericKeypad.vue'
import MeterDisplay   from '@/components/MeterDisplay.vue'

const props = defineProps({
  meter:       { type: Object, required: true },
  readings:    { type: Array,  default: () => [] },
  periodLabel: { type: String, default: null },
})

const addingNew   = ref(false)
const submitting  = ref(false)
const feedbackMsg = ref('')
const feedbackOk  = ref(true)
const readingDate = ref(new Date().toISOString().split('T')[0])
const today       = new Date().toISOString().split('T')[0]

// 6-element digits array — right-to-left input
// indices 0–3 = integer part (kL), 4–5 = decimal part
const digits = ref([0, 0, 0, 0, 0, 0])

const hasInput = computed(() => digits.value.some(d => d !== 0))

const onDigit = (d) => {
  // Push all left, new digit enters at position 5
  digits.value = [...digits.value.slice(1), parseInt(d)]
}

const onBackspace = () => {
  // Shift all right, zero enters at position 0
  digits.value = [0, ...digits.value.slice(0, 5)]
}

// Stored value: e.g. digits [0,0,0,4,5,0] → 4.50
const numericValue = computed(() => {
  const intStr = digits.value.slice(0, 4).join('')
  const decStr = digits.value.slice(4, 6).join('')
  return parseFloat(`${intStr}.${decStr}`)
})

const cancelAdd = () => {
  addingNew.value = false
  digits.value    = [0, 0, 0, 0, 0, 0]
  feedbackMsg.value = ''
}

const submitReading = async () => {
  if (numericValue.value <= 0) {
    feedbackMsg.value = 'Please enter a reading greater than zero.'
    feedbackOk.value  = false
    return
  }

  submitting.value  = true
  feedbackMsg.value = ''

  try {
    const res = await fetch(route('user.api.reading.store'), {
      method:  'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        'Accept':        'application/json',
      },
      body: JSON.stringify({
        meter_id:      props.meter.id,
        reading_date:  readingDate.value,
        reading_value: numericValue.value,
        reading_type:  'Actual',
      }),
    })

    if (res.ok) {
      feedbackMsg.value = 'Reading saved successfully.'
      feedbackOk.value  = true
      addingNew.value   = false
      digits.value      = [0, 0, 0, 0, 0, 0]
      router.reload({ only: ['readings'] })
    } else {
      const err = await res.json().catch(() => ({}))
      feedbackMsg.value = err?.message ?? 'Error saving reading. Please try again.'
      feedbackOk.value  = false
    }
  } catch {
    feedbackMsg.value = 'Network error. Please check your connection.'
    feedbackOk.value  = false
  } finally {
    submitting.value = false
  }
}

const badgeClass = (type) => ({
  'rh-badge--actual':      type === 'Actual',
  'rh-badge--estimated':   type === 'Estimated',
  'rh-badge--provisional': type === 'Provisional',
})
</script>

<style scoped>
.reading-subheader {
  background: var(--ua-orange, #FF9800);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 12px;
  font-size: 0.76rem;
  font-weight: 700;
}

.reading-nav-btn {
  background: rgba(255,255,255,0.2);
  border: none;
  color: #fff;
  border-radius: 4px;
  width: 28px;
  height: 28px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.reading-section-header {
  background: var(--ua-card, #fff);
  padding: 12px 16px 10px;
  display: flex;
  align-items: center;
  gap: 10px;
  border-bottom: 1px solid var(--ua-divider, #E0E0E0);
}

.reading-type-icon        { font-size: 1.3rem; }
.reading-type-icon--water { color: var(--ua-water, #2196F3); }

.reading-type-label {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--ua-text, #212121);
}

.reading-meter-info {
  padding: 6px 16px;
  font-size: 0.8rem;
  color: var(--ua-text-secondary, #757575);
  background: var(--ua-card, #fff);
  border-bottom: 1px solid var(--ua-divider, #E0E0E0);
}

.reading-history-list {
  background: var(--ua-card, #fff);
  padding: 0 16px;
}

.reading-history-row {
  display: flex;
  align-items: center;
  padding: 7px 0;
  border-bottom: 1px solid var(--ua-divider, #E0E0E0);
  font-size: 0.8rem;
  gap: 8px;
}

.reading-history-row--start { color: var(--ua-text-secondary, #757575); }

.rh-date   { flex: 1; color: var(--ua-text-secondary, #757575); font-size: 0.78rem; }
.rh-value  { font-weight: 700; font-family: 'Courier New', monospace; min-width: 80px; color: var(--ua-text, #212121); }

.rh-badge {
  font-size: 0.62rem;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 10px;
  text-transform: uppercase;
  white-space: nowrap;
}

.rh-badge--actual      { background: #E8F5E9; color: #2E7D32; }
.rh-badge--estimated   { background: #F5F5F5; color: #757575; }
.rh-badge--provisional { background: #FFF8E1; color: #E65100; }

.reading-empty {
  padding: 16px 0;
  font-size: 0.8rem;
  color: var(--ua-text-secondary, #757575);
  text-align: center;
  line-height: 1.5;
}

.reading-add-btn {
  display: block;
  width: calc(100% - 32px);
  margin: 12px 16px;
  padding: 10px;
  background: none;
  border: 1.5px dashed var(--ua-primary, #009BA4);
  border-radius: var(--ua-radius-sm, 4px);
  color: var(--ua-primary, #009BA4);
  font-size: 0.9rem;
  font-weight: 700;
  font-family: 'Nunito', sans-serif;
  cursor: pointer;
  transition: background 0.15s;
}

.reading-add-btn:hover { background: #E0F7FA; }

.reading-date-row {
  padding: 10px 16px 4px;
  background: var(--ua-card, #fff);
}

.ua-label {
  display: block;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--ua-text-secondary, #757575);
  margin-bottom: 4px;
}

.ua-input {
  width: 100%;
  padding: 10px 14px;
  border: 1.5px solid var(--ua-divider, #E0E0E0);
  border-radius: var(--ua-radius-sm, 4px);
  font-size: 0.95rem;
  font-family: 'Nunito', sans-serif;
  box-sizing: border-box;
  color: var(--ua-text, #212121);
}

.ua-input:focus { outline: none; border-color: var(--ua-primary, #009BA4); }

.reading-actions {
  display: flex;
  gap: 12px;
  padding: 10px 16px 20px;
  background: var(--ua-card, #fff);
}

.reading-btn-cancel,
.reading-btn-enter {
  flex: 1;
  padding: 14px;
  border-radius: var(--ua-radius-sm, 4px);
  font-size: 0.95rem;
  font-weight: 700;
  font-family: 'Nunito', sans-serif;
  cursor: pointer;
  letter-spacing: 0.05em;
  transition: background 0.15s, border-color 0.15s;
}

.reading-btn-cancel {
  background: var(--ua-card, #fff);
  border: 2px solid var(--ua-primary, #009BA4);
  color: var(--ua-primary, #009BA4);
}

.reading-btn-cancel:hover { background: #E0F7FA; }

.reading-btn-enter {
  background: var(--ua-primary, #009BA4);
  border: 2px solid var(--ua-primary, #009BA4);
  color: #fff;
}

.reading-btn-enter:hover    { background: var(--ua-primary-dark, #007A82); border-color: var(--ua-primary-dark, #007A82); }
.reading-btn-enter:disabled { opacity: 0.55; cursor: not-allowed; }

.reading-feedback {
  margin: 8px 16px;
  padding: 10px 14px;
  border-radius: var(--ua-radius-sm, 4px);
  font-size: 0.85rem;
}

.reading-feedback--ok  { background: #E8F5E9; color: #2E7D32; }
.reading-feedback--err { background: #FFEBEE; color: #C62828; }
</style>
