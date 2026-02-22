<template>
  <AdminLayout>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
      <h1 class="h3 mb-0 text-gray-800">Billing Calculator</h1>
    </div>
    <div class="row">
      <div class="col-md-6">
        <div class="card shadow mb-4">
          <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Calculate Bill</h6>
          </div>
          <div class="card-body">
            <form @submit.prevent="calculate">
              <div class="form-group">
                <label>Select User</label>
                <select v-model="form.user_id" class="form-control" @change="loadAccounts">
                  <option value="">Select User</option>
                  <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                </select>
              </div>
              <div class="form-group">
                <label>Select Account</label>
                <select v-model="form.account_id" class="form-control">
                  <option value="">Select Account</option>
                  <option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.account_number }}</option>
                </select>
              </div>
              <div class="form-group">
                <label>Start Date</label>
                <input type="date" v-model="form.start_date" class="form-control">
              </div>
              <div class="form-group">
                <label>End Date</label>
                <input type="date" v-model="form.end_date" class="form-control">
              </div>
              <button type="submit" class="btn btn-primary">Calculate</button>
            </form>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card shadow mb-4">
          <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Results</h6>
          </div>
          <div class="card-body">
            <div v-if="result">
              <p><strong>Account:</strong> {{ result.account_number }}</p>
              <p><strong>Period:</strong> {{ result.start_date }} - {{ result.end_date }}</p>
              <p><strong>Total Consumption:</strong> {{ result.total_consumption }}</p>
              <p><strong>Total Amount:</strong> R{{ result.total_amount }}</p>
              </div>
            <div v-else class="text-muted">Select parameters and calculate</div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ users: { type: Array, default: () => [] } })
const users = ref(props.users || [])
const accounts = ref([])
const result = ref(null)
const form = ref({ user_id: '', account_id: '', start_date: '', end_date: '' })

const loadAccounts = async () => {
  if (!form.value.user_id) return
  try {
    const res = await fetch(`/admin/billing-calculator/users`)
    const data = await res.json()
    accounts.value = data.filter(a => a.user_id === form.value.user_id)
  } catch (e) { console.error(e) }
}

const calculate = async () => {
  try {
    const res = await fetch('/admin/billing-calculator/api/calculate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
      body: JSON.stringify(form.value)
    })
    result.value = await res.json()
  } catch (e) { console.error(e) }
}
</script>

<style scoped>
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
.form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d3e2; border-radius: 0.35rem; box-sizing: border-box; }
.btn { padding: 0.75rem 1.5rem; border-radius: 0.35rem; border: none; }
.btn-primary { background-color: #4e73df; color: #fff; }
.card { border-radius: 0.35rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); }
.card-header { background-color: #f8f9fc; border-bottom: 1px solid #e3e6f0; }
</style>
