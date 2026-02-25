<template>
  <div class="ua-root">
    <div class="ua-phone">

      <!-- TOP BAR -->
      <header class="ua-header">
        <!-- Hamburger (left side) -->
        <button v-if="navPages.length" class="ua-hamburger" @click="drawerOpen = true" aria-label="Open navigation">
          <i class="fas fa-bars"></i>
        </button>
        <button v-if="showBack" class="ua-back-btn" @click="goBack">
          <i class="fas fa-arrow-left"></i>
        </button>
        <template v-if="title">
          <span class="ua-header-title">{{ title }}</span>
        </template>
        <template v-else>
          <div class="ua-header-logo">
            <span class="ua-logo-my">My</span><span class="ua-logo-cities">Cities</span>
          </div>
        </template>
        <!-- Login / Logout button -->
        <div class="ua-header-auth">
          <Link v-if="authUser" :href="route('user.logout')" class="ua-auth-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
          </Link>
          <Link v-else :href="route('user.login')" class="ua-auth-btn">
            <i class="fas fa-sign-in-alt"></i>
            <span>Login</span>
          </Link>
        </div>
      </header>

      <!-- ── SLIDE-OUT DRAWER ── -->
      <transition name="drawer">
        <div v-if="drawerOpen" class="ua-drawer-overlay" @click.self="drawerOpen = false">
          <nav class="ua-drawer">
            <!-- Drawer header -->
            <div class="ua-drawer-header">
              <div class="ua-drawer-logo">
                <span class="ua-logo-my">My</span><span class="ua-logo-cities">Cities</span>
              </div>
              <button class="ua-drawer-close" @click="drawerOpen = false" aria-label="Close navigation">
                <i class="fas fa-times"></i>
              </button>
            </div>

            <div class="ua-drawer-label">Pages</div>

            <!-- Page tree -->
            <template v-for="pg in navPages" :key="pg.id">
              <!-- Parent or standalone page -->
              <Link
                :href="'/user/info?tab=' + pg.id"
                class="ua-drawer-item"
                :class="{ 'has-children': pg.children && pg.children.length }"
                @click="drawerOpen = false"
              >
                <i v-if="pg.icon" :class="pg.icon" class="ua-drawer-icon"></i>
                <i v-else class="fas fa-file-alt ua-drawer-icon"></i>
                <span>{{ pg.title }}</span>
                <i v-if="pg.children && pg.children.length" class="fas fa-chevron-right ua-drawer-chevron"></i>
              </Link>

              <!-- Child pages (indented) -->
              <template v-if="pg.children && pg.children.length">
                <Link
                  v-for="child in pg.children"
                  :key="child.id"
                  :href="'/user/info?tab=' + pg.id + '&child=' + child.id"
                  class="ua-drawer-item ua-drawer-child"
                  @click="drawerOpen = false"
                >
                  <i class="fas fa-angle-right ua-drawer-child-bullet"></i>
                  <i v-if="child.icon" :class="child.icon" class="ua-drawer-icon"></i>
                  <span>{{ child.title }}</span>
                </Link>
              </template>
            </template>

          </nav>
        </div>
      </transition>

      <!-- MAIN SCROLLABLE CONTENT -->
      <main class="ua-content">
        <slot />
      </main>

      <!-- BOTTOM NAV -->
      <nav class="ua-bottom-nav">
        <Link :href="route('user.info')" class="ua-nav-btn" :class="{ active: isRoute('user.info') }">
          <i class="fas fa-home"></i>
          <span>Home</span>
        </Link>
        <template v-if="authUser">
          <Link :href="route('user.dashboard')" class="ua-nav-btn" :class="{ active: isRoute('user.dashboard') }">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
          </Link>
          <Link :href="route('user.reading.water')" class="ua-nav-btn"
                :class="{ active: isRoute('user.reading.water') || isRoute('user.reading.electricity') }">
            <i class="fas fa-tint"></i>
            <span>Readings</span>
          </Link>
          <Link :href="route('user.account')" class="ua-nav-btn" :class="{ active: isRoute('user.account') }">
            <i class="fas fa-receipt"></i>
            <span>Account</span>
          </Link>
        </template>
      </nav>

    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

defineProps({
  title:    { type: String,  default: '' },
  showBack: { type: Boolean, default: false },
})

const page      = usePage()
const authUser  = computed(() => page.props.auth?.user ?? null)
const navPages  = computed(() => page.props.nav_pages ?? [])
const drawerOpen = ref(false)

const isRoute = (name) => page.props.ziggy?.current === name

const goBack = () => window.history.back()
</script>

