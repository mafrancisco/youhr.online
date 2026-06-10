<script setup>
import { useForm } from '@inertiajs/vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const props = defineProps({
  admin: Object,
  companies: Array,
  licenses: Array,
  auditLogs: Array,
})

const statusForms = {}
const licenseForms = {}

function companyForm(id, status) {
  if (!statusForms[id]) {
    statusForms[id] = useForm({ status })
  }
  return statusForms[id]
}

function licenseForm(id) {
  if (!licenseForms[id]) {
    licenseForms[id] = useForm({ bound_email: '', expires_at: '' })
  }
  return licenseForms[id]
}

function updateCompany(company) {
  const form = companyForm(company.id, company.status)
  form.post(`/landlord/companies/${company.id}/status`, { preserveScroll: true })
}

function generateLicense(company) {
  const form = licenseForm(company.id)
  form.post(`/landlord/companies/${company.id}/licenses`, { preserveScroll: true })
}

function activateLicense(license) {
  useForm({}).post(`/landlord/licenses/${license.id}/activate`, { preserveScroll: true })
}

function suspendLicense(license) {
  useForm({}).post(`/landlord/licenses/${license.id}/suspend`, { preserveScroll: true })
}

function logout() {
  useForm({}).post('/landlord/logout')
}
</script>

<template>
  <div class="min-h-screen bg-slate-950 text-slate-100">
    <FlashMessage />

    <div class="mx-auto max-w-7xl px-6 py-8 space-y-8">
      <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-2xl">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <p class="text-xs uppercase tracking-[0.35em] text-cyan-300">Landlord Console</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Companies and licenses</h1>
            <p class="mt-1 text-sm text-slate-300">Signed in as {{ admin.name }} · {{ admin.email }}</p>
          </div>
          <PrimaryButton variant="ghost" @click="logout">Sign Out</PrimaryButton>
        </div>
      </section>

      <section class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-3xl bg-white p-6 text-slate-900 shadow-xl">
          <h2 class="text-lg font-semibold">Companies</h2>
          <div class="mt-5 space-y-4">
            <article v-for="company in companies" :key="company.id" class="rounded-2xl border border-slate-200 p-4">
              <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div>
                  <h3 class="font-semibold text-slate-900">{{ company.name }}</h3>
                  <p class="text-sm text-slate-500">{{ company.slug }} · {{ company.database }}</p>
                  <p class="text-xs text-slate-500 mt-1">Owner: {{ company.owner_google_email }}</p>
                  <p class="text-xs mt-2" :class="company.licensed ? 'text-emerald-600' : 'text-amber-600'">
                    {{ company.licensed ? 'Licensed' : 'Unlicensed' }}
                  </p>
                </div>
                <div class="flex flex-col gap-2 min-w-48">
                  <select v-model="company.status" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    <option value="active">active</option>
                    <option value="inactive">inactive</option>
                  </select>
                  <button class="rounded-xl bg-slate-900 px-3 py-2 text-sm text-white hover:bg-slate-800" @click="updateCompany(company)">
                    Update Status
                  </button>
                </div>
              </div>

              <div class="mt-4 flex flex-col gap-2 md:flex-row">
                <input v-model="licenseForm(company.id).bound_email" type="email" placeholder="Bind email (optional)" class="flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                <input v-model="licenseForm(company.id).expires_at" type="date" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                <button class="rounded-xl bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700" @click="generateLicense(company)">
                  Generate Key
                </button>
              </div>
            </article>
          </div>
        </div>

        <div class="rounded-3xl bg-white p-6 text-slate-900 shadow-xl">
          <h2 class="text-lg font-semibold">Recent Licenses</h2>
          <div class="mt-5 space-y-4">
            <article v-for="license in licenses" :key="license.id" class="rounded-2xl border border-slate-200 p-4">
              <div class="flex items-start justify-between gap-4">
                <div>
                  <h3 class="font-semibold text-slate-900">{{ license.company }}</h3>
                  <p class="text-sm text-slate-500">{{ license.slug }}</p>
                  <p class="text-xs text-slate-500 mt-1">Bound: {{ license.bound_email || 'none' }}</p>
                  <p class="text-xs text-slate-500">Activated: {{ license.activated_at || 'pending' }}</p>
                  <p class="text-xs text-slate-500">Expires: {{ license.expires_at || 'none' }}</p>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="license.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                  {{ license.status }}
                </span>
              </div>

              <div class="mt-4 flex gap-2">
                <button v-if="license.status !== 'active'" class="rounded-xl bg-emerald-600 px-3 py-2 text-sm text-white hover:bg-emerald-700" @click="activateLicense(license)">
                  Activate
                </button>
                <button v-if="license.status === 'active'" class="rounded-xl bg-rose-600 px-3 py-2 text-sm text-white hover:bg-rose-700" @click="suspendLicense(license)">
                  Suspend
                </button>
              </div>
            </article>
          </div>
        </div>
      </section>

      <section class="rounded-3xl bg-white p-6 text-slate-900 shadow-xl">
        <h2 class="text-lg font-semibold">Audit Trail</h2>
        <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200">
          <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-slate-500">
              <tr>
                <th class="px-4 py-3 text-left font-medium">When</th>
                <th class="px-4 py-3 text-left font-medium">Company</th>
                <th class="px-4 py-3 text-left font-medium">Action</th>
                <th class="px-4 py-3 text-left font-medium">Actor</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
              <tr v-for="log in auditLogs" :key="log.id">
                <td class="px-4 py-3 text-slate-500">{{ log.created_at }}</td>
                <td class="px-4 py-3 font-medium text-slate-900">{{ log.company || 'N/A' }}</td>
                <td class="px-4 py-3 text-slate-700">{{ log.action }}</td>
                <td class="px-4 py-3 text-slate-500">{{ log.actor_email || 'system' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </div>
</template>
