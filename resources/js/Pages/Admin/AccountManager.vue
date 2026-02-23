<template>
  <AdminLayout>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
      <h1 class="h3 mb-0 text-gray-800">Account Manager</h1>
      <a :href="route('user.setup')" class="btn btn-sm btn-secondary shadow-sm">
        <i class="fas fa-user-plus fa-sm mr-1"></i> User Setup
      </a>
    </div>

    <!-- Flash -->
    <div v-if="flash.msg" class="alert" :class="flash.success ? 'alert-success' : 'alert-danger'">
      {{ flash.msg }}
      <button class="close" @click="flash.msg = ''">&times;</button>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- SECTION 1 — Find User                                           -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <div class="card shadow mb-4">
      <div class="card-header py-3 d-flex align-items-center">
        <span class="section-badge">1</span>
        <h6 class="m-0 font-weight-bold text-primary">Find User</h6>
      </div>
      <div class="card-body">
        <div v-if="!selectedUser">
          <div class="input-group mb-2" style="max-width:480px;">
            <input v-model="userSearch" type="text" class="form-control"
                   placeholder="Search by name, email or phone…"
                   @input="searchUsers" />
            <div class="input-group-append">
              <span class="input-group-text"><i class="fas fa-search"></i></span>
            </div>
          </div>
          <div v-if="userResults.length > 0" class="list-group" style="max-width:480px;max-height:200px;overflow-y:auto;">
            <button v-for="u in userResults" :key="u.id" type="button"
                    class="list-group-item list-group-item-action"
                    @click="selectUser(u)">
              <strong>{{ u.name }}</strong>
              <span class="text-muted ml-2">{{ u.email }}</span>
              <span v-if="u.phone" class="text-muted ml-2">| {{ u.phone }}</span>
            </button>
          </div>
          <small class="text-muted">
            User not found?
            <a :href="route('user.setup')">Create a new user</a> first.
          </small>
        </div>
        <div v-else class="d-flex align-items-center">
          <div class="user-card mr-3">
            <i class="fas fa-user-circle fa-2x text-primary"></i>
          </div>
          <div class="flex-grow-1">
            <strong>{{ selectedUser.name }}</strong>
            <span class="text-muted ml-2">{{ selectedUser.email }}</span>
            <span v-if="selectedUser.phone" class="text-muted ml-2">| {{ selectedUser.phone }}</span>
          </div>
          <button class="btn btn-sm btn-outline-secondary" @click="clearUser">Change User</button>
        </div>
        <div v-if="loadingUser" class="mt-2 text-muted small">
          <i class="fas fa-spinner fa-spin mr-1"></i> Loading accounts…
        </div>
      </div>
    </div>

    <!-- Rest only visible once a user is selected -->
    <template v-if="selectedUser">

      <!-- ═══════════════════════════════════════════════════════════════ -->
      <!-- SECTION 2 — Account Selection                                   -->
      <!-- ═══════════════════════════════════════════════════════════════ -->
      <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center">
            <span class="section-badge">2</span>
            <h6 class="m-0 font-weight-bold text-primary">Account</h6>
          </div>
          <div class="d-flex align-items-center">
            <span class="text-muted small mr-3">{{ userAccounts.length }}/2 accounts</span>
            <button v-if="!showNewAccountForm && userAccounts.length < 2"
                    class="btn btn-success btn-sm"
                    @click="showNewAccountForm = true">
              <i class="fas fa-plus fa-sm mr-1"></i> Add New Account
            </button>
            <button v-else-if="showNewAccountForm" class="btn btn-secondary btn-sm"
                    @click="showNewAccountForm = false">
              Cancel
            </button>
          </div>
        </div>
        <div class="card-body">

          <!-- Limit notice -->
          <div v-if="userAccounts.length >= 2 && !showNewAccountForm"
               class="alert alert-info py-2 mb-3 small">
            <i class="fas fa-info-circle mr-1"></i>
            Maximum of 2 accounts per user. To add a new account, remove an existing one.
          </div>

          <!-- Existing accounts list -->
          <div v-if="userAccounts.length > 0 && !showNewAccountForm" class="mb-3">
            <div v-for="acc in userAccounts" :key="acc.id"
                 class="account-card"
                 :class="{ 'account-card--active': selectedAccount?.id === acc.id }"
                 @click="selectAccount(acc)">
              <div class="d-flex justify-content-between align-items-start flex-wrap">
                <!-- Left: identity -->
                <div class="mb-2 mb-md-0">
                  <div class="d-flex align-items-center flex-wrap">
                    <strong class="mr-2" style="font-size:1rem;">{{ acc.name_on_bill || acc.account_name }}</strong>
                    <span class="badge badge-dark mr-2">{{ acc.account_number }}</span>
                    <span v-if="acc.description" class="badge badge-light border text-muted">{{ acc.description }}</span>
                  </div>
                  <div v-if="acc.address" class="text-muted small mt-1">
                    <i class="fas fa-map-marker-alt mr-1 text-danger"></i>{{ acc.address }}
                  </div>
                  <div v-if="acc.tariff_name" class="text-muted small mt-1">
                    <i class="fas fa-file-invoice-dollar mr-1 text-success"></i>{{ acc.tariff_name }}
                  </div>
                </div>
                <!-- Right: billing info -->
                <div class="text-right">
                  <div class="d-flex flex-column align-items-end">
                    <div class="mb-1">
                      <span class="badge badge-primary mr-1">Bill Day: {{ acc.bill_day }}</span>
                      <span class="badge badge-secondary">Read Day: {{ acc.read_day }}</span>
                    </div>
                    <div class="small mt-1">
                      <span v-if="selectedAccount?.id === acc.id" class="text-success font-weight-bold">
                        <i class="fas fa-check-circle mr-1"></i>Selected — managing meters below
                      </span>
                      <span v-else class="text-muted">
                        <i class="fas fa-mouse-pointer mr-1"></i>Click to manage meters
                      </span>
                    </div>
                  </div>
                </div>
              </div>
              <!-- Meter pills -->
              <div v-if="acc.meters && acc.meters.length > 0" class="mt-2 pt-2 border-top">
                <span v-for="m in acc.meters" :key="m.id"
                      class="badge mr-1"
                      :style="meterTypeLabel(m.meter_type_id) === 'Water' ? 'background:#17a2b8;color:#fff' : 'background:#ffc107;color:#212529'">
                  <i :class="meterTypeLabel(m.meter_type_id) === 'Water' ? 'fas fa-tint' : 'fas fa-bolt'" class="mr-1"></i>
                  {{ meterTypeLabel(m.meter_type_id) }} · {{ m.meter_number }}
                </span>
              </div>
              <div v-else class="mt-2 pt-2 border-top small text-muted">
                <i class="fas fa-exclamation-circle mr-1"></i>No meters — click to add
              </div>
            </div>
          </div>
          <div v-if="userAccounts.length === 0 && !showNewAccountForm" class="text-center py-4 text-muted">
            <i class="fas fa-folder-open fa-2x mb-2 d-block"></i>
            No accounts yet. Click <strong>+ Add New Account</strong> to create the first one.
          </div>

          <!-- New Account Form (expands inline) -->
          <div v-if="showNewAccountForm" class="border rounded p-3 bg-light">
            <h6 class="font-weight-bold mb-3">New Account Details</h6>

            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Name on Account (as per municipal bill) <span class="text-danger">*</span></label>
                  <input v-model="newAccount.name_on_bill" type="text" class="form-control"
                         placeholder="As it appears on the bill" />
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-group">
                  <label>Account Number (as per municipal bill) <span class="text-danger">*</span></label>
                  <input v-model="newAccount.account_number" type="text" class="form-control"
                         placeholder="e.g. 12345678" />
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label>Description</label>
                  <input v-model="newAccount.optional_information" type="text" class="form-control"
                         placeholder="e.g. Main House, Granny Cottage" />
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>Bill Day <span class="text-danger">*</span></label>
                  <input v-model.number="newAccount.bill_day" type="number" min="1" max="31"
                         class="form-control" placeholder="1–31" />
                  <small class="text-muted">Day of month billing occurs</small>
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group">
                  <label>Read Day <small class="text-muted">(auto)</small></label>
                  <input :value="newAccountReadDay" type="text" class="form-control" readonly />
                  <small class="text-muted">Bill Day − 5</small>
                </div>
              </div>
            </div>

            <!-- Address / ArcGIS -->
            <div class="row">
              <div class="col-md-3">
                <div class="form-group">
                  <label>Street / Unit No.</label>
                  <input v-model="streetNo" type="text" class="form-control"
                         placeholder="e.g. 54"
                         :disabled="!!newAccount.address" />
                  <small class="text-muted">House or unit number</small>
                </div>
              </div>
              <div class="col-md-9">
                <div class="form-group">
                  <label>Property Address</label>
                  <div class="input-group">
                    <input v-model="addressSearch" type="text" class="form-control"
                           placeholder="Start typing street name…"
                           :disabled="!!newAccount.address"
                           @input="suggestAddress" />
                    <div class="input-group-append">
                      <span class="input-group-text">
                        <i v-if="addressSearching" class="fas fa-spinner fa-spin"></i>
                        <i v-else class="fas fa-map-marker-alt"></i>
                      </span>
                    </div>
                  </div>
                  <small class="text-muted">Powered by OpenStreetMap — billing zone detected automatically.</small>
                </div>
              </div>
            </div>

            <div v-if="addressSuggestions.length > 0" class="list-group mb-3" style="max-height:180px;overflow-y:auto;">
              <button v-for="(s, idx) in addressSuggestions" :key="idx" type="button"
                      class="list-group-item list-group-item-action py-2"
                      @click="selectAddress(s)">
                <i class="fas fa-map-marker-alt text-danger mr-2"></i>{{ s.text }}
              </button>
            </div>

            <div v-if="newAccount.address" class="alert alert-info py-2 mb-3">
              <i class="fas fa-check-circle mr-2"></i><strong>{{ newAccount.address }}</strong>
              <span v-if="newAccount.latitude" class="text-muted ml-2 small">
                ({{ (+newAccount.latitude).toFixed(5) }}, {{ (+newAccount.longitude).toFixed(5) }})
              </span>
              <button type="button" class="btn btn-sm btn-link p-0 ml-2" @click="clearAddress">
                <i class="fas fa-times text-muted"></i>
              </button>
            </div>

            <div v-if="zoneDetecting" class="text-muted small mb-2">
              <i class="fas fa-spinner fa-spin mr-1"></i> Detecting billing zone…
            </div>
            <div v-if="detectedZone" class="alert alert-success py-2 mb-3">
              <i class="fas fa-map mr-2"></i>
              <strong>Zone Detected:</strong> {{ detectedZone.name }}
              <span v-if="newAccount.electricity_email" class="ml-3 small">
                <i class="fas fa-bolt text-warning mr-1"></i>Electricity: {{ newAccount.electricity_email }}
              </span>
              <span class="ml-3 small text-muted">
                <i class="fas fa-tint text-primary mr-1"></i>Water: uses municipality default
              </span>
            </div>
            <div v-if="zoneNotFound && newAccount.address" class="alert alert-warning py-2 mb-3">
              <i class="fas fa-info-circle mr-2"></i>No ArcGIS zone found — emails will use municipality defaults.
            </div>

            <!-- Province / Municipality / Tariff -->
            <div class="row">
              <div class="col-md-4">
                <div class="form-group">
                  <label>Province</label>
                  <select v-model="selectedProvince" class="form-control" @change="onProvinceChange">
                    <option value="">— Select Province —</option>
                    <option v-for="p in provinces" :key="p" :value="p">{{ p }}</option>
                  </select>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label>Municipality</label>
                  <select v-model="newAccount.region_id" class="form-control"
                          :disabled="!selectedProvince" @change="onRegionChange">
                    <option value="">— Select Municipality —</option>
                    <option v-for="r in filteredRegions" :key="r.id" :value="r.id">
                      {{ r.municipality || r.name }}
                    </option>
                  </select>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group">
                  <label>Tariff Template</label>
                  <select v-model="newAccount.tariff_template_id" class="form-control"
                          :disabled="!newAccount.region_id || tariffLoading">
                    <option value="">— Select Tariff —</option>
                    <option v-for="t in tariffTemplates" :key="t.id" :value="t.id">{{ t.template_name }}</option>
                  </select>
                  <small v-if="tariffLoading" class="text-muted">Loading…</small>
                </div>
              </div>
            </div>

            <!-- Billing Emails -->
            <div v-if="newAccount.region_id || detectedZone" class="card bg-white border mb-3">
              <div class="card-body py-2">
                <h6 class="font-weight-bold small mb-2">Billing Emails</h6>
                <div class="row">
                  <div class="col-md-6">
                    <label class="small font-weight-bold">Water Email</label>
                    <input v-model="newAccount.water_email" type="email" class="form-control form-control-sm"
                           :readonly="!!detectedZone" placeholder="water@municipality.gov.za" />
                  </div>
                  <div class="col-md-6">
                    <label class="small font-weight-bold">Electricity Email</label>
                    <input v-model="newAccount.electricity_email" type="email" class="form-control form-control-sm"
                           :readonly="!!detectedZone" placeholder="electricity@municipality.gov.za" />
                  </div>
                </div>
              </div>
            </div>

            <button class="btn btn-primary" @click="saveAccount" :disabled="savingAccount">
              <i v-if="savingAccount" class="fas fa-spinner fa-spin mr-1"></i>
              {{ savingAccount ? 'Saving…' : 'Save Account' }}
            </button>
          </div>

        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════════════ -->
      <!-- SECTION 3 — Meters (only when an account is selected)           -->
      <!-- ═══════════════════════════════════════════════════════════════ -->
      <div v-if="selectedAccount" class="card shadow mb-4">
        <div class="card-header py-3 d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center">
            <span class="section-badge">3</span>
            <h6 class="m-0 font-weight-bold text-primary">
              Meters
              <span class="text-muted font-weight-normal ml-1">
                — {{ selectedAccount.name_on_bill || selectedAccount.account_name }}
                <span class="badge badge-dark ml-1">{{ selectedAccount.account_number }}</span>
              </span>
            </h6>
          </div>
          <span class="text-muted small">{{ selectedAccount.meters.length }}/2 meters</span>
        </div>
        <div class="card-body">

          <!-- Existing meters -->
          <div v-if="selectedAccount.meters.length > 0" class="mb-4">
            <div v-for="m in selectedAccount.meters" :key="m.id"
                 class="d-flex align-items-center justify-content-between p-3 mb-2 rounded border"
                 :style="meterTypeLabel(m.meter_type_id) === 'Water'
                   ? 'border-left: 4px solid #17a2b8 !important; background:#f0fbfc;'
                   : 'border-left: 4px solid #ffc107 !important; background:#fffdf0;'">
              <div class="d-flex align-items-center">
                <span class="fa-stack mr-3" style="font-size:0.8rem;">
                  <i class="fas fa-circle fa-stack-2x"
                     :style="meterTypeLabel(m.meter_type_id) === 'Water' ? 'color:#17a2b8' : 'color:#ffc107'"></i>
                  <i :class="meterTypeLabel(m.meter_type_id) === 'Water' ? 'fas fa-tint' : 'fas fa-bolt'"
                     class="fa-stack-1x fa-inverse"></i>
                </span>
                <div>
                  <div class="font-weight-bold">{{ meterTypeLabel(m.meter_type_id) }} Meter</div>
                  <div class="small text-muted">
                    <code>{{ m.meter_number }}</code>
                    <span v-if="m.meter_title" class="ml-2">· {{ m.meter_title }}</span>
                  </div>
                </div>
              </div>
              <button class="btn btn-sm btn-outline-danger" @click="removeMeter(m)" title="Remove meter">
                <i class="fas fa-trash-alt"></i>
              </button>
            </div>
          </div>
          <div v-else class="text-center py-3 text-muted mb-4">
            <i class="fas fa-tachometer-alt fa-2x mb-2 d-block text-light"></i>
            No meters added yet. Add up to 2 meters (water and/or electricity).
          </div>

          <!-- 2-meter limit notice -->
          <div v-if="selectedAccount.meters.length >= 2" class="alert alert-info py-2 small mb-3">
            <i class="fas fa-info-circle mr-1"></i>
            Maximum of 2 meters per account reached (1 water + 1 electricity).
          </div>

          <!-- Add meter row -->
          <div v-if="selectedAccount.meters.length < 2" class="border rounded p-3"
               style="background:#f8f9fc;">
            <h6 class="font-weight-bold mb-3 text-gray-700">
              <i class="fas fa-plus-circle text-success mr-1"></i> Add Meter
            </h6>
            <div class="row align-items-end">
              <div class="col-md-3">
                <div class="form-group mb-0">
                  <label>Type <span class="text-danger">*</span></label>
                  <select v-model="newMeter.meter_type_id" class="form-control">
                    <option value="">— Select —</option>
                    <option v-for="t in meterTypes" :key="t.id" :value="t.id">{{ t.title }}</option>
                  </select>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-group mb-0">
                  <label>Meter Number <span class="text-danger">*</span></label>
                  <input v-model="newMeter.meter_number" type="text" class="form-control"
                         placeholder="e.g. W998877" />
                </div>
              </div>
              <div class="col-md-3">
                <div class="form-group mb-0">
                  <label>Title <small class="text-muted">(optional)</small></label>
                  <input v-model="newMeter.meter_title" type="text" class="form-control"
                         placeholder="e.g. Main Water" />
                </div>
              </div>
              <div class="col-md-2">
                <button class="btn btn-success btn-block" @click="addMeter" :disabled="addingMeter">
                  <i v-if="addingMeter" class="fas fa-spinner fa-spin mr-1"></i>
                  <i v-else class="fas fa-plus mr-1"></i>
                  Add
                </button>
              </div>
            </div>
          </div>

        </div>
      </div>
      <!-- Prompt when no account selected yet but accounts exist -->
      <div v-else-if="userAccounts.length > 0 && !showNewAccountForm" class="card shadow mb-4">
        <div class="card-body text-center py-4 text-muted">
          <span class="section-badge d-inline-flex mb-2">3</span>
          <p class="mb-0"><i class="fas fa-hand-pointer mr-1"></i> Click an account above to manage its meters.</p>
        </div>
      </div>

    </template><!-- /selectedUser -->

  </AdminLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

