<template>
  <AdminLayout>
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
      <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
      <div class="d-flex align-items-center">
        <button 
          class="btn btn-primary shadow-sm mr-2" 
          :class="{ 'btn-danger': monitoringActive, 'btn-primary': !monitoringActive }"
          @click="toggleMonitoring"
          style="min-width: 160px;"
        >
          <i :class="monitoringActive ? 'fas fa-stop' : 'fas fa-play'"></i>
          <span>{{ monitoringActive ? 'Stop Monitoring' : 'Start Monitoring' }}</span>
        </button>
        <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
          <i class="fas fa-download fa-sm text-white-50"></i> Generate Report
        </a>
      </div>
    </div>

    <!-- Content Row -->
    <div class="row">
      <!-- Area Chart -->
      <div class="col-xl-8 col-lg-7">
        <div class="card shadow mb-4">
          <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Earnings Overview</h6>
            <div class="dropdown no-arrow">
              <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown">
                <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
              </a>
              <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in">
                <div class="dropdown-header">Dropdown Header:</div>
                <a class="dropdown-item" href="#">Action</a>
                <a class="dropdown-item" href="#">Another action</a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="#">Something else here</a>
              </div>
            </div>
          </div>
          <div class="card-body">
            <div class="chart-area" style="height: 100%; min-height: 300px;">
              <canvas id="myAreaChart"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Pie Chart -->
      <div class="col-xl-4 col-lg-5">
        <div class="card shadow mb-4">
          <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Revenue Sources</h6>
            <div class="dropdown no-arrow">
              <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown">
                <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
              </a>
              <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in">
                <div class="dropdown-header">Dropdown Header:</div>
                <a class="dropdown-item" href="#">Action</a>
                <a class="dropdown-item" href="#">Another action</a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="#">Something else here</a>
              </div>
            </div>
          </div>
          <div class="card-body">
            <div class="chart-pie pt-4 pb-2">
              <canvas id="myPieChart"></canvas>
            </div>
            <div class="mt-4 text-center small">
              <span class="mr-2">
                <i class="fas fa-circle text-primary"></i> Direct
              </span>
              <span class="mr-2">
                <i class="fas fa-circle text-success"></i> Social
              </span>
              <span class="mr-2">
                <i class="fas fa-circle text-info"></i> Referral
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Content Row -->
    <div class="row">
      <!-- Content Column -->
      <div class="col-lg-6 mb-4">
        <!-- Project Card Example -->
        <div class="card shadow mb-4">
          <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Projects</h6>
          </div>
          <div class="card-body">
            <h4 class="small font-weight-bold">Server Migration <span class="float-right">20%</span></h4>
            <div class="progress mb-4">
              <div class="progress-bar bg-danger" role="progressbar" style="width: 20%"></div>
            </div>
            <h4 class="small font-weight-bold">Sales Tracking <span class="float-right">40%</span></h4>
            <div class="progress mb-4">
              <div class="progress-bar bg-warning" role="progressbar" style="width: 40%"></div>
            </div>
            <h4 class="small font-weight-bold">Customer Database <span class="float-right">60%</span></h4>
            <div class="progress mb-4">
              <div class="progress-bar" role="progressbar" style="width: 60%"></div>
            </div>
            <h4 class="small font-weight-bold">Payout Details <span class="float-right">80%</span></h4>
            <div class="progress mb-4">
              <div class="progress-bar bg-info" role="progressbar" style="width: 80%"></div>
            </div>
            <h4 class="small font-weight-bold">Account Setup <span class="float-right">Complete!</span></h4>
            <div class="progress">
              <div class="progress-bar bg-success" role="progressbar" style="width: 100%"></div>
            </div>
          </div>
        </div>

        <!-- Color System -->
        <div class="row">
          <div class="col-lg-6 mb-4">
            <div class="card bg-primary text-white shadow">
              <div class="card-body">
                Primary
                <div class="text-white-50 small">#4e73df</div>
              </div>
            </div>
          </div>
          <div class="col-lg-6 mb-4">
            <div class="card bg-success text-white shadow">
              <div class="card-body">
                Success
                <div class="text-white-50 small">#1cc88a</div>
              </div>
            </div>
          </div>
          <div class="col-lg-6 mb-4">
            <div class="card bg-info text-white shadow">
              <div class="card-body">
                Info
                <div class="text-white-50 small">#36b9cc</div>
              </div>
            </div>
          </div>
          <div class="col-lg-6 mb-4">
            <div class="card bg-warning text-white shadow">
              <div class="card-body">
                Warning
                <div class="text-white-50 small">#f6c23e</div>
              </div>
            </div>
          </div>
          <div class="col-lg-6 mb-4">
            <div class="card bg-danger text-white shadow">
              <div class="card-body">
                Danger
                <div class="text-white-50 small">#e74a3b</div>
              </div>
            </div>
          </div>
          <div class="col-lg-6 mb-4">
            <div class="card bg-secondary text-white shadow">
              <div class="card-body">
                Secondary
                <div class="text-white-50 small">#858796</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-6 mb-4">
        <!-- Illustrations -->
        <div class="card shadow mb-4">
          <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Illustrations</h6>
          </div>
          <div class="card-body">
            <div class="text-center">
              <img class="img-fluid px-3 px-sm-4 mt-3 mb-4" style="width: 25rem;" src="/img/undraw_posting_photo.svg" alt="">
            </div>
            <p>Add some quality, svg illustrations to your project courtesy of <a target="_blank" rel="nofollow" href="https://undraw.co/">unDraw</a>, a constantly updated collection of beautiful svg images that you can use completely free and without attribution!</p>
            <a target="_blank" rel="nofollow" href="https://undraw.co/">Browse Illustrations on unDraw &rarr;</a>
          </div>
        </div>

        <!-- Approach -->
        <div class="card shadow mb-4">
          <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Development Approach</h6>
          </div>
          <div class="card-body">
            <p>SB Admin 2 makes extensive use of Bootstrap 4 utility classes in order to reduce CSS bloat and poor page performance. Custom CSS classes are used to create custom components and custom utility classes.</p>
            <p class="mb-0">Before working with this theme, you should become familiar with the Bootstrap framework, especially the utility classes.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Monitoring Timeline -->
    <div v-if="monitoringActive || monitoringSession" class="card shadow mb-4">
      <div class="card-header py-3 bg-primary text-white">
        <h6 class="m-0 font-weight-bold">
          <i class="fas fa-chart-line"></i> Monitoring Timeline
          <span v-if="monitoringSession" class="badge badge-light ml-2">{{ monitoringSession }}</span>
        </h6>
      </div>
      <div class="card-body">
        <div v-if="monitoringEvents.length === 0" class="text-muted">Loading events...</div>
        <div v-else class="timeline-events" style="max-height: 500px; overflow-y: auto;">
          <div v-for="(event, index) in monitoringEvents" :key="index" class="card mb-2" :class="`border-${getSeverityColor(event.severity)}`">
            <div class="card-body p-2">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <small class="text-muted">{{ formatEventTime(event.timestamp) }}</small>
                  <span class="badge" :class="`badge-${getSeverityColor(event.severity)}`">{{ event.severity }}</span>
                  <strong class="ml-2">{{ event.type }}</strong>
                </div>
              </div>
              <div class="mt-2">
                <pre class="mb-0" style="font-size: 0.85em; max-height: 150px; overflow: auto;">{{ JSON.stringify(event.data, null, 2) }}</pre>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const monitoringActive = ref(false)
