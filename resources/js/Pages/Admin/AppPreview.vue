<template>
  <AdminLayout>

    <div class="d-flex align-items-center justify-content-between mb-3">
      <h1 class="h3 mb-0 text-gray-800">
        <i class="fas fa-mobile-alt mr-2"></i>App View
      </h1>
      <div v-if="switchedUser" class="alert alert-warning py-1 px-3 mb-0 d-flex align-items-center">
        <i class="fas fa-user-secret mr-2"></i>
        Viewing app as <strong class="mx-1">{{ switchedUser.name }}</strong>
        <a :href="route('app-view.restore-admin')" class="btn btn-sm btn-warning ml-3">
          <i class="fas fa-undo mr-1"></i>Back to Admin
        </a>
      </div>
    </div>

    <div class="av-layout">

      <!-- ── Phone frame ── -->
      <div class="av-phone-col">
        <div class="av-phone-frame">
          <div class="av-phone-notch"></div>
          <div class="av-phone-screen">
            <iframe
              ref="frame"
              :src="frameUrl"
              title="MyCities App"
              @load="onFrameLoad"
            ></iframe>
          </div>
          <div class="av-phone-bar"></div>
        </div>

        <!-- Screen navigation under phone -->
        <div class="av-screen-nav mt-3">
          <button
            v-for="s in screens"
            :key="s.id"
            class="av-screen-btn"
            :class="{ active: activeScreen === s.id }"
            @click="gotoScreen(s)"
            :title="s.label"
          >
            <i :class="s.icon"></i>
            <span>{{ s.label }}</span>
          </button>
        </div>
      </div>

      <!-- ── Right management panel ── -->
      <div class="av-panel">

        <!-- View as user -->
        <div class="card shadow mb-3">
          <div class="card-header py-2">
            <h6 class="m-0 font-weight-bold text-primary">
              <i class="fas fa-user-circle mr-2"></i>View app as user
            </h6>
          </div>
          <div class="card-body py-2">
            <p class="small text-muted mb-2">
              Switch to a user account to see their dashboard, billing and readings.
            </p>
            <div v-if="users.length">
              <a
                v-for="u in users"
                :key="u.id"
                :href="route('app-view.switch-user', u.id)"
                class="btn btn-sm btn-outline-primary btn-block text-left mb-1"
              >
                <i class="fas fa-sign-in-alt mr-2"></i>{{ u.name }}
                <small class="text-muted ml-1">{{ u.email }}</small>
              </a>
            </div>
            <p v-else class="small text-muted mb-0">
              No regular users yet.
              <a :href="route('user.setup')">Create a user →</a>
            </p>
          </div>
        </div>

        <!-- Info / Pages -->
        <div class="card shadow mb-3" style="border-left:4px solid #009BA4">
          <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
              <i class="fas fa-file-alt mr-2"></i>Info Pages ({{ pages.length }})
            </h6>
            <a :href="route('pages-create')" class="btn btn-sm btn-primary">
              <i class="fas fa-plus"></i>
            </a>
          </div>
          <div class="card-body p-0">
            <div
              v-for="page in pages"
              :key="page.id"
              class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom"
            >
              <button class="btn btn-link p-0 text-left small" @click="gotoInfo">
                <i v-if="page.icon" :class="[page.icon, 'mr-1 text-muted']"></i>
                {{ page.parent_id ? '↳ ' : '' }}{{ page.title }}
              </button>
              <a :href="route('pages-edit', page.id)" class="btn btn-sm btn-outline-secondary py-0 px-2">
                <i class="fas fa-edit"></i>
              </a>
            </div>
            <div v-if="!pages.length" class="px-3 py-3 text-center text-muted small">
              No pages yet. <a :href="route('pages-create')">Create one →</a>
            </div>
          </div>
          <div v-if="pages.length" class="card-footer py-2">
            <a :href="route('pages-list')" class="btn btn-sm btn-outline-primary btn-block">
              <i class="fas fa-list mr-1"></i>Manage all pages
            </a>
          </div>
        </div>

        <!-- Quick links -->
        <div class="card shadow mb-3">
          <div class="card-header py-2">
            <h6 class="m-0 font-weight-bold text-primary">
              <i class="fas fa-external-link-alt mr-2"></i>Open on device
            </h6>
          </div>
          <div class="card-body py-2">
                    <a href="/user/info" target="_blank" class="btn btn-sm btn-outline-secondary btn-block text-left mb-1">
                      <i class="fas fa-mobile-alt mr-2"></i>Open app in new tab
                    </a>
            <a :href="route('pages-create')" class="btn btn-sm btn-outline-secondary btn-block text-left">
              <i class="fas fa-plus-circle mr-2"></i>Add new page
            </a>
          </div>
        </div>

      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { route } from 'ziggy-js'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  pages:        { type: Array,  default: () => [] },
  users:        { type: Array,  default: () => [] },
  switchedUser: { type: Object, default: null },
})

