<template>
  <AdminLayout>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
      <h1 class="h3 mb-0 text-gray-800">User Setup</h1>
      <a :href="route('account-manager')" class="btn btn-primary btn-sm shadow-sm">
        <i class="fas fa-users-cog fa-sm mr-1"></i> Go to Account Manager
      </a>
    </div>

    <!-- Flash -->
    <div v-if="flash.msg" class="alert" :class="flash.success ? 'alert-success' : 'alert-danger'" role="alert">
      <span v-if="flash.newPassword">
        New password: <strong>{{ flash.newPassword }}</strong> — please note this down.
      </span>
      <span v-else>{{ flash.msg }}</span>
      <button type="button" class="close ml-2" @click="flash.msg = ''">&times;</button>
    </div>

    <div class="row">

      <!-- LEFT: Create User + Test Utilities -->
      <div class="col-lg-5">

        <!-- ── Create User ── -->
        <div class="card shadow mb-4">
          <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Create New User</h6>
          </div>
          <div class="card-body">
            <form @submit.prevent="createUser">
              <div class="form-group">
                <label>Full Name <span class="text-danger">*</span></label>
                <input v-model="newUser.name" type="text" class="form-control" placeholder="John Smith" required />
              </div>
              <div class="form-group">
                <label>Email <span class="text-danger">*</span></label>
                <input v-model="newUser.email" type="email" class="form-control" placeholder="john@example.com" required />
              </div>
              <div class="form-group">
                <label>Phone <span class="text-danger">*</span></label>
                <input v-model="newUser.phone" type="text" class="form-control" placeholder="0821234567" required />
              </div>
              <div class="form-group">
                <label>Password <span class="text-danger">*</span></label>
                <input v-model="newUser.password" type="text" class="form-control" placeholder="min 6 characters" required />
              </div>
              <button type="submit" class="btn btn-primary btn-block" :disabled="creating">
                {{ creating ? 'Creating…' : 'Create User' }}
              </button>
            </form>
          </div>
        </div>

        <!-- ── Quick Test User ── -->
        <div class="card shadow mb-4">
          <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-success">Quick Test User</h6>
            <span class="badge badge-success">Demo</span>
          </div>
          <div class="card-body">
            <p class="small text-muted mb-3">Creates a demo user with water &amp; electricity meters and seeded readings.</p>
            <div class="form-row">
              <div class="form-group col-6">
                <label>Region</label>
                <select v-model="testForm.region_id" class="form-control" @change="loadTestTariffs">
                  <option value="">Auto (first)</option>
                  <option v-for="r in regions" :key="r.id" :value="r.id">{{ r.name }}</option>
                </select>
              </div>
              <div class="form-group col-6">
                <label>Tariff</label>
                <select v-model="testForm.tariff_id" class="form-control" :disabled="!testForm.region_id">
                  <option value="">Auto (first)</option>
                  <option v-for="t in testTariffs" :key="t.id" :value="t.id">{{ t.template_name }}</option>
                </select>
              </div>
            </div>
            <div class="form-row align-items-end">
              <div class="form-group col-6">
                <label>Seed Months</label>
                <select v-model.number="testForm.seed_months" class="form-control">
                  <option :value="3">3 months</option>
                  <option :value="6">6 months</option>
                  <option :value="12">12 months</option>
                </select>
              </div>
              <div class="form-group col-6">
                <button class="btn btn-success btn-block" @click="createTestUser" :disabled="creatingTest">
                  {{ creatingTest ? 'Creating…' : 'Create Test User' }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Populate Existing User ── -->
        <div class="card shadow mb-4">
          <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-info">Populate Existing User</h6>
            <span class="badge badge-info">Reseed</span>
          </div>
          <div class="card-body">
            <p class="small text-muted mb-3">Deletes existing readings &amp; bills and replaces them with fresh demo data.</p>
            <div class="form-group">
              <label>Select User</label>
              <select v-model="populateForm.user_id" class="form-control">
                <option value="">— Select User —</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
              </select>
            </div>
            <div class="form-row align-items-end">
              <div class="form-group col-6">
                <label>Seed Months</label>
                <select v-model.number="populateForm.seed_months" class="form-control">
                  <option :value="3">3 months</option>
                  <option :value="6">6 months</option>
                  <option :value="12">12 months</option>
                </select>
              </div>
              <div class="form-group col-6">
                <button class="btn btn-info btn-block" @click="populateUser"
                        :disabled="!populateForm.user_id || populating">
                  {{ populating ? 'Populating…' : 'Populate' }}
                </button>
              </div>
            </div>
          </div>
        </div>

      </div><!-- /LEFT -->

      <!-- RIGHT: Users Table -->
      <div class="col-lg-7">
        <div class="card shadow mb-4">
          <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Existing Users ({{ users.length }})</h6>
            <input v-model="tableSearch" type="text" class="form-control form-control-sm"
                   style="max-width:220px;" placeholder="Filter by name or email…" />
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-bordered table-hover table-sm mb-0">
                <thead class="thead-light">
                  <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th style="width:140px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="filteredUsers.length === 0">
                    <td colspan="5" class="text-center text-muted py-3">No users found.</td>
                  </tr>
                  <tr v-for="u in filteredUsers" :key="u.id">
                    <td>{{ u.name }}</td>
                    <td>{{ u.email }}</td>
                    <td>{{ u.phone || '—' }}</td>
                    <td>
                      <span class="badge" :class="u.active !== false ? 'badge-success' : 'badge-secondary'">
                        {{ u.active !== false ? 'Active' : 'Suspended' }}
                      </span>
                    </td>
                    <td>
                      <button @click="resetPassword(u)" class="btn btn-xs btn-warning mr-1" title="Reset Password">
                        <i class="fas fa-key"></i>
                      </button>
                      <button @click="toggleStatus(u)" class="btn btn-xs mr-1"
                              :class="u.active !== false ? 'btn-secondary' : 'btn-success'"
                              :title="u.active !== false ? 'Suspend' : 'Activate'">
                        <i :class="u.active !== false ? 'fas fa-ban' : 'fas fa-check'"></i>
                      </button>
                      <button @click="deleteUser(u)" class="btn btn-xs btn-danger" title="Delete User">
                        <i class="fas fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div><!-- /RIGHT -->

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  users:      { type: Array, default: () => [] },
  regions:    { type: Array, default: () => [] },
  meterTypes: { type: Array, default: () => [] },
})