const monitoringSession = ref(null)
const monitoringEvents = ref([])
let eventPollInterval = null

const toggleMonitoring = async () => {
  if (monitoringActive.value) {
    await stopMonitoring()
  } else {
    await startMonitoring()
  }
}

const startMonitoring = async () => {
  try {
    const response = await fetch('/admin/monitoring/start', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
      }
    })
    const data = await response.json()
    if (data.success) {
      monitoringActive.value = true
      monitoringSession.value = data.sessionId
      eventPollInterval = setInterval(() => loadTimeline(data.sessionId), 2000)
      loadTimeline(data.sessionId)
    }
  } catch (error) {
    console.error('Failed to start monitoring:', error)
  }
}

const stopMonitoring = async () => {
  if (!monitoringSession.value) return
  
  try {
    await fetch(`/admin/monitoring/stop/${monitoringSession.value}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
      }
    })
    monitoringActive.value = false
    if (eventPollInterval) {
      clearInterval(eventPollInterval)
      eventPollInterval = null
    }
  } catch (error) {
    console.error('Failed to stop monitoring:', error)
  }
}

const loadTimeline = async (sessionId) => {
  if (!sessionId) return
  try {
    const response = await fetch(`/admin/monitoring/events/${sessionId}`)
    const data = await response.json()
    if (data.success && data.session) {
      monitoringEvents.value = data.session.events || []
    }
  } catch (error) {
    console.error('Failed to load timeline:', error)
  }
}

const formatEventTime = (timestamp) => {
  if (!timestamp) return 'N/A'
  try {
    const date = new Date(timestamp.replace(' ', 'T'))
    return date.toLocaleString('en-GB', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: false
    })
  } catch (e) {
    return timestamp
  }
}

const getSeverityColor = (severity) => {
  const colors = {
    'info': 'info',
    'warning': 'warning',
    'error': 'danger',
    'critical': 'dark'
  }
  return colors[severity] || 'secondary'
}

onUnmounted(() => {
  if (eventPollInterval) {
    clearInterval(eventPollInterval)
  }
})
</script>

<style scoped>
/* Card Styles */
.card {
  border-radius: 0.35rem;
  box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}

.card-header {
  background-color: #f8f9fc;
  border-bottom: 1px solid #e3e6f0;
}

/* Progress Bar */
.progress {
  height: 0.5rem;
  border-radius: 0.25rem;
}

.progress-bar {
  transition: width 0.6s ease;
}

/* Color Cards */
.card.bg-primary { background-color: #4e73df !important; }
.card.bg-success { background-color: #1cc88a !important; }
.card.bg-info { background-color: #36b9cc !important; }
.card.bg-warning { background-color: #f6c23e !important; }
.card.bg-danger { background-color: #e74a3b !important; }
.card.bg-secondary { background-color: #858796 !important; }

/* Dropdown */
.dropdown-toggle::after {
  display: inline-block;
  margin-left: 0.255em;
  vertical-align: 0.255em;
  content: "";
  border-top: 0.3em solid;
  border-right: 0.3em solid transparent;
  border-left: 0.3em solid transparent;
}

/* Charts */
.chart-area, .chart-pie {
  position: relative;
  height: 100%;
  min-height: 300px;
}

/* Utility */
.shadow {
  box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
}

.mb-4 { margin-bottom: 1.5rem !important; }
.mt-4 { margin-top: 1.5rem !important; }
.mr-2 { margin-right: 0.5rem !important; }
.ml-2 { margin-left: 0.5rem !important; }
.mb-0 { margin-bottom: 0 !important; }
</style>