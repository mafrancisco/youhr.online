<script setup>
import { computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'

const page       = usePage()
const settings   = computed(() => page.props.settings ?? {})
const systemName = computed(() => settings.value.system_name || 'DTR System')
const logoUrl    = computed(() => settings.value.logo_url || '')

const form = useForm({ username: '', password: '' })

function submit() {
  form.post('/login')
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-900 to-blue-700 px-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-8">
      <div class="text-center mb-8">
        <img v-if="logoUrl" :src="logoUrl" class="h-16 w-16 mx-auto mb-3 object-contain" alt="logo" />
        <h1 class="text-2xl font-bold text-gray-900">{{ systemName }}</h1>
        <p class="text-sm text-gray-500 mt-1">Daily Time Record System</p>
      </div>

      <form @submit.prevent="submit" class="space-y-5">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
          <input v-model="form.username" type="text" required autofocus
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Enter username" />
          <p v-if="form.errors.username" class="mt-1 text-xs text-red-600">{{ form.errors.username }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
          <input v-model="form.password" type="password" required
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Enter password" />
        </div>

        <button type="submit" :disabled="form.processing"
          class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg text-sm transition-colors disabled:opacity-60">
          {{ form.processing ? 'Signing in...' : 'Sign In' }}
        </button>
      </form>
    </div>
  </div>
</template>
