<template>
  <div class="d-flex" id="wrapper">
    <!-- Sidebar -->
    <div class="bg-gradient-primary sidebar" :class="{ 'toggled': sidebarCollapsed }" id="accordionSidebar">
      <a class="sidebar-brand d-flex align-items-center justify-content-center" href="/admin/">
        <div class="sidebar-brand-icon">
          <i class="fas fa-city"></i>
        </div>
        <div class="sidebar-brand-text mx-3">MyCities</div>
      </a>
      <hr class="sidebar-divider my-0">
      
      <!-- Nav Items -->
      <nav class="nav flex-column">
        <template v-for="(item, idx) in menuItems" :key="item.route || item.label + String(idx)">
          <!-- Parent with dropdown children -->
          <div v-if="item.children" class="nav-dropdown">
            <button
              type="button"
              class="nav-item nav-item-toggle"
              :class="{ 'active': isActiveChild(item.children) }"
              @click="toggleCalculator = !toggleCalculator"
              aria-expanded="false"
            >
              <i :class="item.icon"></i>
              <span>{{ item.label }}</span>
              <i class="fas fa-fw fa-chevron-down nav-chevron" :class="{ 'nav-chevron--open': toggleCalculator }"></i>
            </button>
            <div v-show="toggleCalculator" class="nav-dropdown-children">
              <Link
                v-for="ch in item.children"
                :key="ch.route"
                :href="route(ch.route)"
                class="nav-item nav-item-child"
                :class="{ 'active': isActive(ch.routes) }"
              >
                <span>{{ ch.label }}</span>
              </Link>
            </div>
          </div>
          <!-- External link (opens in new tab) -->
          <a
            v-else-if="item.external"
            :href="route(item.route)"
            target="_blank"
            rel="noopener noreferrer"
            class="nav-item"
          >
            <i :class="item.icon"></i>
            <span>{{ item.label }}</span>
            <i class="fas fa-external-link-alt nav-item-ext"></i>
          </a>
          <!-- Internal Inertia link -->
          <Link
            v-else
            :href="route(item.route)"
            class="nav-item"
            :class="{ 'active': isActive(item.routes) }"
          >
            <i :class="item.icon"></i>
            <span>{{ item.label }}</span>
          </Link>
        </template>
      </nav>
      
      <hr class="sidebar-divider d-none d-md-block">
      <div class="text-center d-none d-md-inline">
        <button class="rounded-circle border-0" @click="sidebarCollapsed = !sidebarCollapsed">
          <i class="fas fa-bars"></i>
        </button>
      </div>
    </div>

    <!-- Content Wrapper -->
    <div id="content-wrapper" class="d-flex flex-column flex-grow-1">
      <!-- Main Content -->
      <div id="content">
        <!-- Topbar -->
        <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
          <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3" @click="sidebarCollapsed = !sidebarCollapsed">
            <i class="fa fa-bars"></i>
          </button>
          
          <ul class="navbar-nav ml-auto">
            <li class="nav-item dropdown no-arrow">
              <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown">
                <span class="mr-2 d-none d-lg-inline text-gray-600 small">{{ user?.name || 'Admin' }}</span>
                <img class="img-profile rounded-circle" src="/img/undraw_profile.svg">
              </a>
              <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in">
                <Link :href="route('admin.logout')" method="post" as="button" class="dropdown-item" style="width:100%;text-align:left;background:none;border:none;cursor:pointer;">
                  <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                  Logout
                </Link>
              </div>
            </li>
          </ul>
        </nav>

        <!-- Begin Page Content -->
        <div class="container-fluid">
          <!-- Flash Messages -->
          <div v-if="$page.props.flash?.message" :class="['alert', $page.props.flash.class || 'alert-info']">
            {{ $page.props.flash.message }}
          </div>
          
          <!-- Page Content -->
          <slot />
        </div>
      </div>

      <!-- Footer -->
      <footer class="sticky-footer bg-white">
        <div class="container my-auto">
          <div class="copyright text-center my-auto">
            <span>Copyright &copy; MyCities {{ new Date().getFullYear() }}</span>
          </div>
        </div>
      </footer>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()
const sidebarCollapsed = ref(false)
const toggleCalculator = ref(false)
watch(
  () => page.props.ziggy?.current,
  (r) => { if (r === 'calculator' || r === 'calculator.date-to-date') toggleCalculator.value = true },
  { immediate: true }
)

const user = computed(() => page.props.auth?.user)

