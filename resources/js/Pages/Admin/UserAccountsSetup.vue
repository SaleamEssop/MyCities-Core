<template>
  <AdminLayout>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
      <h1 class="h3 mb-0 text-gray-800">User Accounts — Setup</h1>
    </div>

    <!-- ══════════ FLASH MESSAGES ══════════ -->
    <div v-if="flashMsg" class="alert" :class="flashSuccess ? 'alert-success' : 'alert-danger'" role="alert">
      <pre class="mb-0" style="white-space:pre-wrap;font-family:inherit;font-size:0.88rem;">{{ flashMsg }}</pre>
      <button type="button" class="close" @click="flashMsg = ''">&times;</button>
    </div>

    <!-- ══════════ 1. CREATE FULL ACCOUNT WIZARD ══════════ -->
    <div class="card shadow mb-4">
      <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">1️⃣ Account Setup Wizard</h6>
      </div>
      <div class="card-body">
        <form @submit.prevent="submitForm">
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>User Name *</label>
              <input type="text" v-model="form.name" class="form-control" required />
            </div>
            <div class="form-group col-md-6">
              <label>Email *</label>
              <input type="email" v-model="form.email" class="form-control" required />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Phone</label>
              <input type="text" v-model="form.phone" class="form-control" />
            </div>
            <div class="form-group col-md-6">
              <label>Password</label>
              <input type="text" v-model="form.password" class="form-control" placeholder="demo123" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-4">
              <label>Select Region</label>
              <select v-model="form.region_id" class="form-control" @change="loadTariffs">
                <option value="">Select Region</option>
                <option v-for="r in regions" :key="r.id" :value="r.id">{{ r.name }}</option>
              </select>
            </div>
            <div class="form-group col-md-4">
              <label>Select Tariff</label>
              <select v-model="form.tariff_id" class="form-control" :disabled="!form.region_id">
                <option value="">Select Tariff</option>
                <option v-for="t in tariffs" :key="t.id" :value="t.id">{{ t.template_name }}</option>
              </select>
            </div>
            <div class="form-group col-md-4">
              <label>Bill Day</label>
              <input type="number" v-model.number="form.bill_day" class="form-control" min="1" max="31" placeholder="20" />
            </div>
          </div>
          <button type="submit" class="btn btn-primary" :disabled="submitting">
            {{ submitting ? 'Creating…' : 'Create Account' }}
          </button>
        </form>
      </div>
    </div>

    <!-- ══════════ 2. QUICK TEST USER ══════════ -->
    <div class="card shadow mb-4">
      <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-success">2️⃣ Quick Test User (with Seeded Readings)</h6>
        <span class="badge badge-success">Demo</span>
      </div>
      <div class="card-body">
        <p class="text-muted small mb-3">
          Creates a demo user with water &amp; electricity meters and
          <strong>N months of sample readings</strong> — ideal for testing billing calculations.
        </p>

        <div class="form-row align-items-end">
          <div class="form-group col-md-3">
            <label>Region</label>
            <select v-model="testForm.region_id" class="form-control" @change="loadTestTariffs">
              <option value="">Auto (first region)</option>
              <option v-for="r in regions" :key="r.id" :value="r.id">{{ r.name }}</option>
            </select>
          </div>
          <div class="form-group col-md-3">
            <label>Tariff</label>
            <select v-model="testForm.tariff_id" class="form-control" :disabled="!testForm.region_id">
              <option value="">Auto (first tariff)</option>
              <option v-for="t in testTariffs" :key="t.id" :value="t.id">{{ t.template_name }}</option>
            </select>
          </div>
          <div class="form-group col-md-2">
            <label>Seed Months</label>
            <select v-model.number="testForm.seed_months" class="form-control">
              <option value="3">3 months</option>
              <option value="4">4 months</option>
              <option value="5">5 months</option>
              <option value="6">6 months</option>
            </select>
          </div>
          <div class="form-group col-md-2">
            <label>Auto-generate?</label>
            <select v-model="testForm.use_form_data" class="form-control">
              <option value="0">Yes — auto names</option>
              <option value="1">No — use fields below</option>
            </select>
          </div>
          <div class="form-group col-md-2">
            <button class="btn btn-success btn-block" @click="createTestUser" :disabled="creatingTest">
              {{ creatingTest ? 'Creating…' : '🚀 Create Test User' }}
            </button>
          </div>
        </div>

        <!-- Optional manual fields (shown when use_form_data === '1') -->
        <div v-if="testForm.use_form_data === '1'" class="border rounded p-3 bg-light mt-2">
          <div class="form-row">
            <div class="form-group col-md-4">
              <label>Name</label>
              <input type="text" v-model="testForm.form_name" class="form-control" placeholder="Demo User" />
            </div>
            <div class="form-group col-md-4">
              <label>Email</label>
              <input type="email" v-model="testForm.form_email" class="form-control" placeholder="demo@example.com" />
            </div>
            <div class="form-group col-md-4">
              <label>Phone</label>
              <input type="text" v-model="testForm.form_phone" class="form-control" placeholder="0841234567" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════ 3. POPULATE EXISTING USER ══════════ -->
    <div class="card shadow mb-4">
      <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-info">3️⃣ Populate Existing User with Demo Data</h6>
        <span class="badge badge-info">Reseed</span>
      </div>
      <div class="card-body">
        <p class="text-muted small mb-3">
          Deletes existing readings &amp; bills for the selected user and replaces them with fresh demo data.
        </p>

        <div class="form-row align-items-end">
          <div class="form-group col-md-5">
            <label>Select User</label>
            <select v-model="populateForm.user_id" class="form-control">
              <option value="">— Select User —</option>
              <option v-for="u in users" :key="u.id" :value="u.id">
                {{ u.name }} ({{ u.email }})
              </option>
            </select>
          </div>
          <div class="form-group col-md-3">
            <label>Seed Months</label>
            <select v-model.number="populateForm.seed_months" class="form-control">
              <option value="3">3 months</option>
              <option value="4">4 months</option>
              <option value="5">5 months</option>
              <option value="6">6 months</option>
            </select>
          </div>
          <div class="form-group col-md-4">
            <button
              class="btn btn-info btn-block"
              @click="populateUser"
              :disabled="!populateForm.user_id || populating"
            >
              {{ populating ? 'Populating…' : '🔄 Populate with Demo Data' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════ EXISTING USERS TABLE ══════════ -->
    <div class="card shadow mb-4">
      <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Existing Users</h6>
      </div>
      <div class="card-body">
        <div v-if="users.length === 0" class="text-muted">No users found.</div>
        <div v-else class="table-responsive">
          <table class="table table-bordered table-hover table-sm">
            <thead class="thead-light">
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Accounts</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="u in users" :key="u.id">
                <td>{{ u.name }}</td>
                <td>{{ u.email }}</td>
                <td>
                  <span v-if="u.accounts && u.accounts.length > 0">
                    <span v-for="a in u.accounts" :key="a.id" class="badge badge-secondary mr-1">
                      {{ a.account_name }}
                    </span>
                  </span>
                  <span v-else class="text-muted">—</span>
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
import { ref, onMounted } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  regions:    { type: Array, default: () => [] },
  meterTypes: { type: Array, default: () => [] },
  users:      { type: Array, default: () => [] },
})