const users   = ref([...props.users])
const regions = ref(props.regions || [])

const flash = ref({ msg: '', success: true, newPassword: '' })
function showFlash(msg, success = true, newPassword = '') {
  flash.value = { msg, success, newPassword }
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

// ── Table filter ──────────────────────────────────────────────────────────────
const tableSearch   = ref('')
const filteredUsers = computed(() => {
  const q = tableSearch.value.toLowerCase()
  if (!q) return users.value
  return users.value.filter(u =>
    (u.name  || '').toLowerCase().includes(q) ||
    (u.email || '').toLowerCase().includes(q) ||
    (u.phone || '').toLowerCase().includes(q)
  )
})

// ── Create User ───────────────────────────────────────────────────────────────
const newUser = ref({ name: '', email: '', phone: '', password: '' })
const creating = ref(false)

async function createUser() {
  creating.value = true
  try {
    const res = await window.axios.post(route('user.create'), {
      name:           newUser.value.name,
      email:          newUser.value.email,
      contact_number: newUser.value.phone,
      password:       newUser.value.password,
    })
    if (res.data.status === 200 || res.data.success) {
      users.value.push({
        id:     res.data.user_id,
        name:   newUser.value.name,
        email:  newUser.value.email,
        phone:  newUser.value.phone,
        active: true,
      })
      newUser.value = { name: '', email: '', phone: '', password: '' }
      showFlash(`User created successfully.`, true)
    } else {
      showFlash(res.data.message || 'Error creating user.', false)
    }
  } catch (e) {
    showFlash(e.response?.data?.message || e.message, false)
  } finally {
    creating.value = false
  }
}

// ── Reset Password ────────────────────────────────────────────────────────────
async function resetPassword(u) {
  if (!confirm(`Reset password for ${u.name}?`)) return
  try {
    const res = await window.axios.post(route('user.reset-password'), { user_id: u.id })
    if (res.data.success) {
      showFlash(`Password reset for ${u.name}.`, true, res.data.new_password)
    } else {
      showFlash(res.data.message, false)
    }
  } catch (e) {
    showFlash(e.response?.data?.message || e.message, false)
  }
}

// ── Toggle Status ─────────────────────────────────────────────────────────────
async function toggleStatus(u) {
  const action = u.active !== false ? 'suspend' : 'activate'
  if (!confirm(`${action.charAt(0).toUpperCase() + action.slice(1)} ${u.name}?`)) return
  try {
    const res = await window.axios.patch(route('user.toggle-status', { id: u.id }))
    if (res.data.success) {
      u.active = res.data.is_active
      showFlash(res.data.message, true)
    } else {
      showFlash(res.data.message, false)
    }
  } catch (e) {
    showFlash(e.response?.data?.message || e.message, false)
  }
}

// ── Delete User ───────────────────────────────────────────────────────────────
async function deleteUser(u) {
  if (!confirm(`Permanently delete ${u.name} and all their data?`)) return
  try {
    const res = await window.axios.delete(route('user.destroy', { id: u.id }))
    if (res.data.success) {
      users.value = users.value.filter(x => x.id !== u.id)
      showFlash(res.data.message, true)
    } else {
      showFlash(res.data.message, false)
    }
  } catch (e) {
    showFlash(e.response?.data?.message || e.message, false)
  }
}

// ── Quick Test User ───────────────────────────────────────────────────────────
const testForm    = ref({ region_id: '', tariff_id: '', seed_months: 6 })
const testTariffs = ref([])
const creatingTest = ref(false)

async function loadTestTariffs() {
  if (!testForm.value.region_id) { testTariffs.value = []; return }
  try {
    const res = await window.axios.get(`/admin/user-accounts/setup/tariffs/${testForm.value.region_id}`)
    testTariffs.value = res.data?.data || []
  } catch (e) { console.error(e) }
}

async function createTestUser() {
  creatingTest.value = true
  try {
    const res = await window.axios.post('/admin/user-accounts/setup/create-test-user', {
      seed_months:    testForm.value.seed_months,
      use_form_data:  '0',
      form_region_id: testForm.value.region_id || undefined,
      form_tariff_id: testForm.value.tariff_id || undefined,
    })
    showFlash(res.data?.message || (res.data?.success ? 'Test user created.' : 'Failed.'), !!res.data?.success)
    if (res.data?.success) window.location.reload()
  } catch (e) {
    showFlash(e.response?.data?.message || e.message, false)
  } finally {
    creatingTest.value = false
  }
}

// ── Populate Existing User ────────────────────────────────────────────────────
const populateForm = ref({ user_id: '', seed_months: 6 })
const populating   = ref(false)

async function populateUser() {
  if (!populateForm.value.user_id) return
  populating.value = true
  try {
    const res = await window.axios.post('/admin/user-accounts/setup/populate-existing-user', populateForm.value)
    showFlash(res.data?.message || (res.data?.success ? 'Done.' : 'Failed.'), !!res.data?.success)
  } catch (e) {
    showFlash(e.response?.data?.message || e.message, false)
  } finally {
    populating.value = false
  }
}
</script>

<style scoped>
.form-group label { font-weight: 600; font-size: 0.85rem; color: #5a6070; margin-bottom: 0.3rem; }
.form-control { font-size: 0.9rem; }
.btn-xs { padding: 0.2rem 0.5rem; font-size: 0.75rem; }
.badge-success  { background: #1cc88a; color: #fff; }
.badge-secondary { background: #858796; color: #fff; }
.alert { padding: 0.75rem 1.25rem 0.75rem 1rem; border-radius: 0.35rem; position: relative; }
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-danger  { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.close { position: absolute; top: 0.5rem; right: 0.75rem; background: none; border: none; font-size: 1.2rem; cursor: pointer; }
.card { border: none; border-radius: 0.35rem; }
.card-header { background: #f8f9fc; border-bottom: 1px solid #e3e6f0; }
.table th, .table td { vertical-align: middle; font-size: 0.85rem; }
</style>