// ── Props ─────────────────────────────────────────────────────────────────────
const props = defineProps({
  regions:    { type: Array, default: () => [] },
  meterTypes: { type: Array, default: () => [] },
})

// ── All external calls proxied through Laravel to avoid CORS / User-Agent issues ──

// ── Flash ─────────────────────────────────────────────────────────────────────
const flash = ref({ msg: '', success: true })
function showFlash(msg, success = true) { flash.value = { msg, success }; window.scrollTo({ top: 0, behavior: 'smooth' }) }

// ── User search ───────────────────────────────────────────────────────────────
const userSearch    = ref('')
const userResults   = ref([])
const selectedUser  = ref(null)
const userAccounts  = ref([])
const loadingUser   = ref(false)
let   userTimer     = null

function searchUsers() {
  clearTimeout(userTimer)
  userResults.value = []
  if (userSearch.value.length < 2) return
  userTimer = setTimeout(async () => {
    try {
      const res = await window.axios.get(route('user-accounts.manager.search'), { params: { q: userSearch.value } })
      userResults.value = res.data?.data || res.data || []
    } catch { userResults.value = [] }
  }, 300)
}

async function selectUser(u) {
  selectedUser.value  = u
  userSearch.value    = u.name
  userResults.value   = []
  loadingUser.value   = true
  try {
    const res = await window.axios.get(route('account-manager.user', { id: u.id }))
    userAccounts.value = res.data?.accounts || []
  } catch (e) {
    showFlash('Failed to load accounts: ' + (e.response?.data?.message || e.message), false)
    userAccounts.value = []
  } finally {
    loadingUser.value = false
  }
}

