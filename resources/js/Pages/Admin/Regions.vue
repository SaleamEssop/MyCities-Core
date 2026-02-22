<template>
  <AdminLayout>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
      <h1 class="h3 mb-0 text-gray-800">Regions / Municipalities</h1>
      <a :href="route('add-region-form')" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
        <i class="fas fa-plus fa-sm text-white-50"></i> Add Region
      </a>
    </div>
    <div class="card shadow mb-4">
      <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Regions List</h6>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered" width="100%">
            <thead>
              <tr>
                <th>Province</th>
                <th>Municipality</th>
                <th>Default Water Email</th>
                <th>Default Electricity Email</th>
                <th>Zones</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="region in regions" :key="region.id">
                <td>{{ region.province || region.name || '—' }}</td>
                <td>{{ region.municipality || '—' }}</td>
                <td>{{ region.water_email || '—' }}</td>
                <td>{{ region.electricity_email || '—' }}</td>
                <td>
                  <span class="badge badge-info">{{ region.zones_count || 0 }}</span>
                </td>
                <td>
                  <a :href="`/admin/region/edit/${region.id}`" class="btn btn-sm btn-primary">
                    <i class="fas fa-edit"></i>
                  </a>
                  <a :href="`/admin/region/delete/${region.id}`"
                     class="btn btn-sm btn-danger ml-1"
                     onclick="return confirm('Delete this region?')">
                    <i class="fas fa-trash"></i>
                  </a>
                </td>
              </tr>
              <tr v-if="regions.length === 0">
                <td colspan="6" class="text-center text-muted">No regions found. Click "Add Region" to get started.</td>
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

const props = defineProps({ regions: { type: Array, default: () => [] } })
const regions = ref(props.regions || [])
</script>

<style scoped>
.table { width: 100%; border-collapse: collapse; }
.table thead th { padding: 0.75rem; vertical-align: top; border-bottom: 2px solid #e3e6f0; background-color: #f8f9fc; font-size: 0.85rem; }
.table tbody td { padding: 0.75rem; vertical-align: middle; border-top: 1px solid #e3e6f0; font-size: 0.875rem; }
.btn { padding: 0.25rem 0.75rem; font-size: 0.875rem; border-radius: 0.35rem; margin-right: 0.25rem; }
.btn-primary { background-color: #4e73df; color: #fff; border-color: #4e73df; }
.btn-danger { background-color: #e74a3b; color: #fff; border-color: #e74a3b; }
.badge { display: inline-block; padding: 0.25em 0.6em; font-size: 75%; font-weight: 700; border-radius: 0.35rem; }
.badge-info { background-color: #36b9cc; color: #fff; }
.card { border-radius: 0.35rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); }
.card-header { background-color: #f8f9fc; border-bottom: 1px solid #e3e6f0; }
</style>
