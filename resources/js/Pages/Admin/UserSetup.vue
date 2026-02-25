<template>
  <AdminLayout>
    <!-- Page header -->
    <div class="us-page-header">
      <div>
        <h1 class="us-page-title">User Management</h1>
        <p class="us-page-sub">Create and manage system users</p>
      </div>
      <a :href="route('account-manager')" class="us-btn us-btn-primary">
        <i class="fas fa-users-cog"></i> Account Manager
      </a>
    </div>

    <!-- Flash -->
    <transition name="fade">
      <div v-if="flash.msg" class="us-flash" :class="flash.success ? 'us-flash--ok' : 'us-flash--err'">
        <i :class="flash.success ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'"></i>
        <span v-html="flash.msg"></span>
        <button class="us-flash-close" @click="flash.msg = ''">&times;</button>
      </div>
    </transition>

    <!-- Tabs -->
    <div class="us-tabs">
      <button class="us-tab" :class="{ active: tab === 'users' }" @click="tab = 'users'">
        <i class="fas fa-users"></i> Users
        <span class="us-tab-badge">{{ users.length }}</span>
      </button>
      <button class="us-tab" :class="{ active: tab === 'test' }" @click="tab = 'test'">
        <i class="fas fa-flask"></i> Create Test User
      </button>
    </div>

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- TAB: USERS                                                     -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <div v-if="tab === 'users'">

      <!-- Create new user card -->
      <div class="us-card mb-4">
        <div class="us-card-header">
          <span><i class="fas fa-user-plus"></i> Create New User</span>
        </div>
        <div class="us-card-body">
          <form @submit.prevent="createUser" class="us-form-row">
            <div class="us-field">
              <label>Full Name <span class="req">*</span></label>
              <input v-model="newUser.name" type="text" class="us-input" placeholder="John Smith" required />
            </div>
            <div class="us-field">
              <label>Email <span class="req">*</span></label>
              <input v-model="newUser.email" type="email" class="us-input" placeholder="john@example.com" required />
            </div>
            <div class="us-field">
              <label>Phone <span class="req">*</span></label>
              <input v-model="newUser.phone" type="text" class="us-input" placeholder="0821234567" required />
            </div>
            <div class="us-field">
              <label>Password <span class="req">*</span></label>
              <input v-model="newUser.password" type="text" class="us-input" placeholder="min 6 chars" required />
            </div>
            <div class="us-field us-field--action">
              <button type="submit" class="us-btn us-btn-primary us-btn-full" :disabled="creating">
                <i class="fas fa-plus"></i> {{ creating ? 'Creating…' : 'Create User' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Existing users table -->
      <div class="us-card">
        <div class="us-card-header">
          <span><i class="fas fa-users"></i> Existing Users ({{ filteredUsers.length }})</span>
          <input v-model="tableSearch" type="text" class="us-search" placeholder="Search name, email or phone…" />
        </div>
        <div class="us-card-body p-0">
          <div class="us-table-wrap">
            <table class="us-table">
              <thead>
                <tr>
                  <th style="width:70px;">#ID</th>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Phone</th>
                  <th>Joined</th>
                  <th style="width:60px;">Accts</th>
                  <th style="width:70px;">Status</th>
                  <th style="width:190px;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="filteredUsers.length === 0">
                  <td colspan="8" class="us-table-empty">No users found.</td>
                </tr>

                <template v-for="u in filteredUsers" :key="u.id">
                  <!-- Normal row -->
                  <tr v-if="editingId !== u.id">
                    <td class="us-id">#{{ u.id }}</td>
                    <td class="us-name">{{ u.name }}</td>
                    <td>{{ u.email }}</td>
                    <td>{{ u.phone || '—' }}</td>
                    <td class="us-date">{{ u.joined }}</td>
                    <td class="us-center">
                      <span class="us-acct-badge">{{ u.account_count }}</span>
                    </td>
                    <td>
                      <span class="us-status" :class="u.active ? 'us-status--ok' : 'us-status--off'">
                        {{ u.active ? 'Active' : 'Suspended' }}
                      </span>
                    </td>
                    <td class="us-actions">
                      <button @click="startEdit(u)" class="us-icon-btn us-icon-btn--blue" title="Edit">
                        <i class="fas fa-pencil-alt"></i>
                      </button>
                      <button @click="startReset(u)" class="us-icon-btn us-icon-btn--amber" title="Reset Password">
                        <i class="fas fa-key"></i>
                      </button>
                      <button @click="toggleStatus(u)" class="us-icon-btn"
                              :class="u.active ? 'us-icon-btn--grey' : 'us-icon-btn--green'"
                              :title="u.active ? 'Suspend' : 'Activate'">
                        <i :class="u.active ? 'fas fa-ban' : 'fas fa-check'"></i>
                      </button>
                      <a :href="route('account-manager') + '?user_id=' + u.id"
                         class="us-icon-btn us-icon-btn--teal" title="View Accounts">
                        <i class="fas fa-folder-open"></i>
                      </a>
                      <button @click="deleteUser(u)" class="us-icon-btn us-icon-btn--red" title="Delete">
                        <i class="fas fa-trash"></i>
                      </button>
                    </td>
                  </tr>

                  <!-- Inline edit row -->
                  <tr v-else class="us-edit-row">
                    <td class="us-id">#{{ u.id }}</td>
                    <td><input v-model="editForm.name" class="us-input us-input--sm" placeholder="Full name" /></td>
                    <td><input v-model="editForm.email" type="email" class="us-input us-input--sm" placeholder="Email" /></td>
                    <td><input v-model="editForm.phone" class="us-input us-input--sm" placeholder="Phone" /></td>
                    <td class="us-date">{{ u.joined }}</td>
                    <td class="us-center"><span class="us-acct-badge">{{ u.account_count }}</span></td>
                    <td></td>
                    <td class="us-actions">
                      <button @click="saveEdit(u)" class="us-btn us-btn-sm us-btn-primary" :disabled="saving">
                        {{ saving ? '…' : 'Save' }}
                      </button>
                      <button @click="cancelEdit" class="us-btn us-btn-sm us-btn-ghost">Cancel</button>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
    <!-- /TAB USERS -->

    <!-- ══════════════════════════════════════════════════════════════ -->
    <!-- TAB: CREATE TEST USER                                          -->
    <!-- ══════════════════════════════════════════════════════════════ -->
    <div v-if="tab === 'test'">

      <div class="us-two-col">

        <!-- Left: form -->
        <div class="us-card">
          <div class="us-card-header">
            <span><i class="fas fa-flask"></i> Test User Setup</span>
            <span class="us-badge us-badge--green">Demo</span>
          </div>
          <div class="us-card-body">

            <!-- User selection -->
            <div class="us-section-label">User</div>
            <div class="us-field mb-3">
              <label>Select existing user <span class="us-hint">— or leave blank to auto-create</span></label>
              <div class="us-search-wrap">
                <input v-model="userSearch" type="text" class="us-input" placeholder="Search by name or email…"
                       @focus="userDropOpen = true" @blur="hideUserDrop" />
                <div v-if="userDropOpen && filteredUserSearch.length" class="us-dropdown">
                  <div class="us-dropdown-item" @mousedown.prevent="clearUserSelection">
                    <span class="us-hint">— Auto-create new test user —</span>
                  </div>
                  <div v-for="u in filteredUserSearch" :key="u.id"
                       class="us-dropdown-item" @mousedown.prevent="selectUser(u)">
                    <strong>#{{ u.id }}</strong> {{ u.name }}
                    <span class="us-hint">{{ u.email }}</span>
                  </div>
                </div>
              </div>
              <div v-if="testForm.selectedUser" class="us-selected-pill">
                <i class="fas fa-user"></i>
                #{{ testForm.selectedUser.id }} {{ testForm.selectedUser.name }} · {{ testForm.selectedUser.email }}
                <button @click="clearUserSelection" class="us-pill-close">&times;</button>
              </div>
            </div>

            <!-- Account selection (only when user selected) -->
            <div v-if="testForm.selectedUser" class="us-field mb-3">
              <label>Account <span class="us-hint">— or blank to auto-select/create</span></label>
              <select v-model="testForm.account_id" class="us-input">
                <option value="">— Auto —</option>
                <option v-for="a in userAccounts" :key="a.id" :value="a.id">
                  #{{ a.id }} {{ a.account_name }} ({{ a.account_number }})
                </option>
              </select>
            </div>

            <!-- Region + Tariff -->
            <div class="us-section-label">Billing</div>
            <div class="us-form-row mb-3">
              <div class="us-field">
                <label>Region</label>
                <select v-model="testForm.region_id" class="us-input" @change="onRegionChange">
                  <option value="">— Auto (first) —</option>
                  <option v-for="r in regions" :key="r.id" :value="r.id">{{ r.name }}</option>
                </select>
              </div>
              <div class="us-field">
                <label>Tariff Template</label>
                <select v-model="testForm.tariff_id" class="us-input" :disabled="!testForm.region_id && !regionTariffs.length">
                  <option value="">— Auto (first) —</option>
                  <option v-for="t in regionTariffs" :key="t.id" :value="t.id">{{ t.template_name }}</option>
                </select>
              </div>
            </div>

            <!-- Meters -->
            <div class="us-section-label">Meters</div>
            <div class="us-checkbox-row mb-3">
              <label class="us-checkbox">
                <input type="radio" v-model="testForm.meters" value="both" />
                <span>Water &amp; Electricity</span>
              </label>
              <label class="us-checkbox">
                <input type="radio" v-model="testForm.meters" value="water" />
                <span>Water only</span>
              </label>
              <label class="us-checkbox">
                <input type="radio" v-model="testForm.meters" value="electricity" />
                <span>Electricity only</span>
              </label>
            </div>

            <!-- Seed months -->
            <div class="us-section-label">Seed Data</div>
            <div class="us-segmented mb-4">
              <button v-for="m in [3, 6, 12]" :key="m"
                      class="us-seg-btn" :class="{ active: testForm.seed_months === m }"
                      type="button" @click="testForm.seed_months = m">
                {{ m }} months
              </button>
            </div>

            <button class="us-btn us-btn-primary us-btn-full" @click="runCreateTestUser" :disabled="creatingTest">
              <i class="fas fa-bolt"></i>
              {{ creatingTest ? 'Processing…' : (testForm.selectedUser ? 'Seed This User' : 'Create Test User') }}
            </button>

          </div>
        </div>

        <!-- Right: result card -->
        <div class="us-card">
          <div class="us-card-header">
            <span><i class="fas fa-check-circle"></i> Result</span>
          </div>
          <div class="us-card-body">
            <div v-if="!testResult" class="us-empty-result">
              <i class="fas fa-user-circle us-empty-icon"></i>
              <p>Created user details will appear here.</p>
            </div>
            <div v-else class="us-result">
              <div class="us-result-banner" :class="testResult.success ? 'us-result-banner--ok' : 'us-result-banner--err'">
                <i :class="testResult.success ? 'fas fa-check-circle' : 'fas fa-times-circle'"></i>
                {{ testResult.success ? 'Success' : 'Failed' }}
              </div>

              <template v-if="testResult.success && testResult.created_user">
                <div class="us-result-row">
                  <span class="us-result-label">User ID</span>
                  <span class="us-result-val us-id">#{{ testResult.created_user.id }}</span>
                </div>
                <div class="us-result-row">
                  <span class="us-result-label">Name</span>
                  <span class="us-result-val">{{ testResult.created_user.name }}</span>
                </div>
                <div class="us-result-row">
                  <span class="us-result-label">Email</span>
                  <span class="us-result-val">{{ testResult.created_user.email }}</span>
                </div>
                <div v-if="testResult.created_user.password" class="us-result-row">
                  <span class="us-result-label">Password</span>
                  <span class="us-result-val us-password">{{ testResult.created_user.password }}</span>
                </div>
                <div class="us-result-row">
                  <span class="us-result-label">Phone</span>
                  <span class="us-result-val">{{ testResult.created_user.phone }}</span>
                </div>
                <div class="us-result-row">
                  <span class="us-result-label">Region</span>
                  <span class="us-result-val">{{ testResult.created_user.region }}</span>
                </div>
                <div class="us-result-row">
                  <span class="us-result-label">Tariff</span>
                  <span class="us-result-val">{{ testResult.created_user.tariff }}</span>
                </div>
                <div class="us-result-row">
                  <span class="us-result-label">Account #</span>
                  <span class="us-result-val">{{ testResult.created_user.account_number }}</span>
                </div>
                <div class="us-result-row">
                  <span class="us-result-label">Bill Day</span>
                  <span class="us-result-val">{{ testResult.created_user.bill_day }}</span>
                </div>
                <div class="us-result-row">
                  <span class="us-result-label">Seed Months</span>
                  <span class="us-result-val">{{ testResult.created_user.seed_months }}</span>
                </div>
                <div class="us-result-row">
                  <span class="us-result-label">Readings Added</span>
                  <span class="us-result-val">{{ testResult.created_user.readings_added }}</span>
                </div>
                <div class="us-result-actions">
                  <a :href="route('account-manager') + '?user_id=' + testResult.created_user.id"
                     class="us-btn us-btn-primary">
                    <i class="fas fa-folder-open"></i> View Accounts
                  </a>
                </div>
              </template>

              <div v-if="testResult.errors && testResult.errors.length" class="us-result-errors">
                <p v-for="e in testResult.errors" :key="e" class="us-err-line">
                  <i class="fas fa-exclamation-triangle"></i> {{ e }}
                </p>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
    <!-- /TAB TEST -->

    <!-- ── Password Reset Modal ──────────────────────────────────────── -->
    <teleport to="body">
      <div v-if="pwdReset.show" class="us-modal-backdrop" @click.self="cancelReset">
        <div class="us-modal">
          <div class="us-modal-header">
            <i class="fas fa-key mr-2"></i>Reset Password — {{ pwdReset.userName }}
            <button class="us-modal-close" @click="cancelReset">&times;</button>
          </div>
          <div class="us-modal-body">
            <div class="us-radio-group">
              <label class="us-radio-label">
                <input type="radio" v-model="pwdReset.mode" value="auto">
                <span>Auto-generate a random password</span>
              </label>
              <label class="us-radio-label mt-2">
                <input type="radio" v-model="pwdReset.mode" value="manual">
                <span>Set my own password</span>
              </label>
            </div>
            <div v-if="pwdReset.mode === 'manual'" class="mt-3">
              <label class="us-label">New password <span class="req">*</span></label>
              <input
                v-model="pwdReset.newPwd"
                type="text"
                class="us-input"
                placeholder="Min 6 characters"
                autocomplete="new-password"
              >
            </div>
          </div>
          <div class="us-modal-footer">
            <button class="us-btn us-btn-secondary" @click="cancelReset">Cancel</button>
            <button class="us-btn us-btn-primary" @click="confirmReset" :disabled="pwdReset.busy">
              <i class="fas fa-check mr-1"></i>{{ pwdReset.busy ? 'Resetting…' : 'Reset Password' }}
            </button>
          </div>
        </div>
      </div>
    </teleport>

  </AdminLayout>
</template>

<script setup>
import { ref, computed, watch, reactive } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  users:           { type: Array,  default: () => [] },
  regions:         { type: Array,  default: () => [] },
  tariffsByRegion: { type: Object, default: () => ({}) },
})

