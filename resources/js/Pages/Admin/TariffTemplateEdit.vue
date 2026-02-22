<template>
  <AdminLayout>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
      <h1 class="h3 mb-0 text-gray-800">Edit Tariff Template</h1>
      <div>
        <!-- Make a Copy: inline form with confirm dialog (no modal) -->
        <form method="POST" :action="copyUrl" style="display:inline" @submit.prevent="submitCopy">
          <input type="hidden" name="_token" :value="csrfToken" />
          <input type="hidden" name="id" :value="existingData?.id" />
          <input type="hidden" name="is_date_child" :value="copyAsChild ? '1' : '0'" />
          <div class="d-inline-flex align-items-center mr-2">
            <div class="custom-control custom-checkbox mr-2">
              <input type="checkbox" id="copyAsChild" v-model="copyAsChild"
                     class="custom-control-input">
              <label class="custom-control-label small" for="copyAsChild">Date Child</label>
            </div>
            <button type="submit" class="btn btn-sm btn-success shadow-sm">
              <i class="fas fa-copy fa-sm"></i> Make a Copy
            </button>
          </div>
        </form>
        <Link :href="route('tariff-template')" class="btn btn-sm btn-secondary shadow-sm">
          <i class="fas fa-arrow-left fa-sm"></i> Back to List
        </Link>
      </div>
    </div>

    <!-- Hierarchy notice -->
    <div v-if="existingData?.parent_id" class="alert alert-info mb-3">
      <strong><i class="fas fa-sitemap"></i> Date Child Tariff</strong> — this template is a child of another tariff.
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
      :existing-data="existingData"
    />
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Link } from '@inertiajs/vue3'
import TariffTemplateForm from '@/components/TariffTemplateForm.vue'

const props = defineProps({
  regions:      { type: Array,  default: () => [] },
  csrfToken:    { type: String, default: '' },
  submitUrl:    { type: String, default: '' },
  cancelUrl:    { type: String, default: '' },
  copyUrl:      { type: String, default: '' },
  getEmailUrl:  { type: String, default: '' },
  existingData: { type: Object, default: null },
})

const copyAsChild = ref(false)

function submitCopy() {
  const label = copyAsChild.value ? 'date child (linked to this parent)' : 'independent copy'
  if (!confirm(`Create an ${label} of this tariff template?`)) return
  // Build and submit a real form
  const form = document.createElement('form')
  form.method = 'POST'
  form.action = props.copyUrl
  const fields = {
    _token:        props.csrfToken,
    id:            props.existingData?.id,
    is_date_child: copyAsChild.value ? '1' : '0',
  }
  for (const [name, value] of Object.entries(fields)) {
    const input = document.createElement('input')
    input.type = 'hidden'
    input.name = name
    input.value = value
    form.appendChild(input)
  }
  document.body.appendChild(form)
  form.submit()
}
</script>