// ── Flash ──────────────────────────────────────────────────────────────────────
const flashMsg     = ref('')
const flashSuccess = ref(false)

function flash(msg, success = true) {
  flashMsg.value     = msg
  flashSuccess.value = success
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

// ── Shared data ────────────────────────────────────────────────────────────────
const regions = ref(props.regions || [])
const users   = ref(props.users || [])

// ── Section 1: Create Account Wizard ──────────────────────────────────────────
const form = ref({
  name: '', email: '', phone: '', password: '',
  region_id: '', tariff_id: '', bill_day: 20,
})
const tariffs    = ref([])
const submitting = ref(false)

async function loadTariffs() {
  if (!form.value.region_id) { tariffs.value = []; return }
  try {
    const res = await window.axios.get(`/admin/user-accounts/setup/tariffs/${form.value.region_id}`)
    tariffs.value = res.data?.data || []
  } catch (e) { console.error(e) }
}

async function submitForm() {
  submitting.value = true
  try {
    const data = await apiPost('/admin/user-accounts/setup', form.value)
    if (data.status === 200 || data.success) {
      flash('Account created successfully!', true)
      form.value = { name: '', email: '', phone: '', password: '', region_id: '', tariff_id: '', bill_day: 20 }
      tariffs.value = []
      await refreshUsers()
    } else {
      flash(data.message || 'Error creating account.', false)
    }
  } catch (e) {
    flash('Unexpected error: ' + e.message, false)
  } finally {
    submitting.value = false
  }
}

// ── Section 2: Quick Test User ─────────────────────────────────────────────────
const testForm = ref({
  seed_months:    6,
  region_id:      '',
  tariff_id:      '',
  use_form_data:  '0',
  form_name:      '',
  form_email:     '',
  form_phone:     '',
})
const testTariffs   = ref([])
const creatingTest  = ref(false)

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
    const payload = {
      seed_months:   testForm.value.seed_months,
      use_form_data: testForm.value.use_form_data,
    }
    if (testForm.value.region_id)  payload.form_region_id = testForm.value.region_id
    if (testForm.value.tariff_id)  payload.form_tariff_id = testForm.value.tariff_id
    if (testForm.value.use_form_data === '1') {
      if (testForm.value.form_name)  payload.form_name  = testForm.value.form_name
      if (testForm.value.form_email) payload.form_email = testForm.value.form_email
      if (testForm.value.form_phone) payload.form_phone = testForm.value.form_phone
    }

    const data = await apiPost('/admin/user-accounts/setup/create-test-user', payload)
    flash(data.message || (data.success ? 'Test user created.' : 'Failed to create test user.'), !!data.success)
    if (data.success) await refreshUsers()
  } catch (e) {
    flash('Unexpected error: ' + e.message, false)
  } finally {
    creatingTest.value = false
  }
}

