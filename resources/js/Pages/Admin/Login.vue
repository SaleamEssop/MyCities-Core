<template>
  <div class="login-container">
    <div class="login-card">
      <div class="login-header">
        <img src="/img/my_cities.png" alt="MyCities Logo" @error="hideLogo = true" v-if="!hideLogo">
        <h1>Administrator Portal</h1>
        <p>Sign in to manage MyCities</p>
        <p class="system-badge">MyCities-Core · Laravel + Inertia</p>
      </div>

      <div class="login-body">
        <div v-if="$page.props.flash?.message" :class="['alert', $page.props.flash.class || 'alert-info']">
          {{ $page.props.flash.message }}
        </div>

        <form @submit.prevent="submit">
          <div class="form-group">
            <label class="form-label" for="email">
              <i class="fas fa-envelope" style="margin-right: 8px; color: #6b7280;"></i>
              Email Address
            </label>
            <input 
              type="email" 
              v-model="form.email" 
              id="email" 
              class="form-input" 
              placeholder="Enter your email address"
              required 
              autocomplete="email"
              :class="{ 'is-invalid': form.errors.email }"
            >
            <div v-if="form.errors.email" class="error-text">{{ form.errors.email }}</div>
          </div>

          <div class="form-group">
            <label class="form-label" for="password">
              <i class="fas fa-lock" style="margin-right: 8px; color: #6b7280;"></i>
              Password
            </label>
            <div class="input-wrapper">
              <input 
                :type="showPassword ? 'text' : 'password'" 
                v-model="form.password" 
                id="password" 
                class="form-input has-toggle" 
                placeholder="Enter your password"
                required 
                autocomplete="current-password"
                :class="{ 'is-invalid': form.errors.password }"
              >
              <button type="button" class="password-toggle" @click="showPassword = !showPassword">
                <i :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
              </button>
            </div>
            <div v-if="form.errors.password" class="error-text">{{ form.errors.password }}</div>
          </div>

          <div class="form-group">
            <div class="checkbox-wrapper">
              <input type="checkbox" v-model="form.remember" id="remember">
              <label for="remember">Remember me on this device</label>
            </div>
          </div>

          <button type="submit" class="btn-login" :disabled="form.processing">
            <i class="fas fa-sign-in-alt" style="margin-right: 10px;"></i>
            Sign In
          </button>
        </form>

        <div class="divider">
          <span>or</span>
        </div>

        <Link :href="route('admin.forgot-password')" class="forgot-link">
          <i class="fas fa-key" style="margin-right: 8px;"></i>
          Forgot your password?
        </Link>
      </div>

      <div class="login-footer">
        <p>&copy; {{ new Date().getFullYear() }} MyCities-Core. All rights reserved.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useForm, usePage, Link } from '@inertiajs/vue3'
import { route } from 'ziggy-js'

const page = usePage()
const hideLogo = ref(false)
const showPassword = ref(false)

const form = useForm({
  email: '',
  password: '',
  remember: false,
})

const submit = () => {
  form.post(route('admin.login'), {
    onFinish: () => {
      form.reset('password')
    },
  })
}
</script>

<style scoped>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

.login-container {
  width: 100%;
  max-width: 480px;
  margin: 0 auto;
}

.login-card {
  background: #ffffff;
  border-radius: 16px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
  overflow: hidden;
}

.login-header {
  background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
  padding: 40px 40px 30px;
  text-align: center;
}

.login-header img {
  max-width: 180px;
  height: auto;
  margin-bottom: 15px;
}

.login-header h1 {
  color: #ffffff;
  font-size: 24px;
  font-weight: 600;
  margin: 0;
}

.login-header p {
  color: rgba(255, 255, 255, 0.7);
  font-size: 14px;
  margin-top: 8px;
}

.system-badge {
  margin-top: 10px !important;
  font-size: 12px !important;
  color: rgba(255,255,255,0.85) !important;
  background: rgba(0,0,0,0.2);
  padding: 6px 12px;
  border-radius: 8px;
  display: inline-block !important;
}

.login-body {
  padding: 40px;
}

.alert {
  padding: 14px 18px;
  border-radius: 10px;
  margin-bottom: 25px;
  font-size: 14px;
  font-weight: 500;
}

.alert-danger {
  background-color: #fef2f2;
  border: 1px solid #fecaca;
  color: #dc2626;
}

.alert-success {
  background-color: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #16a34a;
}

.alert-info {
  background-color: #eff6ff;
  border: 1px solid #bfdbfe;
  color: #2563eb;
}

.form-group {
  margin-bottom: 24px;
}

.form-label {
  display: block;
  font-size: 15px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 10px;
}

.input-wrapper {
  position: relative;
}

.form-input {
  width: 100%;
  padding: 18px 20px;
  font-size: 17px;
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  background: #f9fafb;
  color: #1f2937;
  transition: all 0.2s ease;
  outline: none;
}

.form-input:focus {
  border-color: #2980b9;
  background: #ffffff;
  box-shadow: 0 0 0 4px rgba(41, 128, 185, 0.1);
}

.form-input.is-invalid {
  border-color: #dc2626;
}

.form-input::placeholder {
  color: #9ca3af;
  font-size: 16px;
}

.password-toggle {
  position: absolute;
  right: 18px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  color: #6b7280;
  cursor: pointer;
  padding: 5px;
  font-size: 18px;
  transition: color 0.2s;
}

.password-toggle:hover {
  color: #2980b9;
}

.form-input.has-toggle {
  padding-right: 55px;
}

.checkbox-wrapper {
  display: flex;
  align-items: center;
  gap: 10px;
}

.checkbox-wrapper input[type="checkbox"] {
  width: 20px;
  height: 20px;
  accent-color: #2980b9;
  cursor: pointer;
}

.checkbox-wrapper label {
  font-size: 15px;
  color: #4b5563;
  cursor: pointer;
}

.btn-login {
  width: 100%;
  padding: 18px;
  font-size: 17px;
  font-weight: 600;
  color: #ffffff;
  background: linear-gradient(135deg, #2980b9 0%, #1c5a85 100%);
  border: none;
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.3s ease;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.btn-login:hover:not(:disabled) {
  background: linear-gradient(135deg, #1c5a85 0%, #164466 100%);
  transform: translateY(-2px);
  box-shadow: 0 10px 20px -5px rgba(41, 128, 185, 0.4);
}

.btn-login:active:not(:disabled) {
  transform: translateY(0);
}

.btn-login:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.error-text {
  color: #dc2626;
  font-size: 13px;
  margin-top: 6px;
}

.divider {
  display: flex;
  align-items: center;
  margin: 28px 0;
}

.divider::before,
.divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: #e5e7eb;
}

.divider span {
  padding: 0 15px;
  color: #9ca3af;
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.forgot-link {
  display: block;
  text-align: center;
  color: #2980b9;
  font-size: 15px;
  font-weight: 500;
  text-decoration: none;
  transition: color 0.2s;
}

.forgot-link:hover {
  color: #1c5a85;
  text-decoration: underline;
}

.login-footer {
  text-align: center;
  padding: 20px 40px 30px;
  background: #f9fafb;
  border-top: 1px solid #e5e7eb;
}

.login-footer p {
  color: #6b7280;
  font-size: 13px;
}

@media (max-width: 480px) {
  .login-header {
    padding: 30px 25px 25px;
  }
  
  .login-body {
    padding: 30px 25px;
  }
  
  .form-input {
    padding: 16px 18px;
    font-size: 16px;
  }
  
  .btn-login {
    padding: 16px;
    font-size: 16px;
  }
}
</style>