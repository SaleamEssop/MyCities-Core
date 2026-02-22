<template>
  <AdminLayout>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
      <h1 class="h3 mb-0 text-gray-800">Settings</h1>
    </div>
    <div class="card shadow mb-4">
      <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Application Settings</h6>
      </div>
      <div class="card-body">
        <form @submit.prevent="saveSettings">
          <div class="form-group">
            <label>Application Name</label>
            <input type="text" v-model="settings.app_name" class="form-control">
          </div>
          <div class="form-group">
            <label>Currency</label>
            <select v-model="settings.currency" class="form-control">
              <option value="ZAR">ZAR (South African Rand)</option>
              <option value="USD">USD (US Dollar)</option>
            </select>
          </div>
          <div class="form-group">
            <label>Timezone</label>
            <select v-model="settings.timezone" class="form-control">
              <option value="Africa/Johannesburg">Africa/Johannesburg (SAST)</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ settings: { type: Object, default: () => ({}) } })
const settings = ref(props.settings || { app_name: 'MyCities', currency: 'ZAR', timezone: 'Africa/Johannesburg' })

const saveSettings = async () => {
  try {
    await fetch('/admin/settings', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
      body: JSON.stringify(settings.value)
    })
    alert('Settings saved!')
  } catch (error) {
    console.error(error)
  }
}
</script>

<style scoped>
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
.form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d3e2; border-radius: 0.35rem; }
.btn { padding: 0.75rem 1.5rem; border-radius: 0.35rem; border: none; }
.btn-primary { background-color: #4e73df; color: #fff; }
.card { border-radius: 0.35rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); }
.card-header { background-color: #f8f9fc; border-bottom: 1px solid #e3e6f0; }
</style>
