<template>
  <AdminLayout>
    <div class="d2d-page">

      <div class="page-header">
        <div class="page-header-left">
          <h1 class="page-title"><i class="fas fa-calendar-alt"></i> Date to Date Calculator</h1>
          <p class="page-sub">Billing periods are defined by consecutive meter readings: each period runs from one reading date to the next.</p>
        </div>
      </div>

      <div class="d2d-card">
        <div class="d2d-card-header">How Date-to-Date works</div>
        <div class="d2d-card-body">
          <ul class="d2d-list">
            <li><strong>Period = reading pair:</strong> One period is the span from reading date A to the next reading date B. Usage = reading at B − reading at A.</li>
            <li><strong>No fixed calendar:</strong> Unlike Period-to-Period (monthly/bill-day), there is no bill day; periods follow actual reading dates.</li>
            <li><strong>Where it’s used:</strong> Accounts with a tariff whose billing type is <em>Date-to-Date</em>. Bills are generated when a new reading is saved (each new reading closes the previous period).</li>
          </ul>
          <p class="d2d-note">To manage accounts and meters using Date-to-Date billing, use <strong>User Accounts Manager</strong> and open an account; the Period and Readings tab offers a <strong>Date to Date</strong> mode for sector-based (reading-to-reading) view.</p>
          <div class="d2d-actions">
            <a :href="route('user-accounts.manager')" class="btn btn-primary">
              <i class="fas fa-users-cog mr-2"></i> Open User Accounts Manager
            </a>
          </div>
        </div>
      </div>

      <div class="d2d-card">
        <div class="d2d-card-header">Quick reference</div>
        <div class="d2d-card-body">
          <p><strong>Period-to-Period</strong> (monthly): Use the main <a :href="route('calculator')">Calculator → Period to Period</a> for block-day periods and straddle reconciliation.</p>
          <p><strong>Date-to-Date</strong>: This page; full date-to-date flows (periods from consecutive readings) are in Account Manager for accounts linked to a Date-to-Date tariff.</p>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({
  today: { type: String, default: '' },
})
</script>

<style scoped>
.d2d-page { max-width: 720px; }
.page-header { margin-bottom: 1.5rem; }
.page-title { font-size: 1.5rem; font-weight: 700; color: #2d3748; margin: 0 0 0.25rem 0; }
.page-title i { margin-right: 0.5rem; color: #4a5568; }
.page-sub { color: #718096; font-size: 0.95rem; margin: 0; }
.d2d-card { background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 1.25rem; border: 1px solid #e2e8f0; }
.d2d-card-header { font-weight: 600; padding: 0.75rem 1rem; border-bottom: 1px solid #e2e8f0; color: #2d3748; }
.d2d-card-body { padding: 1rem; }
.d2d-list { margin: 0 0 1rem 0; padding-left: 1.25rem; line-height: 1.6; color: #4a5568; }
.d2d-list li { margin-bottom: 0.5rem; }
.d2d-note { color: #4a5568; margin: 0 0 1rem 0; line-height: 1.6; }
.d2d-actions { margin-top: 1rem; }
.d2d-actions .btn { display: inline-flex; align-items: center; }
.d2d-actions .mr-2 { margin-right: 0.5rem; }
.d2d-card-body p { margin: 0 0 0.5rem 0; line-height: 1.6; color: #4a5568; }
.d2d-card-body a { color: #3182ce; text-decoration: none; }
.d2d-card-body a:hover { text-decoration: underline; }
</style>