function clearUser() {
  selectedUser.value    = null
  userSearch.value      = ''
  userResults.value     = []
  userAccounts.value    = []
  selectedAccount.value = null
  showNewAccountForm.value = false
  resetNewAccountForm()
}

// ── Account selection ─────────────────────────────────────────────────────────
const selectedAccount    = ref(null)
const showNewAccountForm = ref(false)

function selectAccount(acc) {
  selectedAccount.value    = acc
  showNewAccountForm.value = false
}

// ── New Account Form ──────────────────────────────────────────────────────────
const newAccount = reactive({
  name_on_bill:         '',
  account_number:       '',
  optional_information: '',
  bill_day:             20,
  address:              '',
  latitude:             null,
  longitude:            null,
  region_id:            '',
  zone_id:              null,
  tariff_template_id:   '',
  water_email:          '',
  electricity_email:    '',
})

function resetNewAccountForm() {
  Object.assign(newAccount, {
    name_on_bill: '', account_number: '', optional_information: '',
    bill_day: 20, address: '', latitude: null, longitude: null,
    region_id: '', zone_id: null, tariff_template_id: '', water_email: '', electricity_email: '',
  })
  addressSearch.value      = ''
  addressSuggestions.value = []
  detectedZone.value       = null
  zoneNotFound.value       = false
  selectedProvince.value   = ''
  tariffTemplates.value    = []
}

