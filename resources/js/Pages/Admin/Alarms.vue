<template>
  <AdminLayout>
    <div class="alarms-page">

      <!-- Page header -->
      <div class="page-header">
        <div class="page-header-left">
          <h1 class="page-title"><i class="fas fa-bell"></i> Alarms &amp; Notifications</h1>
          <p class="page-sub">System alarm definitions — each has a unique ID for reference and support.</p>
        </div>
        <div class="page-header-right">
          <span class="alarm-count-chip">{{ alarms.length }} alarm{{ alarms.length !== 1 ? 's' : '' }} defined</span>
        </div>
      </div>

      <!-- Alarm definitions table -->
      <div class="alarms-card">
        <div class="alarms-card-header">
          <span class="alarms-card-title">Alarm Definitions</span>
          <div class="filter-row">
            <button
              v-for="f in filters"
              :key="f.val"
              :class="['filter-btn', activeFilter === f.val ? 'filter-btn--on' : '']"
              @click="activeFilter = f.val"
            >{{ f.label }}</button>
          </div>
        </div>

        <div class="alarms-table-wrap">
          <table class="alarms-table">
            <thead>
              <tr>
                <th class="col-code">ID</th>
                <th class="col-name">Name</th>
                <th class="col-desc">Description</th>
                <th class="col-delivery">Delivery</th>
                <th class="col-severity">Severity</th>
                <th class="col-status">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="alarm in filteredAlarms" :key="alarm.id" :class="!alarm.is_active ? 'row--inactive' : ''">
                <td class="col-code">
                  <span class="alarm-code">{{ alarm.code }}</span>
                </td>
                <td class="col-name">
                  <span class="alarm-name">{{ alarm.name }}</span>
                  <span class="alarm-condition">{{ conditionLabel(alarm.condition_type) }}</span>
                </td>
                <td class="col-desc">{{ alarm.description }}</td>
                <td class="col-delivery">
                  <span :class="['delivery-chip', `delivery-chip--${alarm.delivery_method}`]">
                    <i :class="deliveryIcon(alarm.delivery_method)"></i>
                    {{ alarm.delivery_method }}
                  </span>
                </td>
                <td class="col-severity">
                  <span :class="['severity-badge', `severity-badge--${alarm.severity}`]">
                    {{ alarm.severity }}
                  </span>
                </td>
                <td class="col-status">
                  <span :class="['status-pill', alarm.is_active ? 'status-pill--on' : 'status-pill--off']">
                    <i :class="['fas', alarm.is_active ? 'fa-check-circle' : 'fa-pause-circle']"></i>
                    {{ alarm.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
              </tr>
              <tr v-if="filteredAlarms.length === 0">
                <td colspan="6" class="empty-row">No alarms match this filter.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Delivery methods legend -->
      <div class="legend-card">
        <div class="legend-title">Delivery Methods</div>
        <div class="legend-grid">
          <div class="legend-item" v-for="m in deliveryMethods" :key="m.key">
            <span :class="['delivery-chip', `delivery-chip--${m.key}`]">
              <i :class="m.icon"></i> {{ m.key }}
            </span>
            <span class="legend-desc">{{ m.desc }}</span>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  alarms: { type: Array, default: () => [] },
})

const activeFilter = ref('all')

const filters = [
  { val: 'all',      label: 'All' },
  { val: 'active',   label: 'Active' },
  { val: 'inactive', label: 'Inactive' },
]

const filteredAlarms = computed(() => {
  if (activeFilter.value === 'active')   return props.alarms.filter(a => a.is_active)
  if (activeFilter.value === 'inactive') return props.alarms.filter(a => !a.is_active)
  return props.alarms
})

const deliveryMethods = [
  { key: 'modal',  icon: 'fas fa-window-restore', desc: 'Pop-up dialog shown in the browser when the alarm is triggered.' },
  { key: 'sound',  icon: 'fas fa-volume-up',      desc: 'Audio alert played in the browser.' },
  { key: 'email',  icon: 'fas fa-envelope',        desc: 'Email notification sent to the account owner or admin.' },
  { key: 'push',   icon: 'fas fa-mobile-alt',      desc: 'Push notification to registered devices.' },
]

function deliveryIcon (method) {
  const map = { modal: 'fas fa-window-restore', sound: 'fas fa-volume-up', email: 'fas fa-envelope', push: 'fas fa-mobile-alt' }
  return map[method] || 'fas fa-bell'
}

function conditionLabel (type) {
  const map = {
    no_period_reading: 'No reading in current period',
    high_consumption:  'Consumption above threshold',
    missed_bill:       'Bill overdue',
  }
  return map[type] || type
}
</script>

