<template>
  <AdminLayout>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
      <h1 class="h3 mb-0 text-gray-800">Alarms</h1>
    </div>
    <div class="card shadow mb-4">
      <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">System Alarms</h6>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered" width="100%">
            <thead>
              <tr>
                <th>Time</th>
                <th>Type</th>
                <th>Message</th>
                <th>Severity</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="alarm in alarms" :key="alarm.id">
                <td>{{ alarm.timestamp }}</td>
                <td>{{ alarm.type }}</td>
                <td>{{ alarm.message }}</td>
                <td><span :class="['badge', `badge-${getSeverityColor(alarm.severity)}`]">{{ alarm.severity }}</span></td>
                <td>{{ alarm.status }}</td>
              </tr>
              <tr v-if="alarms.length === 0">
                <td colspan="5" class="text-center">No alarms</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ alarms: { type: Array, default: () => [] } })
const alarms = ref(props.alarms || [])

const getSeverityColor = (severity) => {
  const colors = { 'info': 'info', 'warning': 'warning', 'error': 'danger', 'critical': 'dark' }
  return colors[severity] || 'secondary'
}
</script>

<style scoped>
.table { width: 100%; border-collapse: collapse; }
.table thead th { padding: 0.75rem; vertical-align: top; border-bottom: 2px solid #e3e6f0; background-color: #f8f9fc; }
.table tbody td { padding: 0.75rem; vertical-align: top; border-top: 1px solid #e3e6f0; }
.badge { padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; }
.badge-info { background-color: #36b9cc; color: #fff; }
.badge-warning { background-color: #f6c23e; color: #fff; }
.badge-danger { background-color: #e74a3b; color: #fff; }
.badge-dark { background-color: #5a5c69; color: #fff; }
.card { border-radius: 0.35rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); }
.card-header { background-color: #f8f9fc; border-bottom: 1px solid #e3e6f0; }
</style>
