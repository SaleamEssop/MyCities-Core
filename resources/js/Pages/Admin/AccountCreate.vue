<template>
  <AdminLayout>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
      <h1 class="h3 mb-0 text-gray-800">Create Account</h1>
      <a :href="route('account-list')" class="btn btn-sm btn-secondary shadow-sm">
        <i class="fas fa-arrow-left fa-sm"></i> Back to Accounts
      </a>
    </div>

    <div v-if="globalError" class="alert alert-danger">{{ globalError }}</div>

    <form @submit.prevent="submit">

      <!-- ═══════════════════════════════════════════════════════ -->
      <!-- SECTION 1 — Select User                                 -->
      <!-- ═══════════════════════════════════════════════════════ -->
      <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex align-items-center">
          <span class="badge badge-primary mr-2">1</span>
          <h6 class="m-0 font-weight-bold text-primary">Select User</h6>
        </div>
        <div class="card-body">
          <div class="form-group mb-2">
            <label class="font-weight-bold">Search by name, email or phone</label>
            <div class="input-group">
              <input v-model="userSearch" type="text" class="form-control"
                     placeholder="Start typing a name, email or phone number..."
                     @input="searchUsers" />
              <div class="input-group-append">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
              </div>
            </div>
          </div>

          <!-- Search Results -->
          <div v-if="userResults.length > 0 && !selectedUser" class="list-group mb-3" style="max-height:220px;overflow-y:auto;">
            <button v-for="u in userResults" :key="u.id" type="button"
                    class="list-group-item list-group-item-action"
                    @click="selectUser(u)">
              <strong>{{ u.name }}</strong>
              <span class="text-muted ml-2">{{ u.email }}</span>
              <span v-if="u.phone" class="text-muted ml-2">| {{ u.phone }}</span>
            </button>
          </div>

          <!-- Selected User Card -->
          <div v-if="selectedUser" class="alert alert-success d-flex align-items-center justify-content-between mb-0">
            <div>
              <i class="fas fa-user-check mr-2"></i>
              <strong>{{ selectedUser.name }}</strong>
              <span class="ml-2 text-muted">{{ selectedUser.email }}</span>
              <span v-if="selectedUser.phone" class="ml-2 text-muted">| {{ selectedUser.phone }}</span>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" @click="clearUser">Change</button>
          </div>
          <div v-if="errors.user_id" class="text-danger small mt-1">{{ errors.user_id }}</div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════ -->
      <!-- SECTION 2 — Account Info                                -->
      <!-- ═══════════════════════════════════════════════════════ -->
      <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex align-items-center">
          <span class="badge badge-primary mr-2">2</span>
          <h6 class="m-0 font-weight-bold text-primary">Account Information</h6>
        </div>
        <div class="card-body">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="font-weight-bold">Name on Account <span class="text-danger">*</span></label>
                <input v-model="form.name_on_bill" type="text" class="form-control"
                       placeholder="As it appears on the municipal bill" required />
                <div v-if="errors.name_on_bill" class="text-danger small mt-1">{{ errors.name_on_bill }}</div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label class="font-weight-bold">Account Number <span class="text-danger">*</span></label>
                <input v-model="form.account_number" type="text" class="form-control"
                       placeholder="As per municipal bill" required />
                <div v-if="errors.account_number" class="text-danger small mt-1">{{ errors.account_number }}</div>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label class="font-weight-bold">Account Description</label>
                <input v-model="form.optional_information" type="text" class="form-control"
                       placeholder="e.g. Main House, Granny Cottage" />
                <small class="text-muted">Optional label to distinguish multiple accounts for the same user.</small>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label class="font-weight-bold">Bill Day <span class="text-danger">*</span></label>
                <input v-model.number="form.bill_day" type="number" min="1" max="31" class="form-control"
                       placeholder="1–31" required />
                <div v-if="errors.bill_day" class="text-danger small mt-1">{{ errors.bill_day }}</div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label class="font-weight-bold">Read Day <small class="text-muted">(auto)</small></label>
                <input :value="readDay" type="text" class="form-control" readonly />
                <small class="text-muted">Bill Day − 5</small>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════ -->
      <!-- SECTION 3 — Address & ArcGIS Zone Lookup                -->
      <!-- ═══════════════════════════════════════════════════════ -->
      <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex align-items-center">
          <span class="badge badge-primary mr-2">3</span>
          <h6 class="m-0 font-weight-bold text-primary">Address &amp; Billing Zone</h6>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label class="font-weight-bold">Property Address</label>
            <div class="input-group">
              <input v-model="addressSearch" type="text" class="form-control"
                     placeholder="Start typing an address..."
                     @input="suggestAddress" />
              <div class="input-group-append">
                <span class="input-group-text">
                  <i v-if="addressSearching" class="fas fa-spinner fa-spin"></i>
                  <i v-else class="fas fa-map-marker-alt"></i>
                </span>
              </div>
            </div>
            <small class="text-muted">Address search powered by ArcGIS. Select a suggestion to auto-fill coordinates.</small>
          </div>

          <!-- Address Suggestions -->
          <div v-if="addressSuggestions.length > 0" class="list-group mb-3" style="max-height:200px;overflow-y:auto;">
            <button v-for="s in addressSuggestions" :key="s.magicKey" type="button"
                    class="list-group-item list-group-item-action"
                    @click="selectAddress(s)">
              <i class="fas fa-map-marker-alt text-danger mr-2"></i>{{ s.text }}
            </button>
          </div>

          <!-- Confirmed Address -->
          <div v-if="form.address" class="alert alert-info mb-3">
            <i class="fas fa-check-circle mr-2"></i>
            <strong>{{ form.address }}</strong>
            <span v-if="form.latitude" class="text-muted ml-2 small">
              ({{ form.latitude.toFixed(5) }}, {{ form.longitude.toFixed(5) }})
            </span>
            <button type="button" class="btn btn-sm btn-link p-0 ml-2" @click="clearAddress">
              <i class="fas fa-times text-muted"></i>
            </button>
          </div>

          <!-- Zone Detection Result -->
          <div v-if="zoneDetecting" class="text-muted small mb-2">
            <i class="fas fa-spinner fa-spin mr-1"></i> Detecting billing zone from address...
          </div>
          <div v-if="detectedZone" class="alert alert-success mb-3">
            <i class="fas fa-map mr-2"></i>
            <strong>Billing Zone Detected:</strong> {{ detectedZone.zone_name || detectedZone.name || 'Zone matched' }}
            <div class="mt-1 small">
              <span v-if="resolvedWaterEmail"><i class="fas fa-tint text-primary mr-1"></i> Water: {{ resolvedWaterEmail }}</span>
              <span v-if="resolvedElecEmail" class="ml-3"><i class="fas fa-bolt text-warning mr-1"></i> Electricity: {{ resolvedElecEmail }}</span>
            </div>
          </div>
          <div v-if="zoneNotFound && form.address" class="alert alert-warning mb-0">
            <i class="fas fa-info-circle mr-2"></i>
            No ArcGIS zone found for this address. Billing emails will be set from the selected municipality defaults (Section 4).
          </div>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════════════════ -->
      <!-- SECTION 4 — Region & Tariff Template                    -->
      <!-- ═══════════════════════════════════════════════════════ -->
      <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex align-items-center">
          <span class="badge badge-primary mr-2">4</span>
          <h6 class="m-0 font-weight-bold text-primary">Region &amp; Tariff Template</h6>
        </div>
        <div class="card-body">
          <div class="row">
            <!-- Province -->
            <div class="col-md-4">
              <div class="form-group">
                <label class="font-weight-bold">Province</label>
                <select v-model="selectedProvince" class="form-control" @change="onProvinceChange">
                  <option value="">— Select Province —</option>
                  <option v-for="p in provinces" :key="p" :value="p">{{ p }}</option>
                </select>
              </div>
            </div>
            <!-- Municipality -->
            <div class="col-md-4">
              <div class="form-group">
                <label class="font-weight-bold">Municipality</label>
                <select v-model="form.region_id" class="form-control"
                        :disabled="!selectedProvince" @change="onRegionChange">
                  <option value="">— Select Municipality —</option>
                  <option v-for="r in filteredRegions" :key="r.id" :value="r.id">
                    {{ r.municipality || r.name }}
                  </option>
                </select>
                <div v-if="errors.region_id" class="text-danger small mt-1">{{ errors.region_id }}</div>
              </div>
            </div>
            <!-- Tariff Template -->
            <div class="col-md-4">
              <div class="form-group">
                <label class="font-weight-bold">Tariff Template</label>
                <select v-model="form.tariff_template_id" class="form-control"
                        :disabled="!form.region_id || tariffTemplatesLoading">
                  <option value="">— Select Tariff —</option>
                  <option v-for="t in tariffTemplates" :key="t.id" :value="t.id">{{ t.template_name }}</option>
                </select>
                <small v-if="tariffTemplatesLoading" class="text-muted">Loading...</small>
                <div v-if="errors.tariff_template_id" class="text-danger small mt-1">{{ errors.tariff_template_id }}</div>
              </div>
            </div>
          </div>

          <!-- Billing Emails (resolved) -->
          <div v-if="form.region_id || detectedZone" class="card bg-light mt-2">
            <div class="card-body py-3">
              <h6 class="font-weight-bold mb-2">Billing Emails</h6>
              <div v-if="detectedZone" class="text-success small mb-1">
                <i class="fas fa-check-circle mr-1"></i> Emails resolved from ArcGIS zone detection
              </div>
              <div v-else-if="form.region_id" class="text-muted small mb-1">
                <i class="fas fa-info-circle mr-1"></i> Using municipality default emails
              </div>
              <div class="row">
                <div class="col-md-6">
                  <div class="form-group mb-0">
                    <label class="small font-weight-bold">Water Email</label>
                    <input v-model="form.water_email" type="email" class="form-control form-control-sm"
                           :readonly="!!detectedZone"
                           placeholder="water@municipality.gov.za" />
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group mb-0">
                    <label class="small font-weight-bold">Electricity Email</label>
                    <input v-model="form.electricity_email" type="email" class="form-control form-control-sm"
                           :readonly="!!detectedZone"
                           placeholder="electricity@municipality.gov.za" />
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Submit -->
      <div class="d-flex gap-2 mb-4">
        <button type="submit" class="btn btn-primary btn-lg" :disabled="saving">
          <span v-if="saving"><i class="fas fa-spinner fa-spin mr-1"></i> Creating Account...</span>
          <span v-else><i class="fas fa-check mr-1"></i> Create Account</span>
        </button>
        <a :href="route('account-list')" class="btn btn-secondary btn-lg">Cancel</a>
      </div>

    </form>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

