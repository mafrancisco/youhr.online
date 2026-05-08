<script setup>
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import FlashMessage from '@/Components/FlashMessage.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const props = defineProps({
  settings: Object,
})

const form = useForm({
  system_name:                   props.settings.system_name                    ?? '',
  company_address:               props.settings.company_address                ?? '',
  authorized_signatory:          props.settings.authorized_signatory           ?? '',
  authorized_signatory_position: props.settings.authorized_signatory_position  ?? '',
  logo:                          null,
})

const logoPreview    = ref(props.settings.logo_url || '')
const hasExistingLogo = computed(() => !!props.settings.logo_url)
const removingLogo   = ref(false)

function onLogoChange(e) {
  const file = e.target.files[0]
  if (!file) return
  form.logo = file
  logoPreview.value = URL.createObjectURL(file)
}

function clearLogoSelection() {
  form.logo = null
  logoPreview.value = props.settings.logo_url || ''
}

function submit() {
  form.post('/admin/settings', {
    forceFormData: true,
    onSuccess: () => {
      if (form.logo) form.logo = null
    },
  })
}

function removeLogo() {
  if (!confirm('Remove the current logo?')) return
  removingLogo.value = true
  useForm({}).delete('/admin/settings/logo', {
    onFinish: () => { removingLogo.value = false },
  })
}
</script>

<template>
  <AppLayout title="System Settings">
    <FlashMessage />

    <div class="max-w-2xl mx-auto bg-white rounded-xl shadow p-8 space-y-8">
      <h2 class="text-lg font-semibold text-gray-800 border-b pb-3">System Settings</h2>

      <form @submit.prevent="submit" class="space-y-6" enctype="multipart/form-data">

        <!-- System Name -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">System Name</label>
          <input v-model="form.system_name" type="text" required
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
            placeholder="e.g. IronOne DTR" />
          <p v-if="form.errors.system_name" class="mt-1 text-xs text-red-600">{{ form.errors.system_name }}</p>
        </div>

        <!-- Company Address -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Company Address</label>
          <textarea v-model="form.company_address" rows="2"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
            placeholder="Full company address"></textarea>
        </div>

        <!-- Authorized Signatory -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Authorized Signatory</label>
            <input v-model="form.authorized_signatory" type="text"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
              placeholder="Full name" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Position / Title</label>
            <input v-model="form.authorized_signatory_position" type="text"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
              placeholder="e.g. Executive Director" />
          </div>
        </div>

        <!-- Logo -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Company Logo</label>

          <!-- Current / preview -->
          <div v-if="logoPreview" class="mb-3 flex items-center gap-4">
            <img :src="logoPreview" class="h-20 w-20 object-contain border rounded-lg p-1 bg-gray-50" alt="logo preview" />
            <div class="flex gap-2">
              <button v-if="form.logo" type="button" @click="clearLogoSelection"
                class="text-xs text-gray-500 hover:text-gray-700 underline">
                Cancel selection
              </button>
              <button v-if="hasExistingLogo && !form.logo" type="button" @click="removeLogo" :disabled="removingLogo"
                class="text-xs text-red-500 hover:text-red-700 underline disabled:opacity-50">
                Remove logo
              </button>
            </div>
          </div>

          <input type="file" accept="image/*" @change="onLogoChange"
            class="block text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-4 file:rounded-lg file:border-0
                   file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
          <p class="mt-1 text-xs text-gray-400">PNG, JPG or GIF — max 2 MB. Displayed in sidebar, login page, and PDF reports.</p>
          <p v-if="form.errors.logo" class="mt-1 text-xs text-red-600">{{ form.errors.logo }}</p>
        </div>

        <div class="pt-2">
          <PrimaryButton type="submit" :disabled="form.processing">
            {{ form.processing ? 'Saving…' : 'Save Settings' }}
          </PrimaryButton>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
