<template>
  <UserAppLayout title="ACCOUNT" :showBack="true">
    <div class="acct-page">

      <!-- ACCOUNT HEADER -->
      <div class="acct-header">
        <div class="acct-name">{{ account.name_on_bill }}</div>
        <div class="acct-number">Account #{{ account.account_number }}</div>
      </div>

      <!-- EMPTY STATE -->
      <div v-if="bills.length === 0" class="acct-empty">
        <i class="fas fa-file-invoice-dollar"></i>
        <p>No billing history yet.</p>
      </div>

      <!-- BILLING PERIOD CARDS -->
      <div
        v-for="bill in bills"
        :key="bill.id"
        class="acct-bill-card"
        :class="{ 'acct-bill-card--current': bill.is_current }"
      >
        <div class="acct-bill-period">
          {{ bill.period_start }} – {{ bill.period_end }}
        </div>
        <div class="acct-bill-row">
          <span class="acct-bill-label">Charges</span>
          <span class="acct-bill-amount">R{{ bill.bill_total }}</span>
        </div>
        <div class="acct-bill-status">
          <span class="acct-badge" :class="statusClass(bill.status)">{{ bill.status }}</span>
        </div>
      </div>

    </div>
  </UserAppLayout>
</template>

<script setup>
import UserAppLayout from '@/Layouts/UserAppLayout.vue'

defineProps({
  account: { type: Object, required: true },
  bills:   { type: Array,  default: () => [] },
})

const statusClass = (status) => ({
  'acct-badge--paid':        status === 'paid',
  'acct-badge--calculated':  status === 'calculated',
  'acct-badge--open':        status === 'open',
  'acct-badge--provisional': status === 'provisional',
})
</script>

<style scoped>
.acct-page {
  background: var(--ua-bg, #F5F5F5);
  min-height: 100%;
}

.acct-header {
  background: var(--ua-primary, #009BA4);
  color: #fff;
  padding: 22px 20px 18px;
  text-align: center;
}

.acct-name   { font-size: 1.15rem; font-weight: 700; }
.acct-number { font-size: 0.8rem; opacity: 0.85; margin-top: 4px; }

.acct-empty {
  padding: 56px 24px;
  text-align: center;
  color: var(--ua-text-secondary, #757575);
}

.acct-empty i {
  font-size: 2.8rem;
  margin-bottom: 14px;
  display: block;
  color: var(--ua-grey, #9E9E9E);
}

.acct-bill-card {
  background: var(--ua-card, #fff);
  margin: 8px 10px;
  border-radius: var(--ua-radius, 8px);
  padding: 14px 16px;
  box-shadow: var(--ua-shadow, 0 2px 4px rgba(0,0,0,0.1));
}

.acct-bill-card--current {
  border-left: 4px solid var(--ua-primary, #009BA4);
}

.acct-bill-period {
  font-size: 0.76rem;
  color: var(--ua-text-secondary, #757575);
  margin-bottom: 8px;
}

.acct-bill-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.acct-bill-label  { font-size: 0.9rem; color: var(--ua-text, #212121); }
.acct-bill-amount { font-size: 1.15rem; font-weight: 700; color: var(--ua-amount, #1565C0); }

.acct-bill-status { display: flex; justify-content: flex-end; }

.acct-badge {
  font-size: 0.62rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 10px;
  text-transform: capitalize;
}

.acct-badge--paid        { background: #E8F5E9; color: #2E7D32; }
.acct-badge--calculated  { background: #E3F2FD; color: #1565C0; }
.acct-badge--open        { background: #F5F5F5; color: #757575; }
.acct-badge--provisional { background: #FFF8E1; color: #E65100; }
</style>