// ── Props ─────────────────────────────────────────────────────
const props = defineProps({
  regions: { type: Array, default: () => [] },
})

// ── ArcGIS constants (from MyCities-Cline/frontend/src/boot/axios.js) ──
const ARCGIS_TOKEN = 'AAPKc12c49d88ad5489486e82db8ebefb94aXNVU8kLARKQJ0rA5KFeUOYRjHqTU9l2phoZf1pFANCXNR-hkFOOQJmeFUYp4nnzQ'
const ARCGIS_GEOCODE_URL = 'https://geocode-api.arcgis.com/arcgis/rest/services/World/GeocodeServer'
const ARCGIS_FEATURE_URL = 'https://services3.arcgis.com/HO0zfySJshlD6Twu/arcgis/rest/services/MeterReadingSuburbs/FeatureServer/0/query'

// ── State ──────────────────────────────────────────────────────
const globalError       = ref('')
const saving            = ref(false)
const errors            = ref({})

// User search
const userSearch    = ref('')
const userResults   = ref([])
const selectedUser  = ref(null)
let userSearchTimer = null

// Address / ArcGIS
const addressSearch       = ref('')
const addressSuggestions  = ref([])
const addressSearching    = ref(false)
const zoneDetecting       = ref(false)
const detectedZone        = ref(null)
const zoneNotFound        = ref(false)
let addressSearchTimer    = null

