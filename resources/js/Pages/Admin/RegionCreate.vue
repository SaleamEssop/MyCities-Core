<template>
  <AdminLayout>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
      <h1 class="h3 mb-0 text-gray-800">Add Region / Municipality</h1>
      <a :href="route('regions-list')" class="btn btn-sm btn-secondary shadow-sm">
        <i class="fas fa-arrow-left fa-sm"></i> Back to List
      </a>
    </div>

    <div v-if="flash?.message" :class="['alert', flash.class || 'alert-info']">{{ flash.message }}</div>

    <div class="card shadow mb-4">
      <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Region Details</h6>
      </div>
      <div class="card-body">
        <form @submit.prevent="submit">
          <!-- Province & Municipality -->
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label><strong>Province <span class="text-danger">*</span></strong></label>
                <input v-model="form.province" type="text" class="form-control"
                       placeholder="e.g. KwaZulu-Natal" required />
                <div v-if="errors.province" class="text-danger small mt-1">{{ errors.province }}</div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label><strong>Municipality <span class="text-danger">*</span></strong></label>
                <input v-model="form.municipality" type="text" class="form-control"
                       placeholder="e.g. eThekwini" required />
                <div v-if="errors.municipality" class="text-danger small mt-1">{{ errors.municipality }}</div>
              </div>
            </div>
          </div>

          <!-- Default Billing Emails -->
          <div class="card bg-light mb-4">
            <div class="card-body">
              <h6 class="font-weight-bold mb-3">Default Billing Emails</h6>
              <p class="text-muted small mb-3">These apply when no zone is matched for an address.</p>
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Default Water Email</label>
                    <input v-model="form.water_email" type="email" class="form-control"
                           placeholder="water@municipality.gov.za" />
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label>Default Electricity Email</label>
                    <input v-model="form.electricity_email" type="email" class="form-control"
                           placeholder="electricity@municipality.gov.za" />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Zones -->
          <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
              <h6 class="m-0 font-weight-bold text-primary">Billing Zones (Optional)</h6>
              <button type="button" class="btn btn-sm btn-primary" @click="addZone">
                <i class="fas fa-plus fa-sm"></i> Add Zone
              </button>
            </div>
            <div class="card-body">
              <p class="text-muted small" v-if="form.zones.length === 0">
                No zones added. A single municipality with default emails will be used.
                Add zones if this municipality has multiple billing areas with different email addresses
                (e.g. eThekwini has 9 zones).
              </p>
              <div v-for="(zone, idx) in form.zones" :key="idx" class="zone-row border rounded p-3 mb-3 bg-light">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <strong class="text-primary">Zone {{ idx + 1 }}</strong>
                  <button type="button" class="btn btn-sm btn-danger" @click="removeZone(idx)">
                    <i class="fas fa-trash fa-sm"></i>
                  </button>
                </div>
                <div class="row">
                  <div class="col-md-4">
                    <div class="form-group mb-2">
                      <label class="small font-weight-bold">Zone Name <span class="text-danger">*</span></label>
                      <input v-model="zone.zone_name" type="text" class="form-control form-control-sm"
                             placeholder="e.g. Zone 1, Northern Zone" required />
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group mb-2">
                      <label class="small font-weight-bold">Water Email</label>
                      <input v-model="zone.water_email" type="email" class="form-control form-control-sm"
                             placeholder="zone1-water@municipality.gov.za" />
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="form-group mb-2">
                      <label class="small font-weight-bold">Electricity Email</label>
                      <input v-model="zone.electricity_email" type="email" class="form-control form-control-sm"
                             placeholder="zone1-elec@municipality.gov.za" />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" :disabled="saving">
              <span v-if="saving"><i class="fas fa-spinner fa-spin fa-sm mr-1"></i> Saving...</span>
              <span v-else><i class="fas fa-save fa-sm mr-1"></i> Create Region</span>
            </button>
            <a :href="route('regions-list')" class="btn btn-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { reactive, ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const page = usePage()
const flash = computed(() => page.props.flash)
const saving = ref(false)
const errors = ref({})

const form = reactive({
  province: '',
  municipality: '',
  water_email: '',
  electricity_email: '',
  zones: [],
})

function addZone() {
  form.zones.push({ zone_name: '', water_email: '', electricity_email: '' })
}

function removeZone(idx) {
  form.zones.splice(idx, 1)
}

async function submit() {
  saving.value = true
  errors.value = {}
  try {
    await window.axios.post(route('add-region'), {
      province:          form.province,
      municipality:      form.municipality,
      water_email:       form.water_email,
      electricity_email: form.electricity_email,
      zones:             form.zones,
    })
    window.location.href = route('regions-list')
  } catch (e) {
    if (e.response?.status === 422) {
      errors.value = e.response.data.errors || {}
    } else {
      alert('An error occurred. Please try again.')
    }
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.gap-2 { gap: 0.5rem; }
.zone-row { transition: box-shadow 0.2s; }
.zone-row:hover { box-shadow: 0 0.125rem 0.5rem rgba(0,0,0,0.1); }
</style>
