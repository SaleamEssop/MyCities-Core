<template>
  <AdminLayout>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
      <h1 class="h3 mb-0 text-gray-800">Meter Readings</h1>
      <a :href="route('add-meter-reading-form')" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
        <i class="fas fa-plus fa-sm text-white-50"></i> Add Reading
      </a>
    </div>
    <div class="card shadow mb-4">
      <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Readings List</h6>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered" width="100%">
            <thead>
              <tr>
                <th>Date</th>
                <th>Meter</th>
                <th>Reading</th>
                <th>Type</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="reading in readings" :key="reading.id">
                <td>{{ reading.reading_date }}</td>
                <td>{{ reading.meter?.meter_number }}</td>
                <td>{{ reading.reading_value }}</td>
                <td>{{ reading.reading_type }}</td>
                <td>
                  <a :href="`/admin/readings/edit/${reading.id}`" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                </td>
              </tr>
              <tr v-if="readings.length === 0">
                <td colspan="5" class="text-center">No readings found</td>
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
const props = defineProps({ readings: { type: Array, default: () => [] } })
const readings = ref(props.readings || [])
</script>

<style scoped>
.table { width: 100%; border-collapse: collapse; }
.table thead th { padding: 0.75rem; vertical-align: top; border-bottom: 2px solid #e3e6f0; background-color: #f8f9fc; }
.table tbody td { padding: 0.75rem; vertical-align: top; border-top: 1px solid #e3e6f0; }
.btn { padding: 0.25rem 0.75rem; font-size: 0.875rem; border-radius: 0.35rem; margin-right: 0.25rem; }
.btn-primary { background-color: #4e73df; color: #fff; }
.card { border-radius: 0.35rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); }
.card-header { background-color: #f8f9fc; border-bottom: 1px solid #e3e6f0; }
</style>