// Tariff templates
const tariffTemplates        = ref([])
const tariffTemplatesLoading = ref(false)

// Province / Region
const selectedProvince = ref('')

// Form data
const form = reactive({
  user_id:              null,
  name_on_bill:         '',
  account_number:       '',
  optional_information: '',
  bill_day:             15,
  address:              '',
  latitude:             null,
  longitude:            null,
  region_id:            '',
  zone_id:              null,
  tariff_template_id:   '',
  water_email:          '',
  electricity_email:    '',
})

// ── Computed ───────────────────────────────────────────────────
const readDay = computed(() => {
  const b = parseInt(form.bill_day)
  if (!b) return ''
  const r = b > 5 ? b - 5 : 30 + (b - 5)
  return r
})

const provinces = computed(() => {
  const set = new Set()
  props.regions.forEach(r => { if (r.province) set.add(r.province) })
  return [...set].sort()
})

const filteredRegions = computed(() => {
  if (!selectedProvince.value) return props.regions
  return props.regions.filter(r => r.province === selectedProvince.value)
})

const resolvedWaterEmail = computed(() => form.water_email)
const resolvedElecEmail  = computed(() => form.electricity_email)

// ── User Search ────────────────────────────────────────────────
function searchUsers() {
  clearTimeout(userSearchTimer)
  if (userSearch.value.length < 2) { userResults.value = []; return }
  userSearchTimer = setTimeout(async () => {
    try {
      const res = await window.axios.get(route('user-accounts.manager.search'), {
        params: { q: userSearch.value }
      })
      userResults.value = res.data?.data || res.data || []
    } catch { userResults.value = [] }
  }, 300)
}

