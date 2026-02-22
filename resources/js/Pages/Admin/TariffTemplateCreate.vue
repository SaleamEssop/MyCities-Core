<template>
  <AdminLayout>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
      <h1 class="h3 mb-0 text-gray-800">Add Tariff Template</h1>
      <Link :href="route('tariff-template')" class="btn btn-sm btn-secondary shadow-sm">
        <i class="fas fa-arrow-left fa-sm"></i> Back to List
      </Link>
    </div>

    <div v-if="$page.props.flash?.message"
         :class="['alert', $page.props.flash?.class || 'alert-info', 'alert-dismissible']"
         role="alert">
      {{ $page.props.flash.message }}
    </div>

    <TariffTemplateForm
      :regions="regions"
      :csrf-token="csrfToken"
      :submit-url="submitUrl"
      :cancel-url="cancelUrl"
      :get-email-url="getEmailUrl"
    />
  </AdminLayout>
</template>

<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Link } from '@inertiajs/vue3'
import TariffTemplateForm from '@/components/TariffTemplateForm.vue'

defineProps({
  regions:     { type: Array,  default: () => [] },
  csrfToken:   { type: String, default: '' },
  submitUrl:   { type: String, default: '' },
  cancelUrl:   { type: String, default: '' },
  getEmailUrl: { type: String, default: '' },
})
</script>