<style scoped>
/* ── Root: full-viewport grey background ── */
.ua-root {
  min-height: 100vh;
  background: var(--ua-bg, #F5F5F5);
  display: flex;
  justify-content: center;
  align-items: flex-start;
  font-family: 'Nunito', sans-serif;
}

/* ── Phone container ── */
.ua-phone {
  width: 100%;
  max-width: 414px;
  min-height: 100vh;
  background: var(--ua-card, #fff);
  display: flex;
  flex-direction: column;
  position: relative;
  box-shadow: 0 0 40px rgba(0,0,0,0.12);
}

/* ── Header ── */
.ua-header {
  background: var(--ua-primary, #009BA4);
  color: #fff;
  height: 56px;
  display: flex;
  align-items: center;
  padding: 0 16px;
  flex-shrink: 0;
  gap: 12px;
  position: sticky;
  top: 0;
  z-index: 10;
}

.ua-header-title {
  font-size: 1rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.ua-header-logo {
  font-style: italic;
  font-size: 1.4rem;
}

.ua-logo-my     { font-weight: 300; color: #fff; }
.ua-logo-cities { font-weight: 700; color: #fff; }

.ua-back-btn {
  background: none;
  border: none;
  color: #fff;
  font-size: 1rem;
  cursor: pointer;
  padding: 4px 8px 4px 0;
  flex-shrink: 0;
}

/* Hamburger button */
.ua-hamburger {
  background: none;
  border: none;
  color: #fff;
  font-size: 1.15rem;
  cursor: pointer;
  padding: 4px 8px 4px 0;
  flex-shrink: 0;
  line-height: 1;
}

/* Push auth button to far right */
.ua-header-auth {
  margin-left: auto;
  flex-shrink: 0;
}

.ua-auth-btn {
  display: flex;
  align-items: center;
  gap: 5px;
  color: rgba(255,255,255,0.92);
  text-decoration: none;
  font-size: 0.78rem;
  font-weight: 600;
  font-family: 'Nunito', sans-serif;
  border: 1.5px solid rgba(255,255,255,0.55);
  border-radius: 20px;
  padding: 4px 12px;
  transition: background 0.15s;
  white-space: nowrap;
}

.ua-auth-btn:hover {
  background: rgba(255,255,255,0.18);
  text-decoration: none;
  color: #fff;
}

/* ── Main scrollable content ── */
.ua-content {
  flex: 1;
  overflow-y: auto;
  background: var(--ua-bg, #F5F5F5);
  padding-bottom: 64px;
}

/* ── Bottom navigation ── */
.ua-bottom-nav {
  position: fixed;
  bottom: 0;
  width: 100%;
  max-width: 414px;
  height: 56px;
  background: var(--ua-card, #fff);
  border-top: 1px solid var(--ua-divider, #E0E0E0);
  display: flex;
  z-index: 100;
}

.ua-nav-btn {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: var(--ua-text-secondary, #757575);
  text-decoration: none;
  font-size: 0.62rem;
  font-family: 'Nunito', sans-serif;
  gap: 3px;
  transition: color 0.15s;
}

.ua-nav-btn i         { font-size: 1.1rem; }
.ua-nav-btn.active    { color: var(--ua-primary, #009BA4); font-weight: 600; }
.ua-nav-btn:hover     { color: var(--ua-primary, #009BA4); text-decoration: none; }

/* ── Slide-out drawer ── */
.ua-drawer-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,0.45);
  z-index: 200;
  display: flex;
}

.ua-drawer {
  width: 80%;
  max-width: 280px;
  height: 100%;
  background: #fff;
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  box-shadow: 4px 0 24px rgba(0,0,0,0.18);
}

.ua-drawer-header {
  background: var(--ua-primary, #009BA4);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px;
  height: 56px;
  flex-shrink: 0;
}

.ua-drawer-logo {
  font-style: italic;
  font-size: 1.3rem;
}

.ua-drawer-close {
  background: none;
  border: none;
  color: #fff;
  font-size: 1.1rem;
  cursor: pointer;
  padding: 4px;
}

.ua-drawer-label {
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: #9e9e9e;
  padding: 14px 16px 4px;
}

.ua-drawer-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  color: #333;
  text-decoration: none;
  font-size: 0.92rem;
  font-weight: 600;
  border-bottom: 1px solid #f0f0f0;
  transition: background 0.15s;
}

.ua-drawer-item:hover,
.ua-drawer-item:focus {
  background: #f5f7ff;
  color: var(--ua-primary, #009BA4);
  text-decoration: none;
}

.ua-drawer-icon {
  font-size: 0.9rem;
  width: 18px;
  text-align: center;
  color: var(--ua-primary, #009BA4);
  flex-shrink: 0;
}

.ua-drawer-chevron {
  margin-left: auto;
  font-size: 0.7rem;
  color: #bbb;
}

/* Child page row */
.ua-drawer-child {
  padding-left: 36px;
  font-weight: 500;
  background: #fafafa;
  color: #555;
  font-size: 0.86rem;
}

.ua-drawer-child:hover {
  background: #f0f4ff;
  color: var(--ua-primary, #009BA4);
}

.ua-drawer-child-bullet {
  font-size: 0.8rem;
  color: #bbb;
  flex-shrink: 0;
}

/* Drawer slide-in/out transition */
.drawer-enter-active,
.drawer-leave-active { transition: opacity 0.22s ease; }
.drawer-enter-from,
.drawer-leave-to    { opacity: 0; }
.drawer-enter-active .ua-drawer,
.drawer-leave-active .ua-drawer { transition: transform 0.22s ease; }
.drawer-enter-from .ua-drawer,
.drawer-leave-to .ua-drawer    { transform: translateX(-100%); }
</style>