const menuItems = [
  { label: 'Dashboard', icon: 'fas fa-fw fa-tachometer-alt', route: 'admin.home', routes: ['admin.home'] },
  { label: 'User Setup', icon: 'fas fa-fw fa-user-plus', route: 'user.setup', routes: ['user.setup'] },
  { label: 'Account Manager', icon: 'fas fa-fw fa-users-cog', route: 'account-manager', routes: ['account-manager'] },
  { label: 'Sites', icon: 'fas fa-fw fa-location-arrow', route: 'show-sites', routes: ['show-sites', 'create-site-form'] },
  { label: 'Regions', icon: 'fas fa-fw fa-map-marker-alt', route: 'regions-list', routes: ['regions-list', 'add-region-form'] },
  { label: 'Tariff Templates', icon: 'fas fa-fw fa-file-invoice-dollar', route: 'tariff-template', routes: ['tariff-template', 'tariff-template-create'] },
  { label: 'Pages', icon: 'fas fa-fw fa-file-alt', route: 'pages-list', routes: ['pages-list', 'pages-create'] },
  { label: 'Advertising', icon: 'fas fa-fw fa-ad', route: 'ads-list', routes: ['ads-list', 'ads-categories'] },
  { label: 'App View', icon: 'fas fa-fw fa-mobile-alt', route: 'app-view', routes: ['app-view'] },
  {
    label: 'Calculator',
    icon: 'fas fa-fw fa-calculator',
    route: 'calculator',
    children: [
      { label: 'Period to Period', route: 'calculator', routes: ['calculator'] },
      { label: 'Date to Date', route: 'calculator.date-to-date', routes: ['calculator.date-to-date'] },
    ],
  },
  { label: 'Alarms', icon: 'fas fa-fw fa-clock', route: 'alarms', routes: ['alarms'] },
  { label: 'Administrators', icon: 'fas fa-fw fa-user-shield', route: 'administrators.index', routes: ['administrators.index'] },
  { label: 'Settings', icon: 'fas fa-fw fa-cog', route: 'settings.index', routes: ['settings.index'] },
]

const isActive = (routes) => {
  const currentRoute = page.props.ziggy?.current
  return routes && currentRoute && routes.includes(currentRoute)
}

const isActiveChild = (children) => {
  return children && children.some(ch => isActive(ch.routes))
}
</script>

<style scoped>
/* Sidebar Styles */
.sidebar {
  width: 250px;
  min-height: 100vh;
  background: linear-gradient(180deg, #4e73df 0%, #224abe 100%);
  transition: all 0.3s;
}

.sidebar.toggled {
  width: 6rem;
}

.sidebar.toggled .sidebar-brand-text,
.sidebar.toggled span {
  display: none;
}

.sidebar-brand {
  height: 4.375rem;
  text-decoration: none;
  font-size: 1rem;
  font-weight: 800;
  padding: 1.5rem 1rem;
  text-align: center;
  text-transform: uppercase;
  letter-spacing: 0.05rem;
  z-index: 1;
}

.sidebar-brand-icon {
  font-size: 2rem;
}

.sidebar-brand-text {
  color: #fff;
}

.sidebar-divider {
  border-top: 1px solid rgba(255, 255, 255, 0.15);
  margin: 0 1rem 1rem;
}

.nav-item {
  display: flex;
  align-items: center;
  padding: 0.75rem 1rem;
  color: rgba(255, 255, 255, 0.8);
  text-decoration: none;
  transition: all 0.2s;
  cursor: pointer;
}

.nav-item:hover,
.nav-item.active {
  color: #fff;
  background: rgba(255, 255, 255, 0.1);
  text-decoration: none;
}

.nav-item.active {
  font-weight: 600;
}

.nav-item i {
  margin-right: 0.75rem;
  width: 1.25rem;
  text-align: center;
  flex-shrink: 0;
}

.nav-item-ext {
  margin-left: auto;
  margin-right: 0;
  font-size: 0.6rem;
  opacity: 0.5;
  width: auto;
}

.nav-dropdown {
  display: flex;
  flex-direction: column;
}
.nav-item-toggle {
  width: 100%;
  text-align: left;
  border: none;
  background: transparent;
  font: inherit;
}
.nav-chevron {
  margin-left: auto;
  margin-right: 0;
  transition: transform 0.2s;
}
.nav-chevron--open {
  transform: rotate(180deg);
}
.nav-dropdown-children {
  display: flex;
  flex-direction: column;
  background: rgba(0, 0, 0, 0.15);
  padding-left: 0.5rem;
}
.nav-item-child {
  padding-left: 2rem;
  font-size: 0.9rem;
}

/* Content Wrapper */
#content-wrapper {
  background-color: #f8f9fa;
  min-height: 100vh;
}

/* Topbar */
.topbar {
  height: 4.375rem;
}

.img-profile {
  height: 2rem;
  width: 2rem;
}

/* Footer */
.sticky-footer {
  padding: 2rem 0;
}

/* Alert Styles */
.alert {
  padding: 0.75rem 1.25rem;
  margin-bottom: 1rem;
  border: 1px solid transparent;
  border-radius: 0.35rem;
}

.alert-info {
  color: #0c5460;
  background-color: #d1ecf1;
  border-color: #bee5eb;
}

/* Sidebar Toggle */
.rounded-circle {
  border-radius: 50% !important;
}

.border-0 {
  border: 0 !important;
  background: rgba(255, 255, 255, 0.2);
  color: #fff;
  padding: 0.5rem;
}

.border-0:hover {
  background: rgba(255, 255, 255, 0.3);
}
</style>