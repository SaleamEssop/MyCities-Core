<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="max-w-2xl mx-auto">
      <h1 class="text-2xl font-bold text-gray-800">Billing Calculator</h1>
      <p class="mt-1 text-gray-600">PD.md ↔ Calculator.php (Mirror). Integer math, SAST, Remainder Method.</p>
      <div v-if="message" class="mt-4 p-3 rounded bg-blue-50 text-blue-800">{{ message }}</div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  message: { type: String, default: '' },
});
</script>
