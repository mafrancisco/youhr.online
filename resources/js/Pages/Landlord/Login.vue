<script setup>
import { router } from '@inertiajs/vue3'

function login() {
  router.visit('/landlord/auth/google/redirect')
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-[radial-gradient(circle_at_top,_#f8fafc,_#dbeafe_40%,_#0f172a_100%)] px-4">
    <div class="w-full max-w-md rounded-3xl bg-white/95 backdrop-blur border border-slate-200 shadow-2xl p-8">
      <p class="text-xs uppercase tracking-[0.35em] text-slate-500">Landlord Console</p>
      <h1 class="mt-3 text-3xl font-semibold text-slate-900">Company & License Admin</h1>
      <p class="mt-2 text-sm text-slate-600">
        Sign in with an authorized Google account to manage tenants and license keys.
      </p>

      <button
        type="button"
        @click="login"
        class="mt-8 w-full rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800"
      >
        Continue with Google
      </button>
    </div>
  </div>
</template>