const frame = ref(null)

const screens = [
  { id: 'home',     label: 'Home',      icon: 'fas fa-home',           url: '/user/info' },
  { id: 'login',    label: 'Login',     icon: 'fas fa-sign-in-alt',    url: '/user/login?preview=1' },
  { id: 'dash',     label: 'Dashboard', icon: 'fas fa-tachometer-alt', url: '/user/dashboard' },
  { id: 'readings', label: 'Readings',  icon: 'fas fa-tint',           url: '/user/reading/water' },
  { id: 'account',  label: 'Account',   icon: 'fas fa-receipt',        url: '/user/account' },
]

const activeScreen = ref('home')
const frameUrl     = ref('/user/info')

function gotoScreen(s) {
  activeScreen.value = s.id
  frameUrl.value     = s.url
}

function gotoInfo() {
  gotoScreen(screens.find(s => s.id === 'home'))
}

function onFrameLoad() {
  // Try to detect current path from iframe to keep nav in sync
  try {
    const path = frame.value?.contentWindow?.location?.pathname ?? ''
    const matched = screens.find(s => path.startsWith(s.url.split('?')[0]))
    if (matched) activeScreen.value = matched.id
  } catch (_) {
    // cross-origin guard — ignore
  }
}
</script>

<style scoped>
/* ── Two-column layout ── */
.av-layout {
  display: flex;
  gap: 24px;
  align-items: flex-start;
}

/* ── Phone column ── */
.av-phone-col {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
}

/* ── Phone frame ── */
.av-phone-frame {
  width: 390px;
  background: #1a1a2e;
  border-radius: 40px;
  padding: 14px 14px 10px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.45);
}

.av-phone-notch {
  width: 120px;
  height: 28px;
  background: #1a1a2e;
  border-radius: 0 0 16px 16px;
  margin: 0 auto 8px;
}

.av-phone-screen {
  border-radius: 28px;
  overflow: hidden;
  height: 780px;
  background: #fff;
}

.av-phone-screen iframe {
  width: 100%;
  height: 100%;
  border: none;
  display: block;
}

.av-phone-bar {
  width: 100px;
  height: 5px;
  background: #444;
  border-radius: 3px;
  margin: 10px auto 0;
}

/* ── Screen navigation strip under phone ── */
.av-screen-nav {
  display: flex;
  gap: 4px;
  width: 390px;
  justify-content: space-between;
  flex-wrap: wrap;
}

.av-screen-btn {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 8px 4px;
  background: #fff;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  color: #757575;
  font-size: 0.62rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}
.av-screen-btn i      { font-size: 1rem; }
.av-screen-btn.active { background: #009BA4; color: #fff; border-color: #009BA4; }
.av-screen-btn:hover:not(.active) { background: #f5f5f5; color: #009BA4; border-color: #009BA4; }

/* ── Right management panel ── */
.av-panel {
  flex: 1;
  max-width: 380px;
}

.btn-block { display: block; width: 100%; }
.card      { border-radius: 0.35rem; }
.card-header { background-color: #f8f9fc; border-bottom: 1px solid #e3e6f0; }
</style>