const newAccountReadDay = computed(() => {
  const b = parseInt(newAccount.bill_day)
  if (!b) return ''
  return b > 5 ? b - 5 : 30 + (b - 5)
})

const savingAccount = ref(false)

async function saveAccount() {
  if (!newAccount.name_on_bill || !newAccount.account_number || !newAccount.bill_day) {
    showFlash('Name on bill, account number and bill day are required.', false)
    return
  }
  savingAccount.value = true
  try {
    const res = await window.axios.post(route('account-manager.account.store'), {
      user_id: selectedUser.value.id,
      ...newAccount,
      region_id:          newAccount.region_id  || null,
      zone_id:            newAccount.zone_id    || null,
      tariff_template_id: newAccount.tariff_template_id || null,
    })
    if (res.data.success) {
      userAccounts.value.push(res.data.account)
      selectedAccount.value    = res.data.account
      showNewAccountForm.value = false
      resetNewAccountForm()
      showFlash('Account saved successfully.', true)
    } else {
      showFlash(res.data.message || 'Failed to save account.', false)
    }
  } catch (e) {
    const errs = e.response?.data?.errors
    showFlash(errs ? Object.values(errs).flat().join(' ') : (e.response?.data?.message || e.message), false)
  } finally {
    savingAccount.value = false
  }
}

