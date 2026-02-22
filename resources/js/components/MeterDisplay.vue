<template>
  <div class="meter-display">
    <template v-if="type === 'water'">
      <span class="meter-int">{{ intPart }}</span>
      <span class="meter-sep"> - </span>
      <span class="meter-dec">{{ decPart }}</span>
    </template>
    <template v-else>
      <span class="meter-int">{{ allDigits }}</span>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  digits: { type: Array,  required: true }, // 6-element array of numbers (0–9)
  type:   { type: String, default: 'water' },
})

const intPart  = computed(() => props.digits.slice(0, 4).map(String).join(''))
const decPart  = computed(() => props.digits.slice(4, 6).map(String).join(''))
const allDigits = computed(() => props.digits.map(String).join(''))
</script>

<style scoped>
.meter-display {
  background: var(--ua-card);
  border: 2px solid var(--ua-primary);
  border-radius: var(--ua-radius);
  margin: 12px 16px;
  padding: 16px;
  text-align: center;
  font-size: 2.5rem;
  font-weight: 700;
  letter-spacing: 0.12em;
  color: var(--ua-text);
  font-family: 'Courier New', monospace;
}

.meter-sep {
  color: var(--ua-primary);
  font-size: 2rem;
}
</style>
