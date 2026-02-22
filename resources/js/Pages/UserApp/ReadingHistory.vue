<template>
  <UserAppLayout title="READING HISTORY" :showBack="true">
    <div class="rh-page">

      <div v-if="readings.length === 0" class="rh-empty">
        <i class="fas fa-history"></i>
        <p>No readings recorded yet.</p>
      </div>

      <div v-for="r in readings" :key="r.id" class="rh-row">
        <div class="rh-row-icon">
          <i
            class="fas"
            :class="r.meter_type === 'water' ? 'fa-tint' : 'fa-bolt'"
            :style="{ color: r.meter_type === 'water' ? 'var(--ua-water)' : 'var(--ua-electricity)' }"
          ></i>
        </div>
        <div class="rh-row-body">
          <span class="rh-row-date">{{ r.reading_date }}</span>
          <span class="rh-row-value">{{ r.reading_value }}</span>
        </div>
        <span class="rh-badge" :class="badgeClass(r.reading_type)">{{ r.reading_type }}</span>
      </div>

    </div>
  </UserAppLayout>
</template>

<script setup>
import UserAppLayout from '@/Layouts/UserAppLayout.vue'

defineProps({
  readings: { type: Array, default: () => [] },
})

const badgeClass = (type) => ({
  'rh-badge--actual':      type === 'Actual',
  'rh-badge--estimated':   type === 'Estimated',
  'rh-badge--provisional': type === 'Provisional',
})
</script>

<style scoped>
.rh-page {
  background: var(--ua-card, #fff);
  min-height: 100%;
}

.rh-empty {
  padding: 56px 24px;
  text-align: center;
  color: var(--ua-text-secondary, #757575);
}

.rh-empty i {
  font-size: 2.8rem;
  margin-bottom: 14px;
  display: block;
  color: var(--ua-grey, #9E9E9E);
}

.rh-row {
  display: flex;
  align-items: center;
  padding: 12px 16px;
  border-bottom: 1px solid var(--ua-divider, #E0E0E0);
  gap: 12px;
}

.rh-row-icon {
  width: 28px;
  text-align: center;
  font-size: 1.1rem;
  flex-shrink: 0;
}

.rh-row-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.rh-row-date  { font-size: 0.76rem; color: var(--ua-text-secondary, #757575); }
.rh-row-value { font-size: 0.95rem; font-weight: 700; font-family: 'Courier New', monospace; color: var(--ua-text, #212121); }

.rh-badge {
  font-size: 0.62rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 10px;
  text-transform: uppercase;
  white-space: nowrap;
  flex-shrink: 0;
}

.rh-badge--actual      { background: #E8F5E9; color: #2E7D32; }
.rh-badge--estimated   { background: #F5F5F5; color: #757575; }
.rh-badge--provisional { background: #FFF8E1; color: #E65100; }
</style>
