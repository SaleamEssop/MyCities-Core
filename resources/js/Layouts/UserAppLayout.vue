<template>
  <div class="ua-root">
    <div class="ua-phone">

      <!-- TOP BAR -->
      <header class="ua-header">
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
      </header>

      <!-- MAIN SCROLLABLE CONTENT -->
      <main class="ua-content">
        <slot />
      </main>

      <!-- BOTTOM NAV -->
      <nav class="ua-bottom-nav">
        <Link :href="route('user.splash')" class="ua-nav-btn" :class="{ active: isRoute('user.splash') }">
          <i class="fas fa-home"></i>
          <span>Home</span>
        </Link>
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
      </nav>

    </div>
  </div>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

defineProps({
  title:    { type: String,  default: '' },
  showBack: { type: Boolean, default: false },
})

const page = usePage()

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
</style>