// ── ArcGIS ────────────────────────────────────────────────────────────────────
const streetNo           = ref('')
const addressSearch      = ref('')
const addressSuggestions = ref([])
const addressSearching   = ref(false)
const zoneDetecting      = ref(false)
const detectedZone       = ref(null)
const zoneNotFound       = ref(false)
let   addrTimer          = null

function suggestAddress() {
  clearTimeout(addrTimer)
  addressSuggestions.value = []
  if (addressSearch.value.length < 3) return
  addrTimer = setTimeout(async () => {
    addressSearching.value = true
    try {
      const res = await window.axios.get(route('address.suggest'), {
        params: { q: addressSearch.value },
      })
      addressSuggestions.value = res.data || []
    } catch { addressSuggestions.value = [] }
    finally { addressSearching.value = false }
  }, 350)
}

async function selectAddress(s) {
  addressSuggestions.value = []
  const fullAddress        = streetNo.value.trim()
    ? streetNo.value.trim() + ' ' + s.text
    : s.text
  addressSearch.value      = fullAddress
  newAccount.address       = fullAddress
  newAccount.latitude      = s.lat
  newAccount.longitude     = s.lon
  detectedZone.value       = null
  zoneNotFound.value       = false
  await lookupZone(s.lat, s.lon)
}

