<template>
  <AdminLayout>
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
      <h1 class="h3 mb-0 text-gray-800">Users</h1>
      <a :href="route('add-user-form')" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
        <i class="fas fa-plus fa-sm text-white-50"></i> Add User
      </a>
    </div>

    <!-- DataTales Example -->
    <div class="card shadow mb-4">
      <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Users List</h6>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Site</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tfoot>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Site</th>
                <th>Actions</th>
              </tr>
            </tfoot>
            <tbody>
              <tr v-for="user in users" :key="user.id">
                <td>{{ user.name }}</td>
                <td>{{ user.email }}</td>
                <td>{{ user.phone }}</td>
                <td>{{ user.site?.name }}</td>
                <td>
                  <a :href="`/admin/users/edit/${user.id}`" class="btn btn-sm btn-primary">
                    <i class="fas fa-edit"></i>
                  </a>
                  <button @click="deleteUser(user.id)" class="btn btn-sm btn-danger">
                    <i class="fas fa-trash"></i>
                  </button>
                </td>
              </tr>
              <tr v-if="users.length === 0">
                <td colspan="5" class="text-center">No users found</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
const props = defineProps({
  users: {
    type: Array,
    default: () => []
  }
})

const users = ref(props.users || [])

const deleteUser = async (id) => {
  if (confirm('Are you sure you want to delete this user?')) {
    try {
      const response = await fetch(`/admin/users/delete/${id}`, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
        }
      })
      if (response.ok) {
        users.value = users.value.filter(u => u.id !== id)
      }
    } catch (error) {
      console.error('Failed to delete user:', error)
    }
  }
}
</script>

<style scoped>
/* Table Styles */
.table {
  width: 100%;
  border-collapse: collapse;
}

.table thead th,
.table tfoot th {
  padding: 0.75rem;
  vertical-align: top;
  border-bottom: 2px solid #e3e6f0;
  background-color: #f8f9fc;
  color: #3a3b45;
}

.table tbody td {
  padding: 0.75rem;
  vertical-align: top;
  border-top: 1px solid #e3e6f0;
}

.table-responsive {
  overflow-x: auto;
}

/* Button Styles */
.btn {
  padding: 0.25rem 0.75rem;
  font-size: 0.875rem;
  border-radius: 0.35rem;
  margin-right: 0.25rem;
}

.btn-primary {
  background-color: #4e73df;
  border-color: #4e73df;
  color: #fff;
}

.btn-primary:hover {
  background-color: #2e59d9;
  border-color: #2e59d9;
}

.btn-danger {
  background-color: #e74a3b;
  border-color: #e74a3b;
  color: #fff;
}

.btn-danger:hover {
  background-color: #be3a30;
  border-color: #be3a30;
}

/* Card Styles */
.card {
  border-radius: 0.35rem;
  box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}

.card-header {
  background-color: #f8f9fc;
  border-bottom: 1px solid #e3e6f0;
  padding: 1rem 1.25rem;
}

.card-body {
  padding: 1.25rem;
}

.text-gray-800 {
  color: #3a3b45 !important;
}

.text-primary {
  color: #4e73df !important;
}
</style>