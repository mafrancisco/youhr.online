<script setup>
import { ref } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import FlashMessage from '@/Components/FlashMessage.vue'

const props = defineProps({
  admin: Object,
  companies: Array,
  licenses: Array,
  auditLogs: Array,
})

const activeTab = ref('companies')

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

// Expandable row for license generation
const expandedCompany = ref(null)
function toggleExpand(id) {
  expandedCompany.value = expandedCompany.value === id ? null : id
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 text-slate-100">
    <FlashMessage />

    <!-- Top Bar -->
    <header class="sticky top-0 z-50 border-b border-white/5 bg-slate-950/80 backdrop-blur-xl">
      <div class="mx-auto max-w-7xl flex items-center justify-between px-6 py-4">
        <div class="flex items-center gap-4">
          <div class="h-9 w-9 rounded-xl bg-gradient-to-br from-cyan-400 to-blue-600 flex items-center justify-center shadow-lg shadow-cyan-500/20">
            <span class="text-white text-sm font-bold">L</span>
          </div>
          <div>
            <h1 class="text-lg font-semibold text-white tracking-tight">Landlord Console</h1>
            <p class="text-xs text-slate-400">{{ admin.name }} · {{ admin.email }}</p>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <a href="/landlord/database/download-all" class="rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 px-4 py-2 text-sm font-medium text-white shadow-lg shadow-indigo-500/20 hover:shadow-indigo-500/40 transition-all">
            Backup All Databases
          </a>
          <button @click="logout" class="rounded-xl border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800 hover:text-white hover:border-slate-600 transition-all">
            Sign Out
          </button>
        </div>
      </div>
    </header>

    <div class="mx-auto max-w-7xl px-6 py-8 space-y-8">

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="rounded-2xl border border-white/5 bg-white/[0.03] backdrop-blur p-5">
          <p class="text-xs uppercase tracking-widest text-slate-500">Total Companies</p>
          <p class="mt-2 text-3xl font-bold text-white">{{ companies.length }}</p>
        </div>
        <div class="rounded-2xl border border-white/5 bg-white/[0.03] backdrop-blur p-5">
          <p class="text-xs uppercase tracking-widest text-slate-500">Active Licenses</p>
          <p class="mt-2 text-3xl font-bold text-emerald-400">{{ licenses.filter(l => l.status === 'active').length }}</p>
        </div>
        <div class="rounded-2xl border border-white/5 bg-white/[0.03] backdrop-blur p-5">
          <p class="text-xs uppercase tracking-widest text-slate-500">Pending Licenses</p>
          <p class="mt-2 text-3xl font-bold text-amber-400">{{ licenses.filter(l => l.status === 'pending').length }}</p>
        </div>
      </div>

      <!-- Tabs -->
      <div class="flex gap-1 rounded-xl bg-white/[0.03] border border-white/5 p-1 w-fit">
        <button
          v-for="tab in ['companies', 'licenses', 'audit']"
          :key="tab"
          @click="activeTab = tab"
          class="rounded-lg px-5 py-2.5 text-sm font-medium transition-all capitalize"
          :class="activeTab === tab ? 'bg-white text-slate-900 shadow-lg' : 'text-slate-400 hover:text-white'"
        >
          {{ tab === 'audit' ? 'Audit Trail' : tab }}
        </button>
      </div>

      <!-- Companies Table -->
      <section v-show="activeTab === 'companies'" class="rounded-2xl border border-white/5 bg-white/[0.02] backdrop-blur overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-white/5 bg-white/[0.02]">
                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Company</th>
                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Database</th>
                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Owner</th>
                <th class="px-5 py-4 text-center text-xs font-semibold uppercase tracking-wider text-slate-400">License</th>
                <th class="px-5 py-4 text-center text-xs font-semibold uppercase tracking-wider text-slate-400">Status</th>
                <th class="px-5 py-4 text-right text-xs font-semibold uppercase tracking-wider text-slate-400">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
              <template v-for="company in companies" :key="company.id">
                <tr class="hover:bg-white/[0.02] transition-colors">
                  <td class="px-5 py-4">
                    <p class="font-semibold text-white">{{ company.name }}</p>
                    <p class="text-xs text-slate-500 mt-0.5">{{ company.slug }}</p>
                  </td>
                  <td class="px-5 py-4">
                    <span class="font-mono text-xs text-slate-400 bg-white/5 rounded-md px-2 py-1">{{ company.database }}</span>
                  </td>
                  <td class="px-5 py-4">
                    <p class="text-slate-300 text-xs">{{ company.owner_google_email }}</p>
                  </td>
                  <td class="px-5 py-4 text-center">
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                      :class="company.licensed ? 'bg-emerald-500/10 text-emerald-400 ring-1 ring-emerald-500/20' : 'bg-amber-500/10 text-amber-400 ring-1 ring-amber-500/20'">
                      <span class="mr-1.5 h-1.5 w-1.5 rounded-full" :class="company.licensed ? 'bg-emerald-400' : 'bg-amber-400'"></span>
                      {{ company.licensed ? 'Licensed' : 'Unlicensed' }}
                    </span>
                  </td>
                  <td class="px-5 py-4 text-center">
                    <select v-model="company.status"
                      class="rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs text-slate-200 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500"
                      @change="updateCompany(company)">
                      <option value="active" class="bg-slate-900">active</option>
                      <option value="inactive" class="bg-slate-900">inactive</option>
                    </select>
                  </td>
                  <td class="px-5 py-4">
                    <div class="flex items-center justify-end gap-2">
                      <Link :href="`/landlord/companies/${company.id}/modules`"
                        class="rounded-lg bg-cyan-500/10 px-3 py-1.5 text-xs font-medium text-cyan-400 ring-1 ring-cyan-500/20 hover:bg-cyan-500/20 transition">
                        Modules
                      </Link>
                      <a :href="`/landlord/companies/${company.id}/database/download`"
                        class="rounded-lg bg-indigo-500/10 px-3 py-1.5 text-xs font-medium text-indigo-400 ring-1 ring-indigo-500/20 hover:bg-indigo-500/20 transition">
                        Backup
                      </a>
                      <button @click="toggleExpand(company.id)"
                        class="rounded-lg bg-blue-500/10 px-3 py-1.5 text-xs font-medium text-blue-400 ring-1 ring-blue-500/20 hover:bg-blue-500/20 transition">
                        + License
                      </button>
                    </div>
                  </td>
                </tr>
                <!-- Expandable License Generation Row -->
                <tr v-if="expandedCompany === company.id" class="bg-white/[0.02]">
                  <td colspan="6" class="px-5 py-4">
                    <div class="flex flex-wrap items-end gap-3 pl-4 border-l-2 border-cyan-500/30">
                      <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs text-slate-500 mb-1">Bind Email (optional)</label>
                        <input v-model="licenseForm(company.id).bound_email" type="email" placeholder="email@example.com"
                          class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-200 placeholder:text-slate-600 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500" />
                      </div>
                      <div class="min-w-[160px]">
                        <label class="block text-xs text-slate-500 mb-1">Expires At</label>
                        <input v-model="licenseForm(company.id).expires_at" type="date"
                          class="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-200 focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500" />
                      </div>
                      <button @click="generateLicense(company)"
                        class="rounded-lg bg-gradient-to-r from-blue-600 to-cyan-600 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-blue-500/20 hover:shadow-blue-500/40 transition-all">
                        Generate License Key
                      </button>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Licenses Table -->
      <section v-show="activeTab === 'licenses'" class="rounded-2xl border border-white/5 bg-white/[0.02] backdrop-blur overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-white/5 bg-white/[0.02]">
                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Company</th>
                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">License Key</th>
                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Bound Email</th>
                <th class="px-5 py-4 text-center text-xs font-semibold uppercase tracking-wider text-slate-400">Status</th>
                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Activated</th>
                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Expires</th>
                <th class="px-5 py-4 text-right text-xs font-semibold uppercase tracking-wider text-slate-400">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
              <tr v-for="license in licenses" :key="license.id" class="hover:bg-white/[0.02] transition-colors">
                <td class="px-5 py-4">
                  <p class="font-semibold text-white">{{ license.company }}</p>
                  <p class="text-xs text-slate-500">{{ license.slug }}</p>
                </td>
                <td class="px-5 py-4">
                  <code v-if="license.license_key" class="font-mono text-xs font-semibold text-cyan-300 bg-cyan-500/10 rounded-md px-2.5 py-1 select-all ring-1 ring-cyan-500/20">
                    {{ license.license_key }}
                  </code>
                  <span v-else class="text-xs text-slate-600 italic">—</span>
                </td>
                <td class="px-5 py-4">
                  <span class="text-xs text-slate-400">{{ license.bound_email || '—' }}</span>
                </td>
                <td class="px-5 py-4 text-center">
                  <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                    :class="{
                      'bg-emerald-500/10 text-emerald-400 ring-1 ring-emerald-500/20': license.status === 'active',
                      'bg-amber-500/10 text-amber-400 ring-1 ring-amber-500/20': license.status === 'pending',
                      'bg-rose-500/10 text-rose-400 ring-1 ring-rose-500/20': license.status === 'suspended',
                    }">
                    <span class="mr-1.5 h-1.5 w-1.5 rounded-full"
                      :class="{
                        'bg-emerald-400': license.status === 'active',
                        'bg-amber-400': license.status === 'pending',
                        'bg-rose-400': license.status === 'suspended',
                      }"></span>
                    {{ license.status }}
                  </span>
                </td>
                <td class="px-5 py-4">
                  <span class="text-xs text-slate-400">{{ license.activated_at || '—' }}</span>
                </td>
                <td class="px-5 py-4">
                  <span class="text-xs text-slate-400">{{ license.expires_at || 'Never' }}</span>
                </td>
                <td class="px-5 py-4">
                  <div class="flex items-center justify-end gap-2">
                    <button v-if="license.status !== 'active'"
                      @click="activateLicense(license)"
                      class="rounded-lg bg-emerald-500/10 px-3 py-1.5 text-xs font-medium text-emerald-400 ring-1 ring-emerald-500/20 hover:bg-emerald-500/20 transition">
                      Activate
                    </button>
                    <button v-if="license.status === 'active'"
                      @click="suspendLicense(license)"
                      class="rounded-lg bg-rose-500/10 px-3 py-1.5 text-xs font-medium text-rose-400 ring-1 ring-rose-500/20 hover:bg-rose-500/20 transition">
                      Suspend
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Audit Trail Table -->
      <section v-show="activeTab === 'audit'" class="rounded-2xl border border-white/5 bg-white/[0.02] backdrop-blur overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-white/5 bg-white/[0.02]">
                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Timestamp</th>
                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Company</th>
                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Action</th>
                <th class="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Actor</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/5">
              <tr v-for="log in auditLogs" :key="log.id" class="hover:bg-white/[0.02] transition-colors">
                <td class="px-5 py-4">
                  <span class="text-xs text-slate-500 font-mono">{{ log.created_at }}</span>
                </td>
                <td class="px-5 py-4">
                  <span class="font-medium text-white">{{ log.company || '—' }}</span>
                </td>
                <td class="px-5 py-4">
                  <span class="rounded-md bg-white/5 px-2 py-1 text-xs font-medium text-slate-300 ring-1 ring-white/10">{{ log.action }}</span>
                </td>
                <td class="px-5 py-4">
                  <span class="text-xs text-slate-400">{{ log.actor_email || 'system' }}</span>
                </td>
              </tr>
              <tr v-if="auditLogs.length === 0">
                <td colspan="4" class="px-5 py-8 text-center text-slate-600">No audit entries yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

    </div>
  </div>
</template>