const users   = ref([...props.users])
const regions = ref(props.regions || [])
const tab     = ref('users')

// ── Flash ─────────────────────────────────────────────────────────────────────
const flash = ref({ msg: '', success: true })
function showFlash(msg, success = true) {
  flash.value = { msg, success }
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

// ── Users table ───────────────────────────────────────────────────────────────
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
const newUser  = ref({ name: '', email: '', phone: '', password: '' })
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
      const now = new Date()
      const joined = now.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
      users.value.unshift({
        id:            res.data.user_id,
        name:          newUser.value.name,
        email:         newUser.value.email,
        phone:         newUser.value.phone,
        active:        true,
        joined,
        account_count: 0,
      })
      newUser.value = { name: '', email: '', phone: '', password: '' }
      showFlash(`User <strong>${newUser.value.name || res.data.user_id}</strong> created.`, true)
    } else {
      showFlash(res.data.message || 'Error creating user.', false)
    }
  } catch (e) {
    showFlash(e.response?.data?.message || e.message, false)
  } finally {
    creating.value = false
  }
}

// ── Edit User ─────────────────────────────────────────────────────────────────
const editingId = ref(null)
const editForm  = ref({ name: '', email: '', phone: '' })
const saving    = ref(false)

function startEdit(u) {
  editingId.value = u.id
  editForm.value  = { name: u.name, email: u.email, phone: u.phone || '' }
}
function cancelEdit() {
  editingId.value = null
}
async function saveEdit(u) {
  saving.value = true
  try {
    const res = await window.axios.patch(route('user.edit', { id: u.id }), {
      name:           editForm.value.name,
      email:          editForm.value.email,
      contact_number: editForm.value.phone,
    })
    if (res.data.success) {
      u.name  = editForm.value.name
      u.email = editForm.value.email
      u.phone = editForm.value.phone
      cancelEdit()
      showFlash('User updated.', true)
    } else {
      showFlash(res.data.message, false)
    }
  } catch (e) {
    showFlash(e.response?.data?.message || e.message, false)
  } finally {
    saving.value = false
  }
}