async function lookupZone(lat, lon) {
  zoneDetecting.value = true
  detectedZone.value  = null
  zoneNotFound.value  = false
  try {
    const res = await window.axios.get(route('zone.lookup'), { params: { lat, lon } })
    const feats = res.data?.features || []
    if (feats.length > 0) {
      const a    = feats[0].attributes
      // Fields: SUBURB, REGION, DISTRICT, MREMAIL
      const zone = [a.SUBURB, a.REGION, a.DISTRICT].filter(Boolean).join(' — ')
      detectedZone.value = { name: zone || 'Zone detected' }
      // MREMAIL is the electricity meter reading email only — water uses municipality default
      if (a.MREMAIL) {
        newAccount.electricity_email = a.MREMAIL
      }
    } else {
      zoneNotFound.value = true
    }
  } catch { zoneNotFound.value = true }
  finally  { zoneDetecting.value = false }
}

function clearAddress() {
  newAccount.address  = ''
  newAccount.latitude = null; newAccount.longitude = null
  addressSearch.value = ''; addressSuggestions.value = []
  streetNo.value      = ''
  detectedZone.value  = null; zoneNotFound.value = false
}

// ── Province / Region / Tariff ────────────────────────────────────────────────
const selectedProvince = ref('')
const tariffTemplates  = ref([])
const tariffLoading    = ref(false)

const provinces = computed(() => {
  const s = new Set(props.regions.filter(r => r.province).map(r => r.province))
  return [...s].sort()
})

const filteredRegions = computed(() => {
  if (!selectedProvince.value) return props.regions
  return props.regions.filter(r => r.province === selectedProvince.value)
})

function onProvinceChange() {
  newAccount.region_id          = ''
  newAccount.tariff_template_id = ''
  tariffTemplates.value         = []
  if (!detectedZone.value) { newAccount.water_email = ''; newAccount.electricity_email = '' }
}