<style scoped>
.alarms-page {
  max-width: 1100px; margin: 0 auto; padding: 1.5rem 1.5rem 4rem;
  font-family: 'Nunito', sans-serif; display: flex; flex-direction: column; gap: 1.25rem;
  color: #1a2b3c;
}

/* ── Page header ── */
.page-header { display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; }
.page-title  { font-size: 1.35rem; font-weight: 800; color: #1a2b3c; margin: 0; display: flex; align-items: center; gap: 0.5rem; }
.page-title i { color: #3294B8; }
.page-sub    { font-size: 0.84rem; color: #718096; margin: 0.25rem 0 0; }
.alarm-count-chip { background: #e8f4f8; color: #2a7a9e; font-size: 0.8rem; font-weight: 700;
  padding: 0.3rem 0.85rem; border-radius: 999px; }

/* ── Main card ── */
.alarms-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.07); overflow: hidden; }
.alarms-card-header {
  display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;
  padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0;
}
.alarms-card-title { font-weight: 800; font-size: 0.95rem; color: #2d3748; }

/* ── Filters ── */
.filter-row { display: flex; gap: 0.35rem; }
.filter-btn {
  padding: 0.28rem 0.8rem; border: 1.5px solid #e2e8f0; border-radius: 999px;
  background: #fff; color: #718096; font-size: 0.78rem; font-weight: 700; cursor: pointer;
  transition: all 0.15s;
}
.filter-btn--on { background: #3294B8; border-color: #3294B8; color: #fff; }

/* ── Table ── */
.alarms-table-wrap { overflow-x: auto; }
.alarms-table { width: 100%; border-collapse: collapse; font-size: 0.86rem; }
.alarms-table thead th {
  padding: 0.65rem 1rem; text-align: left; font-size: 0.72rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.05em; color: #718096;
  background: #f7fafc; border-bottom: 1px solid #e2e8f0;
}
.alarms-table tbody td { padding: 0.85rem 1rem; border-bottom: 1px solid #f0f4f8; vertical-align: middle; }
.alarms-table tbody tr:last-child td { border-bottom: none; }
.alarms-table tbody tr:hover td { background: #f7fafc; }
.row--inactive td { opacity: 0.55; }

/* Column widths */
.col-code     { width: 100px; }
.col-name     { width: 200px; }
.col-desc     { }
.col-delivery { width: 130px; }
.col-severity { width: 110px; }
.col-status   { width: 110px; }

/* Alarm code */
.alarm-code {
  font-family: 'Courier New', monospace; font-weight: 700; font-size: 0.88rem;
  color: #2a7a9e; background: #e8f4f8; padding: 0.2rem 0.55rem; border-radius: 5px;
}

/* Alarm name + condition */
.alarm-name      { display: block; font-weight: 700; color: #2d3748; }
.alarm-condition { display: block; font-size: 0.76rem; color: #718096; margin-top: 0.15rem; }

/* Delivery chips */
.delivery-chip {
  display: inline-flex; align-items: center; gap: 0.35rem;
  padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.76rem; font-weight: 700;
  text-transform: capitalize;
}
.delivery-chip--modal  { background: #e8f4f8; color: #2a7a9e; }
.delivery-chip--sound  { background: #fef3c7; color: #92400e; }
.delivery-chip--email  { background: #ede9fe; color: #5b21b6; }
.delivery-chip--push   { background: #dcfce7; color: #166534; }

/* Severity badges */
.severity-badge {
  display: inline-block; padding: 0.22rem 0.65rem; border-radius: 999px;
  font-size: 0.73rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
}
.severity-badge--info     { background: #e0f2fe; color: #0369a1; }
.severity-badge--warning  { background: #fef3c7; color: #92400e; }
.severity-badge--critical { background: #fee2e2; color: #991b1b; }

/* Status pill */
.status-pill {
  display: inline-flex; align-items: center; gap: 0.3rem;
  padding: 0.22rem 0.65rem; border-radius: 999px; font-size: 0.76rem; font-weight: 700;
}
.status-pill--on  { background: #dcfce7; color: #166534; }
.status-pill--off { background: #f3f4f6; color: #6b7280; }

/* Empty */
.empty-row { text-align: center; padding: 2rem; color: #a0aec0; font-style: italic; }

/* ── Legend card ── */
.legend-card {
  background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,.07);
  padding: 1rem 1.25rem;
}
.legend-title { font-weight: 800; font-size: 0.88rem; color: #2d3748; margin-bottom: 0.75rem; }
.legend-grid  { display: flex; flex-wrap: wrap; gap: 1rem; }
.legend-item  { display: flex; align-items: center; gap: 0.5rem; }
.legend-desc  { font-size: 0.78rem; color: #718096; max-width: 260px; }
</style>
