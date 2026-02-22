<template>
  <div class="login-container">
    <div class="login-card">
      <div class="login-header">
        <h2><i class="fas fa-city"></i> MyCities-Core</h2>
        <p>Reset Password</p>
      </div>
      <form @submit.prevent="submitForm">
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" v-model="form.email" class="form-control" required placeholder="Enter your email">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
      </form>
      <div class="login-footer">
        <Link :href="route('login')">Back to Login</Link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'

const form = ref({ email: '' })

const submitForm = async () => {
  try {
    const res = await fetch('/admin/forgot-password', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
      body: JSON.stringify(form.value)
    })
    const data = await res.json()
    alert(data.message || 'Reset link sent!')
  } catch (e) { console.error(e) }
}
</script>

<style scoped>
.login-container { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #2980b9 0%, #6dd5fa 100%); }
.login-card { background: #fff; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); padding: 40px; width: 100%; max-width: 400px; }
.login-header { text-align: center; margin-bottom: 30px; }
.login-header h2 { color: #2980b9; font-size: 28px; margin-bottom: 5px; }
.login-header p { color: #666; font-size: 14px; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
.form-control { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
.btn { width: 100%; padding: 12px; border: none; border-radius: 5px; font-size: 16px; font-weight: 600; cursor: pointer; }
.btn-primary { background: #2980b9; color: #fff; }
.btn-primary:hover { background: #1a5276; }
.login-footer { text-align: center; margin-top: 20px; }
.login-footer a { color: #2980b9; text-decoration: none; font-size: 14px; }
.login-footer a:hover { text-decoration: underline; }
</style>