async function onRegionChange() {
  newAccount.tariff_template_id = ''
  tariffTemplates.value         = []
  newAccount.zone_id            = null

  if (!detectedZone.value) {
    const region = props.regions.find(r => String(r.id) === String(newAccount.region_id))
    if (region) {
      newAccount.water_email       = region.water_email       || ''
      newAccount.electricity_email = region.electricity_email || ''
    }
  }

  if (!newAccount.region_id) return
  tariffLoading.value = true
  try {
    const res = await window.axios.get(route('get-tariff-templates-by-region', { regionId: newAccount.region_id }))
    tariffTemplates.value = res.data?.data || []
  } catch { tariffTemplates.value = [] }
  finally { tariffLoading.value = false }
}

// ── Meters ────────────────────────────────────────────────────────────────────
const newMeter   = reactive({ meter_type_id: '', meter_number: '', meter_title: '' })
const addingMeter = ref(false)
const meterTypes  = ref(props.meterTypes || [])

function meterTypeLabel(id) {
  return meterTypes.value.find(t => t.id === id)?.title || 'Unknown'
}

async function addMeter() {
  if (!newMeter.meter_type_id || !newMeter.meter_number) {
    showFlash('Meter type and meter number are required.', false)
    return
  }
  addingMeter.value = true
  try {
    const res = await window.axios.post(route('account-manager.meter.store'), {
      account_id:     selectedAccount.value.id,
      meter_type_id:  newMeter.meter_type_id,
      meter_number:   newMeter.meter_number,
      meter_title:    newMeter.meter_title || undefined,
    })
    if (res.data.success) {
      // selectedAccount IS the same reference as the item in userAccounts — push once only
      selectedAccount.value.meters.push(res.data.meter)
      newMeter.meter_type_id = ''; newMeter.meter_number = ''; newMeter.meter_title = ''
      showFlash('Meter added.', true)
    } else {
      showFlash(res.data.message || 'Failed to add meter.', false)
    }
  } catch (e) {
    showFlash(e.response?.data?.message || e.message, false)
  } finally {
    addingMeter.value = false
  }
}

async function removeMeter(m) {
  if (!confirm(`Remove meter ${m.meter_number}?`)) return
  try {
    const res = await window.axios.delete(route('account-manager.meter.delete', { id: m.id }))
    if (res.data.success) {
      selectedAccount.value.meters = selectedAccount.value.meters.filter(x => x.id !== m.id)
      const acc = userAccounts.value.find(a => a.id === selectedAccount.value.id)
      if (acc) acc.meters = acc.meters.filter(x => x.id !== m.id)
      showFlash('Meter removed.', true)
    } else {
      showFlash(res.data.message, false)
    }
  } catch (e) {
    showFlash(e.response?.data?.message || e.message, false)
  }
}
</script>

<style scoped>
.section-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.6rem; height: 1.6rem;
  border-radius: 50%;
  background: #4e73df;
  color: #fff;
  font-size: 0.8rem;
  font-weight: 700;
  margin-right: 0.6rem;
  flex-shrink: 0;
}
.account-card {
  border: 1px solid #e3e6f0;
  border-radius: 0.35rem;
  padding: 0.75rem 1rem;
  margin-bottom: 0.5rem;
  cursor: pointer;
  transition: all 0.15s;
  background: #fff;
}
.account-card:hover { background: #f0f4ff; border-color: #4e73df; }
.account-card--active { background: #eef2ff; border-color: #4e73df; border-width: 2px; }
.badge-secondary { background: #858796; color: #fff; font-size: 0.72rem; padding: 0.2rem 0.45rem; }
.btn-xs { padding: 0.2rem 0.5rem; font-size: 0.75rem; }
.alert { padding: 0.65rem 1rem; border-radius: 0.35rem; position: relative; }
.alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-danger  { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.alert-info    { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
.alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
.close { position: absolute; top: 0.4rem; right: 0.75rem; background: none; border: none; font-size: 1.1rem; cursor: pointer; }
.card { border: none; border-radius: 0.35rem; }
.card-header { background: #f8f9fc; border-bottom: 1px solid #e3e6f0; }
.form-group label { font-weight: 600; font-size: 0.85rem; color: #5a6070; margin-bottom: 0.3rem; }
.list-group-item-action:hover { background: #f8f9fc; }
.table th, .table td { vertical-align: middle; font-size: 0.85rem; }
.user-card { padding: 0.25rem; }
</style>