function selectUser(user) {
  selectedUser.value  = user
  form.user_id        = user.id
  userResults.value   = []
  userSearch.value    = user.name
}

function clearUser() {
  selectedUser.value = null
  form.user_id       = null
  userSearch.value   = ''
  userResults.value  = []
}

// ── ArcGIS Address Search ──────────────────────────────────────
function suggestAddress() {
  clearTimeout(addressSearchTimer)
  addressSuggestions.value = []
  if (addressSearch.value.length < 3) return
  addressSearchTimer = setTimeout(async () => {
    addressSearching.value = true
    try {
      const res = await window.axios.get(
        `${ARCGIS_GEOCODE_URL}/suggest`,
        { params: { f: 'pjson', token: ARCGIS_TOKEN, text: addressSearch.value, countryCode: 'ZAF' } }
      )
      addressSuggestions.value = res.data?.suggestions || []
    } catch { addressSuggestions.value = [] }
    finally { addressSearching.value = false }
  }, 350)
}

async function selectAddress(suggestion) {
  addressSuggestions.value = []
  addressSearch.value      = suggestion.text
  form.address             = suggestion.text
  form.latitude            = null
  form.longitude           = null
  detectedZone.value       = null
  zoneNotFound.value       = false

  try {
    const res = await window.axios.get(
      `${ARCGIS_GEOCODE_URL}/findAddressCandidates`,
      {
        params: {
          f: 'pjson',
          token: ARCGIS_TOKEN,
          singleLine: suggestion.text,
          magicKey: suggestion.magicKey,
          outSR: '{"wkid":102100}',
          countryCode: 'ZAF',
        }
      }
    )
    const candidates = res.data?.candidates || []
    if (candidates.length > 0) {
      const loc     = candidates[0].location
      form.latitude  = candidates[0].attributes?.Y || null
      form.longitude = candidates[0].attributes?.X || null
      await lookupZoneFromGeometry(loc)
    }
  } catch (e) {
    console.error('ArcGIS findAddressCandidates error:', e)
  }
}

