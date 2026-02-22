<template>
  <div class="splash-root">
    <div class="splash-phone">

      <!-- HEADER -->
      <div class="splash-header">
        <span class="splash-logo-my">My</span><span class="splash-logo-cities">Cities</span>
      </div>

      <!-- AD CONTENT -->
      <div class="splash-body">
        <template v-if="ad">
          <img v-if="ad.image" :src="ad.image" class="splash-image" alt="Advertisement">
          <h2 class="splash-title">{{ ad.title }}</h2>
          <div class="splash-content" v-html="ad.content"></div>
        </template>
        <template v-else>
          <div class="splash-default">
            <i class="fas fa-city splash-icon"></i>
            <h2 class="splash-title">Welcome to MyCities</h2>
            <p class="splash-sub">Your municipal services portal</p>
          </div>
        </template>
      </div>

      <!-- FOOTER: progress bar + skip -->
      <div class="splash-footer">
        <div class="splash-progress">
          <div class="splash-progress-bar" :style="{ width: progressPct + '%' }"></div>
        </div>
        <a :href="nextUrl" class="splash-skip">Skip →</a>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  ad:      { type: Object, default: null },
  nextUrl: { type: String, required: true },
})

const DURATION    = 3000
const progressPct = ref(0)
let raf = null

onMounted(() => {
  const startTime = Date.now()

  const animate = () => {
    const elapsed = Date.now() - startTime
    progressPct.value = Math.min((elapsed / DURATION) * 100, 100)
    if (elapsed < DURATION) {
      raf = requestAnimationFrame(animate)
    } else {
      window.location.href = props.nextUrl
    }
  }

  raf = requestAnimationFrame(animate)
})

onUnmounted(() => {
  if (raf) cancelAnimationFrame(raf)
})
</script>

<style scoped>
.splash-root {
  min-height: 100vh;
  background: var(--ua-primary, #009BA4);
  display: flex;
  justify-content: center;
  align-items: center;
  font-family: 'Nunito', sans-serif;
}

.splash-phone {
  width: 100%;
  max-width: 414px;
  min-height: 100vh;
  background: var(--ua-card, #fff);
  display: flex;
  flex-direction: column;
}

.splash-header {
  background: var(--ua-primary, #009BA4);
  height: 88px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-style: italic;
  font-size: 2.2rem;
}

.splash-logo-my     { font-weight: 300; color: #fff; }
.splash-logo-cities { font-weight: 700; color: #fff; }

.splash-body {
  flex: 1;
  padding: 40px 24px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
}

.splash-image {
  max-width: 100%;
  max-height: 260px;
  object-fit: contain;
  border-radius: var(--ua-radius, 8px);
  margin-bottom: 20px;
}

.splash-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--ua-primary, #009BA4);
  margin-bottom: 12px;
}

.splash-sub     { color: var(--ua-text-secondary, #757575); font-size: 1rem; }
.splash-content { color: var(--ua-text, #212121); line-height: 1.6; font-size: 0.95rem; }
.splash-default { text-align: center; }

.splash-icon {
  font-size: 4.5rem;
  color: var(--ua-primary, #009BA4);
  margin-bottom: 20px;
  display: block;
}

.splash-footer {
  padding: 16px 20px 24px;
}

.splash-progress {
  height: 4px;
  background: var(--ua-divider, #E0E0E0);
  border-radius: 2px;
  overflow: hidden;
  margin-bottom: 12px;
}

.splash-progress-bar {
  height: 100%;
  background: var(--ua-primary, #009BA4);
  transition: width 0.08s linear;
}

.splash-skip {
  display: block;
  text-align: right;
  color: var(--ua-primary, #009BA4);
  text-decoration: none;
  font-weight: 700;
  font-size: 0.9rem;
}

.splash-skip:hover { text-decoration: underline; }
</style>