// ── Section 3: Populate Existing User ─────────────────────────────────────────
const populateForm = ref({ user_id: '', seed_months: 6 })
const populating   = ref(false)

async function populateUser() {
  if (!populateForm.value.user_id) return
  populating.value = true
  try {
    const data = await apiPost('/admin/user-accounts/setup/populate-existing-user', populateForm.value)
    flash(data.message || (data.success ? 'User populated.' : 'Failed to populate user.'), !!data.success)
    if (data.success) await refreshUsers()
  } catch (e) {
    flash('Unexpected error: ' + e.message, false)
  } finally {
    populating.value = false
  }
}

// ── Refresh users list ─────────────────────────────────────────────────────────
async function refreshUsers() {
  try {
    const res  = await fetch('/admin/user-accounts/setup', {
      headers: { Accept: 'application/json', 'X-Inertia': 'true', 'X-Inertia-Version': '' },
    })
    // Silently fail; user can refresh page
  } catch (_) {}
}

// ── Helpers ────────────────────────────────────────────────────────────────────
// Use window.axios — automatically sends XSRF-TOKEN cookie (no manual CSRF needed)
async function apiPost(url, data) {
  try {
    const res = await window.axios.post(url, data)
    return res.data
  } catch (err) {
    return err.response?.data ?? { success: false, message: err.message }
  }
}
</script>

<style scoped>
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; margin-bottom: 0.35rem; font-weight: 600; font-size: 0.85rem; color: #5a6070; }
.form-control {
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: 1px solid #d1d3e2;
  border-radius: 0.35rem;
  font-size: 0.9rem;
  box-sizing: border-box;
}
.form-control:focus { border-color: #4e73df; outline: none; box-shadow: 0 0 0 2px rgba(78,115,223,0.2); }
.form-control:disabled { background: #f8f9fc; cursor: not-allowed; }
.btn { padding: 0.5rem 1.25rem; border-radius: 0.35rem; border: none; cursor: pointer; font-weight: 600; }
.btn-primary { background: #4e73df; color: #fff; }
.btn-success { background: #1cc88a; color: #fff; }
.btn-info    { background: #36b9cc; color: #fff; }
.btn-block   { display: block; width: 100%; }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
.card { border-radius: 0.35rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58,59,69,0.15); border: none; margin-bottom: 1.5rem; }
.card-header { background: #f8f9fc; border-bottom: 1px solid #e3e6f0; padding: 0.75rem 1.25rem; }
.alert { position: relative; padding: 0.75rem 2.5rem 0.75rem 1rem; margin-bottom: 1rem; border-radius: 0.35rem; }
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-danger  { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.close { position: absolute; top: 0.5rem; right: 0.75rem; background: none; border: none; font-size: 1.2rem; cursor: pointer; opacity: 0.7; }
.badge { padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 700; }
.badge-secondary { background: #858796; color: #fff; }
.badge-success   { background: #1cc88a; color: #fff; }
.badge-info      { background: #36b9cc; color: #fff; }
.form-row { display: flex; flex-wrap: wrap; margin: 0 -0.5rem; }
.form-row .form-group { padding: 0 0.5rem; }
.col-md-2 { flex: 0 0 16.666%; max-width: 16.666%; }
.col-md-3 { flex: 0 0 25%; max-width: 25%; }
.col-md-4 { flex: 0 0 33.333%; max-width: 33.333%; }
.col-md-5 { flex: 0 0 41.666%; max-width: 41.666%; }
.col-md-6 { flex: 0 0 50%; max-width: 50%; }
.table-responsive { overflow-x: auto; }
.table { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
.table th, .table td { padding: 0.5rem 0.75rem; border: 1px solid #e3e6f0; }
.table thead.thead-light th { background: #f8f9fc; font-weight: 700; color: #5a6070; }
.table-hover tbody tr:hover td { background: #f0f4ff; }
@media (max-width: 768px) {
  .col-md-2, .col-md-3, .col-md-4, .col-md-5, .col-md-6 { flex: 0 0 100%; max-width: 100%; }
}
</style>