async function lookupZoneFromGeometry(geometry) {
  zoneDetecting.value = true
  detectedZone.value  = null
  zoneNotFound.value  = false
  try {
    const res = await window.axios.get(ARCGIS_FEATURE_URL, {
      params: {
        f:            'json',
        returnGeometry: false,
        spatialRel:   'esriSpatialRelIntersects',
        geometryType: 'esriGeometryPoint',
        geometry:     JSON.stringify(geometry),
        inSR:         102100,
        outFields:    '*',
        outSR:        102100,
      }
    })
    const features = res.data?.features || []
    if (features.length > 0) {
      const attrs = features[0].attributes
      detectedZone.value = { name: attrs.ZONE_NAME || attrs.zone || attrs.Name || 'Zone' }
      // Try to match with a local region zone by name or set emails from feature attributes
      if (attrs.WATER_EMAIL || attrs.water_email) form.water_email = attrs.WATER_EMAIL || attrs.water_email
      if (attrs.ELEC_EMAIL || attrs.electricity_email) form.electricity_email = attrs.ELEC_EMAIL || attrs.electricity_email
    } else {
      zoneNotFound.value = true
    }
  } catch (e) {
    zoneNotFound.value = true
    console.error('ArcGIS zone lookup error:', e)
  } finally {
    zoneDetecting.value = false
  }
}

function clearAddress() {
  form.address   = ''
  form.latitude  = null
  form.longitude = null
  addressSearch.value      = ''
  addressSuggestions.value = []
  detectedZone.value       = null
  zoneNotFound.value       = false
}

// ── Region / Province / Tariff ─────────────────────────────────
function onProvinceChange() {
  form.region_id          = ''
  form.tariff_template_id = ''
  tariffTemplates.value   = []
  if (!detectedZone.value) {
    form.water_email       = ''
    form.electricity_email = ''
  }
}

async function onRegionChange() {
  form.tariff_template_id = ''
  tariffTemplates.value   = []
  form.zone_id            = null

  // Set default emails from region (if ArcGIS didn't resolve them)
  if (!detectedZone.value) {
    const region = props.regions.find(r => String(r.id) === String(form.region_id))
    if (region) {
      form.water_email       = region.water_email || ''
      form.electricity_email = region.electricity_email || ''
    }
  }

  if (!form.region_id) return
  tariffTemplatesLoading.value = true
  try {
    const res = await window.axios.get(route('get-tariff-templates-by-region', { regionId: form.region_id }))
    tariffTemplates.value = res.data?.data || []
  } catch { tariffTemplates.value = [] }
  finally { tariffTemplatesLoading.value = false }
}

// ── Submit ─────────────────────────────────────────────────────
async function submit() {
  errors.value     = {}
  globalError.value = ''
  if (!form.user_id) { errors.value.user_id = 'Please select a user.'; return }
  saving.value = true
  try {
    await window.axios.post(route('accounts.store'), {
      user_id:              form.user_id,
      name_on_bill:         form.name_on_bill,
      account_number:       form.account_number,
      optional_information: form.optional_information,
      bill_day:             form.bill_day,
      address:              form.address,
      latitude:             form.latitude,
      longitude:            form.longitude,
      region_id:            form.region_id || null,
      zone_id:              form.zone_id   || null,
      tariff_template_id:   form.tariff_template_id || null,
      water_email:          form.water_email,
      electricity_email:    form.electricity_email,
    })
    window.location.href = route('account-list')
  } catch (e) {
    if (e.response?.status === 422) {
      errors.value = e.response.data.errors || {}
    } else {
      globalError.value = 'An error occurred while creating the account. Please try again.'
    }
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.gap-2 { gap: 0.5rem; }
.badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.6rem;
  height: 1.6rem;
  border-radius: 50%;
  font-size: 0.8rem;
  font-weight: 700;
}
.badge-primary { background-color: #4e73df; color: #fff; }
.list-group-item-action:hover { background-color: #f8f9fc; }
.input-group-text { background: #f8f9fc; }
.card { border-radius: 0.35rem; }
.card-header { background-color: #f8f9fc; border-bottom: 1px solid #e3e6f0; }
</style>
