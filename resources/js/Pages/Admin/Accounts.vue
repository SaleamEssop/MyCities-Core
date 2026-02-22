<template>
  <AdminLayout>
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
      <h1 class="h3 mb-0 text-gray-800">Accounts</h1>
      <a :href="route('accounts.create')" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
        <i class="fas fa-plus fa-sm text-white-50"></i> Add Account
      </a>
    </div>

    <!-- DataTales Example -->
    <div class="card shadow mb-4">
      <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Accounts List</h6>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
            <thead>
              <tr>
                <th>Account Number</th>
                <th>Name</th>
                <th>Email</th>
                <th>Site</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tfoot>
              <tr>
                <th>Account Number</th>
                <th>Name</th>
                <th>Email</th>
                <th>Site</th>
                <th>Actions</th>
              </tr>
            </tfoot>
            <tbody>
              <tr v-for="account in accounts" :key="account.id">
                <td>{{ account.account_number }}</td>
                <td>{{ account.name_on_bill || account.name }}</td>
                <td>{{ account.email }}</td>
                <td>{{ account.site?.name }}</td>
                <td>
                  <a :href="`/admin/account/edit/${account.id}`" class="btn btn-sm btn-primary">
                    <i class="fas fa-edit"></i>
                  </a>
                  <button @click="deleteAccount(account.id)" class="btn btn-sm btn-danger">
                    <i class="fas fa-trash"></i>
                  </button>
                </td>
              </tr>
              <tr v-if="accounts.length === 0">
                <td colspan="5" class="text-center">No accounts found</td>
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
const props = defineProps({
  accounts: {
    type: Array,
    default: () => []
  }
})

const accounts = ref(props.accounts || [])

const deleteAccount = async (id) => {
  if (confirm('Are you sure you want to delete this account?')) {
    try {
      const response = await fetch(`/admin/account/delete/${id}`, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
        }
      })
      if (response.ok) {
        accounts.value = accounts.value.filter(a => a.id !== id)
      }
    } catch (error) {
      console.error('Failed to delete account:', error)
    }
  }
}
</script>

<style scoped>
.table { width: 100%; border-collapse: collapse; }
.table thead th, .table tfoot th { padding: 0.75rem; vertical-align: top; border-bottom: 2px solid #e3e6f0; background-color: #f8f9fc; }
.table tbody td { padding: 0.75rem; vertical-align: top; border-top: 1px solid #e3e6f0; }
.table-responsive { overflow-x: auto; }
.btn { padding: 0.25rem 0.75rem; font-size: 0.875rem; border-radius: 0.35rem; margin-right: 0.25rem; }
.btn-primary { background-color: #4e73df; border-color: #4e73df; color: #fff; }
.btn-primary:hover { background-color: #2e59d9; }
.btn-danger { background-color: #e74a3b; border-color: #e74a3b; color: #fff; }
.btn-danger:hover { background-color: #be3a30; }
.card { border-radius: 0.35rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); }
.card-header { background-color: #f8f9fc; border-bottom: 1px solid #e3e6f0; }
.text-gray-800 { color: #3a3b45 !important; }
.text-primary { color: #4e73df !important; }
</style>