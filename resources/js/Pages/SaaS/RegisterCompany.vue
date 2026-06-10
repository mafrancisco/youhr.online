<script setup>
import { useForm } from '@inertiajs/vue3'

const form = useForm({
  company_name:  '',
  company_email: '',
})

function submit() {
  form.post('/register-company')
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-sky-950 via-cyan-900 to-emerald-800 px-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-8">
      <div class="text-center mb-8">
        <img :src="'/images/yourhrlogo.png'" class="h-16 mx-auto mb-3 object-contain" alt="YourHR Online" />
        <h1 class="text-2xl font-bold text-gray-900">Register Your Organization</h1>
        <p class="text-sm text-gray-500 mt-1">Set up your company's DTR workspace</p>
      </div>

      <form @submit.prevent="submit" class="space-y-5">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
          <input v-model="form.company_name" type="text" required autofocus
            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="e.g. Acme Corporation" />
          <p v-if="form.errors.company_name" class="mt-1 text-xs text-red-600">{{ form.errors.company_name }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Company Email Address</label>
          <input v-model="form.company_email" type="email" required
            class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="admin@yourcompany.com" />
          <p class="text-xs text-gray-400 mt-1">This email will be used for Google authentication and license delivery.</p>
          <p v-if="form.errors.company_email" class="mt-1 text-xs text-red-600">{{ form.errors.company_email }}</p>
        </div>

        <button type="submit" :disabled="form.processing"
          class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 rounded-lg text-sm transition-colors disabled:opacity-60 flex items-center justify-center gap-2">
          <svg v-if="!form.processing" class="w-4 h-4" viewBox="0 0 24 24">
            <path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
            <path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
          </svg>
          {{ form.processing ? 'Connecting...' : 'Continue with Google' }}
        </button>

        <div class="text-center">
          <a href="/" class="text-sm text-slate-600 hover:text-slate-900">Already registered? Sign in</a>
        </div>
      </form>
    </div>
  </div>
</template>
