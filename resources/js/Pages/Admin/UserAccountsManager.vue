<template>
  <AdminLayout>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
      <h1 class="h3 mb-0 text-gray-800">User Accounts - Manager</h1>
    </div>
    <div class="card shadow mb-4">
      <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Search &amp; Manage Users</h6>
      </div>
      <div class="card-body">
        <div class="form-group">
          <input type="text" v-model="searchQuery" @input="searchUsers"
                 class="form-control"
                 placeholder="Search by name, email or phone number..." />
        </div>
        <div class="table-responsive">
          <table class="table table-bordered" width="100%">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Sites</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="users.length === 0">
                <td colspan="5" class="text-center text-muted">
                  {{ searchQuery.length > 0 ? 'No results found.' : 'No users found.' }}
                </td>
              </tr>
              <tr v-for="user in users" :key="user.id">
                <td>{{ user.name }}</td>
                <td>{{ user.email }}</td>
                <td>{{ user.phone || '—' }}</td>
                <td>{{ user.sites_count || 0 }}</td>
                <td>
                  <button @click="editUser(user)" class="btn btn-sm btn-primary" title="Edit User">
                    <i class="fas fa-edit"></i>
                  </button>
                  <button v-if="user.first_account_id"
                          @click="viewBilling(user)"
                          class="btn btn-sm btn-info ml-1"
                          title="View Billing">
                    <i class="fas fa-file-invoice-dollar"></i>
                  </button>
                  <button v-if="user.first_account_id"
                          @click="viewDashboard(user)"
                          class="btn btn-sm btn-success ml-1"
                          title="View Dashboard">
                    <i class="fas fa-tachometer-alt"></i>
                  </button>
                  <button @click="deleteUser(user.id)" class="btn btn-sm btn-danger ml-1" title="Delete User">
                    <i class="fas fa-trash"></i>
                  </button>
                </td>
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

const props = defineProps({ users: { type: Array, default: () => [] } })
const users = ref(props.users || [])
const searchQuery = ref('')
let searchTimer = null

const searchUsers = () => {
  clearTimeout(searchTimer)
  if (searchQuery.value.length < 2) {
    users.value = props.users || []
    return
  }
  searchTimer = setTimeout(async () => {
    try {
      const res = await window.axios.get(route('user-accounts.manager.search'), {
        params: { q: searchQuery.value }
      })
      // Response shape: { status: 200, data: [...] }
      users.value = res.data?.data || res.data || []
    } catch (e) { console.error('Search failed:', e) }
  }, 300)
}

const editUser    = (user) => { window.location.href = `/admin/user-accounts/${user.id}/edit` }
const viewBilling = (user) => { window.location.href = route('user-accounts.billing', { accountId: user.first_account_id }) }
const viewDashboard = (user) => { window.location.href = route('user-accounts.dashboard', { accountId: user.first_account_id }) }

const deleteUser = async (id) => {
  if (!confirm('Delete this user and all their data?')) return
  try {
    await window.axios.delete(route('user-accounts.manager.delete-user', { id }))
    users.value = users.value.filter(u => u.id !== id)
  } catch (e) {
    alert('Failed to delete user.')
    console.error(e)
  }
}
</script>

<style scoped>
.form-group { margin-bottom: 1rem; }
.form-control { width: 100%; padding: 0.75rem; border: 1px solid #d1d3e2; border-radius: 0.35rem; }
.table { width: 100%; border-collapse: collapse; }
.table thead th { padding: 0.75rem; border-bottom: 2px solid #e3e6f0; background-color: #f8f9fc; font-size: 0.85rem; }
.table tbody td { padding: 0.75rem; border-top: 1px solid #e3e6f0; vertical-align: middle; }
.btn { padding: 0.25rem 0.6rem; font-size: 0.8rem; border-radius: 0.35rem; }
.btn-primary  { background-color: #4e73df; color: #fff; border-color: #4e73df; }
.btn-info     { background-color: #36b9cc; color: #fff; border-color: #36b9cc; }
.btn-success  { background-color: #1cc88a; color: #fff; border-color: #1cc88a; }
.btn-danger   { background-color: #e74a3b; color: #fff; border-color: #e74a3b; }
.ml-1 { margin-left: 0.25rem; }
.card { border-radius: 0.35rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58,59,69,0.15); }
.card-header { background-color: #f8f9fc; border-bottom: 1px solid #e3e6f0; }
</style>
