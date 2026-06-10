<script setup>
import { useForm, usePage } from '@inertiajs/vue3'

const page = usePage()
const company = page.props.company ?? {}

const form = useForm({
  license_key: '',
})

function submit() {
  form.post('/license/activate')
}
</script>

<template>
  <div class="min-h-screen bg-amber-50 flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-lg bg-white border border-amber-200 rounded-2xl p-8 shadow-lg">
      <h1 class="text-2xl font-bold text-amber-900">License Activation Required</h1>
      <p class="mt-2 text-sm text-amber-800">
        Workspace: <span class="font-semibold">{{ company.name }}</span>
      </p>
      <p class="mt-2 text-sm text-amber-700">
        Enter the license key sent by the system owner after payment.
      </p>

      <form class="mt-6 space-y-4" @submit.prevent="submit">
        <div>
          <label class="block text-sm font-medium text-amber-900 mb-1">License Key</label>
          <input
            v-model="form.license_key"
            type="text"
            required
            class="w-full rounded-lg border border-amber-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
            placeholder="XXXX-XXXX-XXXX-XXXX"
          />
          <p v-if="form.errors.license_key" class="mt-1 text-xs text-red-600">{{ form.errors.license_key }}</p>
        </div>

        <button
          type="submit"
          :disabled="form.processing"
          class="w-full rounded-lg bg-amber-600 hover:bg-amber-700 text-white py-2.5 text-sm font-medium disabled:opacity-60"
        >
          {{ form.processing ? 'Activating...' : 'Activate License' }}
        </button>
      </form>
    </div>
  </div>
</template>
