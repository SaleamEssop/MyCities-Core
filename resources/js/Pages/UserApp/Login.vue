<template>
  <div class="login-root">
    <div class="login-phone">

      <!-- HEADER -->
      <div class="login-header">
        <div class="login-logo">
          <span class="logo-my">My</span><span class="logo-cities">Cities</span>
        </div>
        <p class="login-tagline">Municipal Services Portal</p>
      </div>

      <!-- FORM BODY -->
      <div class="login-body">
        <div v-if="$page.props.flash?.message" class="ua-alert">
          {{ $page.props.flash.message }}
        </div>
        <div v-if="form.errors.email" class="ua-alert ua-alert--error">
          {{ form.errors.email }}
        </div>

        <form @submit.prevent="submit">
          <div class="ua-field">
            <label class="ua-label" for="email">Email Address</label>
            <input
              id="email"
              type="email"
              v-model="form.email"
              class="ua-input"
              placeholder="you@example.com"
              autocomplete="email"
              required
            >
          </div>

          <div class="ua-field">
            <label class="ua-label" for="password">Password</label>
            <div class="ua-input-wrap">
              <input
                id="password"
                :type="showPwd ? 'text' : 'password'"
                v-model="form.password"
                class="ua-input ua-input--padded"
                placeholder="Your password"
                autocomplete="current-password"
                required
              >
              <button type="button" class="ua-pwd-toggle" @click="showPwd = !showPwd">
                <i :class="showPwd ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
              </button>
            </div>
          </div>

          <div class="ua-field ua-field--row">
            <input type="checkbox" v-model="form.remember" id="remember">
            <label for="remember" class="ua-label-inline">Keep me logged in</label>
          </div>

          <button type="submit" class="ua-btn-primary" :disabled="form.processing">
            <i class="fas fa-sign-in-alt"></i>
            <span>{{ form.processing ? 'Signing in…' : 'Sign In' }}</span>
          </button>
        </form>
      </div>

      <!-- FOOTER -->
      <div class="login-footer">
        <p>By signing in you agree to the MyCities terms of service.</p>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const showPwd = ref(false)

const form = useForm({
  email:    '',
  password: '',
  remember: false,
})

const submit = () => {
  form.post(route('user.login.submit'), {
    onFinish: () => form.reset('password'),
  })
}
</script>

<style scoped>
.login-root {
  min-height: 100vh;
  background: var(--ua-primary, #009BA4);
  display: flex;
  justify-content: center;
  align-items: center;
  font-family: 'Nunito', sans-serif;
}

.login-phone {
  width: 100%;
  max-width: 414px;
  min-height: 100vh;
  background: var(--ua-card, #fff);
  display: flex;
  flex-direction: column;
}

.login-header {
  background: var(--ua-primary, #009BA4);
  padding: 52px 24px 36px;
  text-align: center;
}

.login-logo {
  font-style: italic;
  font-size: 2.6rem;
  margin-bottom: 8px;
}

.logo-my     { font-weight: 300; color: #fff; }
.logo-cities { font-weight: 700; color: #fff; }

.login-tagline {
  color: rgba(255,255,255,0.85);
  font-size: 0.9rem;
  margin: 0;
}

.login-body {
  flex: 1;
  padding: 32px 24px;
}

.ua-alert {
  background: #E3F2FD;
  color: #1565C0;
  border-radius: var(--ua-radius-sm, 4px);
  padding: 10px 14px;
  margin-bottom: 16px;
  font-size: 0.9rem;
}

.ua-alert--error {
  background: #FFEBEE;
  color: #C62828;
}

.ua-field {
  margin-bottom: 20px;
}

.ua-field--row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 24px;
}

.ua-label {
  display: block;
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--ua-text-secondary, #757575);
  margin-bottom: 6px;
}

.ua-label-inline {
  font-size: 0.85rem;
  color: var(--ua-text-secondary, #757575);
  cursor: pointer;
}

.ua-input {
  width: 100%;
  padding: 12px 14px;
  border: 1.5px solid var(--ua-divider, #E0E0E0);
  border-radius: var(--ua-radius-sm, 4px);
  font-size: 1rem;
  font-family: 'Nunito', sans-serif;
  outline: none;
  transition: border-color 0.15s;
  box-sizing: border-box;
  color: var(--ua-text, #212121);
}

.ua-input:focus           { border-color: var(--ua-primary, #009BA4); }
.ua-input--padded         { padding-right: 46px; }

.ua-input-wrap {
  position: relative;
}

.ua-pwd-toggle {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  color: var(--ua-text-secondary, #757575);
  font-size: 1rem;
  padding: 4px;
}

.ua-btn-primary {
  width: 100%;
  padding: 14px;
  background: var(--ua-primary, #009BA4);
  color: #fff;
  border: none;
  border-radius: var(--ua-radius-sm, 4px);
  font-size: 1rem;
  font-weight: 700;
  font-family: 'Nunito', sans-serif;
  cursor: pointer;
  transition: background 0.15s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}

.ua-btn-primary:hover    { background: var(--ua-primary-dark, #007A82); }
.ua-btn-primary:disabled { opacity: 0.65; cursor: not-allowed; }

.login-footer {
  padding: 16px 24px;
  text-align: center;
  font-size: 0.72rem;
  color: var(--ua-text-secondary, #757575);
  border-top: 1px solid var(--ua-divider, #E0E0E0);
}
</style>