// ── Reset Password ────────────────────────────────────────────────────────────
const pwdReset = reactive({ show: false, userId: null, userName: '', mode: 'auto', newPwd: '', busy: false })

function startReset(u) {
  pwdReset.userId   = u.id
  pwdReset.userName = u.name
  pwdReset.mode     = 'auto'
  pwdReset.newPwd   = ''
  pwdReset.show     = true
}
function cancelReset() { pwdReset.show = false }

async function confirmReset() {
  if (pwdReset.mode === 'manual' && pwdReset.newPwd.length < 6) {
    showFlash('Password must be at least 6 characters.', false); return
  }
  pwdReset.busy = true
  try {
    const payload = { user_id: pwdReset.userId }
    if (pwdReset.mode === 'manual') payload.password = pwdReset.newPwd
    const res = await window.axios.post(route('user.reset-password'), payload)
    if (res.data.success) {
      showFlash(`Password reset for <strong>${pwdReset.userName}</strong>. New password: <strong>${res.data.new_password}</strong>`, true)
      pwdReset.show = false
    } else {
      showFlash(res.data.message, false)
    }
  } catch (e) {
    showFlash(e.response?.data?.message || e.message, false)
  } finally {
    pwdReset.busy = false
  }
}

// ── Toggle Status ─────────────────────────────────────────────────────────────
async function toggleStatus(u) {
  const action = u.active ? 'Suspend' : 'Activate'
  if (!confirm(`${action} ${u.name}?`)) return
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

// ── Create Test User ──────────────────────────────────────────────────────────
const testForm = ref({
  selectedUser: null,
  account_id:   '',
  region_id:    '',
  tariff_id:    '',
  meters:       'both',
  seed_months:  6,
})

const userSearch     = ref('')
const userDropOpen   = ref(false)
const userAccounts   = ref([])
const regionTariffs  = ref([])
const creatingTest   = ref(false)
const testResult     = ref(null)

const filteredUserSearch = computed(() => {
  const q = userSearch.value.toLowerCase()
  if (!q) return users.value.slice(0, 12)
  return users.value.filter(u =>
    (u.name  || '').toLowerCase().includes(q) ||
    (u.email || '').toLowerCase().includes(q)
  ).slice(0, 12)
})

function hideUserDrop() {
  setTimeout(() => { userDropOpen.value = false }, 150)
}

function selectUser(u) {
  testForm.value.selectedUser = u
  userSearch.value            = `${u.name} — ${u.email}`
  userDropOpen.value          = false
  testForm.value.account_id   = ''
  loadUserAccounts(u.id)
}

function clearUserSelection() {
  testForm.value.selectedUser = null
  userSearch.value            = ''
  userAccounts.value          = []
  testForm.value.account_id   = ''
}

async function loadUserAccounts(userId) {
  try {
    const res = await window.axios.get(`/admin/user/${userId}/accounts`)
    userAccounts.value = res.data?.accounts || []
  } catch { userAccounts.value = [] }
}

function onRegionChange() {
  testForm.value.tariff_id = ''
  const id = testForm.value.region_id
  if (id && props.tariffsByRegion[id]) {
    regionTariffs.value = props.tariffsByRegion[id]
  } else {
    regionTariffs.value = []
  }
}

// Populate regionTariffs when region is pre-set
watch(() => testForm.value.region_id, (id) => {
  regionTariffs.value = id && props.tariffsByRegion[id] ? props.tariffsByRegion[id] : []
})

async function runCreateTestUser() {
  creatingTest.value = true
  testResult.value   = null
  try {
    const payload = {
      seed_months: testForm.value.seed_months,
      meters:      testForm.value.meters,
    }
    if (testForm.value.selectedUser)  payload.user_id    = testForm.value.selectedUser.id
    if (testForm.value.account_id)    payload.account_id = testForm.value.account_id
    if (testForm.value.region_id)     payload.region_id  = testForm.value.region_id
    if (testForm.value.tariff_id)     payload.tariff_id  = testForm.value.tariff_id

    const res = await window.axios.post(
      route('user-accounts.setup.create-test-user'),
      payload
    )
    testResult.value = { success: !!res.data?.success, ...res.data }

    if (res.data?.success && res.data?.created_user?.is_new_user) {
      const cu = res.data.created_user
      const now = new Date()
      const joined = now.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
      users.value.unshift({
        id: cu.id, name: cu.name, email: cu.email,
        phone: cu.phone, active: true, joined, account_count: 1,
      })
    }
  } catch (e) {
    testResult.value = {
      success: false,
      errors: [e.response?.data?.message || e.message],
    }
  } finally {
    creatingTest.value = false
  }
}
</script>

<style scoped>
/* ── Base ────────────────────────────────────────────────────────────────────── */
.us-page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.5rem;
}
.us-page-title { font-size: 1.5rem; font-weight: 700; color: #2c3e50; margin: 0; }
.us-page-sub   { font-size: 0.82rem; color: #888; margin: 0; }

/* ── Buttons ─────────────────────────────────────────────────────────────────── */
.us-btn {
  display: inline-flex; align-items: center; gap: 0.35rem;
  padding: 0.45rem 1rem; border-radius: 6px; font-size: 0.85rem;
  font-weight: 600; border: none; cursor: pointer; text-decoration: none;
  transition: opacity .15s;
}
.us-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.us-btn-primary  { background: #3294B8; color: #fff; }
.us-btn-primary:hover { background: #2579a0; }
.us-btn-ghost    { background: #f0f0f0; color: #555; }
.us-btn-ghost:hover { background: #e0e0e0; }
.us-btn-full     { width: 100%; justify-content: center; padding: 0.6rem; font-size: 0.9rem; }
.us-btn-sm       { padding: 0.25rem 0.6rem; font-size: 0.78rem; }

/* ── Flash ───────────────────────────────────────────────────────────────────── */
.us-flash {
  display: flex; align-items: center; gap: 0.6rem;
  padding: 0.75rem 1rem; border-radius: 8px;
  margin-bottom: 1.25rem; font-size: 0.88rem; font-weight: 500;
  position: relative;
}
.us-flash--ok  { background: #d4f4e2; color: #1a7a4a; border: 1px solid #b7e5cc; }
.us-flash--err { background: #fde8e8; color: #b91c1c; border: 1px solid #f5b7b7; }
.us-flash-close {
  position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%);
  background: none; border: none; font-size: 1.1rem; cursor: pointer; opacity: .6;
}
.fade-enter-active, .fade-leave-active { transition: opacity .25s; }
.fade-enter-from, .fade-leave-to       { opacity: 0; }

/* ── Tabs ────────────────────────────────────────────────────────────────────── */
.us-tabs {
  display: flex; gap: 0; margin-bottom: 1.5rem;
  border-bottom: 2px solid #B0D3DF;
}
.us-tab {
  display: flex; align-items: center; gap: 0.4rem;
  padding: 0.55rem 1.2rem; background: none; border: none;
  font-size: 0.88rem; font-weight: 600; color: #7a8ea0; cursor: pointer;
  border-bottom: 3px solid transparent; margin-bottom: -2px;
  transition: color .15s, border-color .15s;
}
.us-tab.active { color: #3294B8; border-bottom-color: #3294B8; }
.us-tab:hover  { color: #3294B8; }
.us-tab-badge  {
  background: #B0D3DF; color: #2a6a8a; border-radius: 99px;
  font-size: 0.72rem; padding: 0.1rem 0.45rem; font-weight: 700;
}
.us-tab.active .us-tab-badge { background: #3294B8; color: #fff; }

/* ── Card ────────────────────────────────────────────────────────────────────── */
.us-card { background: #fff; border-radius: 10px; box-shadow: 0 2px 12px rgba(0,0,0,.07); overflow: hidden; }
.mb-4    { margin-bottom: 1.25rem; }
.mb-3    { margin-bottom: 0.85rem; }
.us-card-header {
  display: flex; align-items: center; justify-content: space-between;
  background: #f5fafc; border-bottom: 1px solid #dde9ef;
  padding: 0.7rem 1rem; font-weight: 700; font-size: 0.88rem; color: #3294B8;
}
.us-card-body { padding: 1rem; }
.us-card-body.p-0 { padding: 0; }

/* ── Form ────────────────────────────────────────────────────────────────────── */
.us-form-row { display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: flex-end; }
.us-field    { display: flex; flex-direction: column; gap: 0.3rem; flex: 1; min-width: 150px; }
.us-field--action { flex: 0 0 auto; min-width: 140px; }
.us-field label   { font-size: 0.78rem; font-weight: 600; color: #5a6a7a; }
.req  { color: #e03; }
.us-hint { font-weight: 400; color: #aaa; font-size: 0.75rem; }

.us-input {
  border: 1.5px solid #c5d8e4; border-radius: 6px;
  padding: 0.45rem 0.65rem; font-size: 0.85rem; color: #2c3e50;
  background: #fff; transition: border-color .15s;
  width: 100%; box-sizing: border-box;
}
.us-input:focus { outline: none; border-color: #3294B8; box-shadow: 0 0 0 3px rgba(50,148,184,.12); }
.us-input--sm   { padding: 0.3rem 0.5rem; font-size: 0.82rem; }
.us-input:disabled { background: #f5f7fa; color: #aaa; }

.us-search {
  border: 1.5px solid #c5d8e4; border-radius: 6px;
  padding: 0.35rem 0.65rem; font-size: 0.82rem; width: 240px;
}
.us-search:focus { outline: none; border-color: #3294B8; }

/* ── Table ───────────────────────────────────────────────────────────────────── */
.us-table-wrap { overflow-x: auto; }
.us-table { width: 100%; border-collapse: collapse; font-size: 0.83rem; }
.us-table thead th {
  background: #f5fafc; color: #4a6a7a; font-weight: 700;
  padding: 0.6rem 0.75rem; text-align: left;
  border-bottom: 2px solid #dde9ef; white-space: nowrap;
}
.us-table tbody tr { border-bottom: 1px solid #edf3f6; }
.us-table tbody tr:last-child { border-bottom: none; }
.us-table tbody tr:hover { background: #f9fcfe; }
.us-table tbody td { padding: 0.55rem 0.75rem; vertical-align: middle; }
.us-table-empty { text-align: center; color: #aaa; padding: 2rem !important; }

.us-id     { font-family: monospace; color: #3294B8; font-weight: 700; }
.us-name   { font-weight: 600; }
.us-date   { color: #7a8898; font-size: 0.78rem; white-space: nowrap; }
.us-center { text-align: center; }

.us-acct-badge {
  display: inline-block; background: #eaf4f9; color: #3294B8;
  border-radius: 99px; padding: 0.1rem 0.55rem; font-size: 0.78rem; font-weight: 700;
}
.us-status {
  display: inline-block; border-radius: 99px; padding: 0.15rem 0.6rem;
  font-size: 0.73rem; font-weight: 700;
}
.us-status--ok  { background: #d4f4e2; color: #1a7a4a; }
.us-status--off { background: #f3f3f3; color: #888; }

.us-actions { display: flex; gap: 0.3rem; align-items: center; flex-wrap: wrap; }
.us-icon-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 28px; height: 28px; border-radius: 6px; border: none; cursor: pointer;
  font-size: 0.75rem; transition: opacity .15s;
}
.us-icon-btn:hover { opacity: 0.75; }
.us-icon-btn--blue   { background: #dbeeff; color: #2060a0; }
.us-icon-btn--amber  { background: #fff3cd; color: #856404; }
.us-icon-btn--grey   { background: #e9ecef; color: #555; }
.us-icon-btn--green  { background: #d4edda; color: #155724; }
.us-icon-btn--teal   { background: #d1ecf1; color: #0c5460; }
.us-icon-btn--red    { background: #fde8e8; color: #b91c1c; }

.us-edit-row { background: #fffbf0 !important; }
.us-edit-row td { padding-top: 0.4rem !important; padding-bottom: 0.4rem !important; }

/* ── Two-column layout ───────────────────────────────────────────────────────── */
.us-two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
@media (max-width: 900px) { .us-two-col { grid-template-columns: 1fr; } }

/* ── Section labels ──────────────────────────────────────────────────────────── */
.us-section-label {
  font-size: 0.72rem; font-weight: 800; text-transform: uppercase;
  letter-spacing: .06em; color: #3294B8; margin-bottom: 0.4rem; margin-top: 0.2rem;
}

/* ── Dropdown ────────────────────────────────────────────────────────────────── */
.us-search-wrap { position: relative; }
.us-dropdown {
  position: absolute; top: calc(100% + 4px); left: 0; right: 0;
  background: #fff; border: 1.5px solid #c5d8e4; border-radius: 8px;
  box-shadow: 0 6px 20px rgba(0,0,0,.12); z-index: 200; max-height: 220px; overflow-y: auto;
}
.us-dropdown-item {
  padding: 0.55rem 0.75rem; cursor: pointer; font-size: 0.83rem;
  display: flex; gap: 0.5rem; align-items: baseline; flex-wrap: wrap;
  border-bottom: 1px solid #f0f0f0;
}
.us-dropdown-item:last-child { border-bottom: none; }
.us-dropdown-item:hover { background: #f0f8fc; }

.us-selected-pill {
  display: inline-flex; align-items: center; gap: 0.4rem;
  background: #eaf4f9; border: 1px solid #B0D3DF; border-radius: 99px;
  padding: 0.25rem 0.65rem; font-size: 0.8rem; color: #2a6a8a; margin-top: 0.35rem;
}
.us-pill-close {
  background: none; border: none; cursor: pointer; font-size: 1rem;
  color: #aaa; margin-left: 0.2rem; line-height: 1;
}
.us-pill-close:hover { color: #e03; }

/* ── Checkboxes / radio ──────────────────────────────────────────────────────── */
.us-checkbox-row { display: flex; gap: 1.25rem; flex-wrap: wrap; }
.us-checkbox { display: flex; align-items: center; gap: 0.4rem; cursor: pointer; font-size: 0.85rem; }
.us-checkbox input { accent-color: #3294B8; }

/* ── Segmented buttons ───────────────────────────────────────────────────────── */
.us-segmented { display: flex; gap: 0; background: #f0f4f7; border-radius: 8px; padding: 3px; width: fit-content; }
.us-seg-btn {
  padding: 0.35rem 1rem; border: none; background: none; border-radius: 6px;
  font-size: 0.83rem; font-weight: 600; color: #7a8ea0; cursor: pointer;
  transition: background .15s, color .15s;
}
.us-seg-btn.active { background: #3294B8; color: #fff; }
.us-seg-btn:hover:not(.active) { background: #dce9ef; }

/* ── Result panel ────────────────────────────────────────────────────────────── */
.us-empty-result {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  padding: 2.5rem 1rem; color: #c0cdd6;
}
.us-empty-icon { font-size: 3rem; margin-bottom: 0.75rem; }

.us-result { display: flex; flex-direction: column; gap: 0; }
.us-result-banner {
  display: flex; align-items: center; gap: 0.5rem;
  padding: 0.6rem 0.9rem; border-radius: 6px; font-weight: 700;
  font-size: 0.9rem; margin-bottom: 1rem;
}
.us-result-banner--ok  { background: #d4f4e2; color: #1a7a4a; }
.us-result-banner--err { background: #fde8e8; color: #b91c1c; }

.us-result-row {
  display: flex; justify-content: space-between; align-items: center;
  padding: 0.4rem 0; border-bottom: 1px solid #edf3f6;
}
.us-result-row:last-of-type { border-bottom: none; }
.us-result-label { font-size: 0.78rem; color: #7a8898; font-weight: 600; }
.us-result-val   { font-size: 0.85rem; color: #2c3e50; font-weight: 500; }
.us-password     { font-family: monospace; background: #f5f7fa; padding: 0.1rem 0.4rem; border-radius: 4px; }

.us-result-actions { margin-top: 1rem; }

.us-result-errors { margin-top: 0.75rem; }
.us-err-line { font-size: 0.8rem; color: #b91c1c; display: flex; gap: 0.4rem; align-items: flex-start; margin: 0.2rem 0; }

/* ── Badge ───────────────────────────────────────────────────────────────────── */
.us-badge { border-radius: 99px; padding: 0.1rem 0.55rem; font-size: 0.72rem; font-weight: 700; }
.us-badge--green { background: #d4f4e2; color: #1a7a4a; }

/* ── Password Reset Modal ──────────────────────────────────────────────────── */
.us-modal-backdrop {
  position: fixed; inset: 0; background: rgba(0,0,0,0.45);
  display: flex; align-items: center; justify-content: center; z-index: 9999;
}
.us-modal {
  background: #fff; border-radius: 8px; width: 100%; max-width: 440px;
  box-shadow: 0 8px 32px rgba(0,0,0,0.18); overflow: hidden;
}
.us-modal-header {
  background: #f8f9fc; padding: 14px 18px; font-weight: 700; font-size: 0.95rem;
  border-bottom: 1px solid #e3e6f0; display: flex; align-items: center;
}
.us-modal-close {
  margin-left: auto; background: none; border: none; font-size: 1.3rem;
  cursor: pointer; color: #888; line-height: 1;
}
.us-modal-body  { padding: 20px 18px; }
.us-modal-footer {
  padding: 12px 18px; border-top: 1px solid #e3e6f0;
  display: flex; justify-content: flex-end; gap: 8px;
}
.us-radio-group { display: flex; flex-direction: column; }
.us-radio-label { display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 0.9rem; }
.us-radio-label input[type="radio"] { accent-color: #009BA4; width: 16px; height: 16px; }
.us-btn-secondary { background: #fff; border: 1.5px solid #d1d3e2; color: #555; }
.us-btn-secondary:hover { background: #f8f9fc; }
.mt-2 { margin-top: 0.5rem; }
.mt-3 { margin-top: 1rem; }
.mr-1 { margin-right: 0.25rem; }
.mr-2 { margin-right: 0.5rem; }
</style>
