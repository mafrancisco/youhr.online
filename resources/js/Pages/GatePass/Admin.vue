<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import FlashMessage from '@/Components/FlashMessage.vue'

defineProps({ passes: Array })

const modalTarget = ref(null)

const form = useForm({
  actual_timeout: '',
  actual_timein:  '',
})

function openApprove(gp) {
  form.reset()
  form.clearErrors()
  form.actual_timeout = gp.gatepass_timeout ?? ''
  form.actual_timein  = gp.gatepass_timein  ?? ''
  modalTarget.value   = gp
}

function closeModal() {
  modalTarget.value = null
  form.reset()
  form.clearErrors()
}

function approve() {
  form.put(`/gate-passes/${modalTarget.value.id}/approve`, { onSuccess: closeModal })
}

function cancel(gp) {
  if (!confirm(`Cancel gate pass ${gp.controlno}?`)) return
  router.put(`/gate-passes/${gp.id}/cancel`)
}

const typeColors = {
  'Official Business': 'bg-blue-100 text-blue-700',
  'Official Time':     'bg-green-100 text-green-700',
  'Personal':          'bg-gray-100 text-gray-600',
}
</script>

<template>
  <AppLayout title="Gate Passes">
    <FlashMessage />

    <div class="flex items-center justify-between mb-5">
      <h2 class="text-base font-semibold text-gray-700">Pending Gate Passes ({{ passes.length }})</h2>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-4 py-3 text-left">Control No.</th>
            <th class="px-4 py-3 text-left">Employee</th>
            <th class="px-4 py-3 text-left">Type</th>
            <th class="px-4 py-3 text-left">Date</th>
            <th class="px-4 py-3 text-left">Time Out</th>
            <th class="px-4 py-3 text-left">Time In</th>
            <th class="px-4 py-3 text-left">Purpose</th>
            <th class="px-4 py-3 text-left">Filed</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="gp in passes" :key="gp.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ gp.controlno }}</td>
            <td class="px-4 py-3 font-medium text-gray-900">{{ gp.empName }}</td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                :class="typeColors[gp.gatepass_type] ?? 'bg-gray-100 text-gray-600'">
                {{ gp.gatepass_type }}
              </span>
            </td>
            <td class="px-4 py-3 text-gray-600">{{ gp.gatepass_date }}</td>
            <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ gp.gatepass_timeout || '—' }}</td>
            <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ gp.gatepass_timein  || '—' }}</td>
            <td class="px-4 py-3 text-gray-500 text-xs max-w-[160px] truncate" :title="gp.purpose">{{ gp.purpose }}</td>
            <td class="px-4 py-3 text-gray-400 text-xs">{{ gp.gatepass_datefiled }}</td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <button @click="openApprove(gp)" class="text-green-600 hover:underline text-xs mr-3">Approve</button>
              <button @click="cancel(gp)"       class="text-red-500 hover:underline text-xs">Cancel</button>
            </td>
          </tr>
          <tr v-if="!passes.length">
            <td colspan="9" class="px-4 py-8 text-center text-gray-400">No pending gate passes.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <Modal :show="!!modalTarget" size="sm" @close="closeModal">
      <template #header>Approve Gate Pass — {{ modalTarget?.controlno }}</template>

      <template #body>
        <form @submit.prevent="approve" id="gp-form" class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Actual Time Out</label>
            <input v-model="form.actual_timeout" type="time"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Actual Time In</label>
            <input v-model="form.actual_timein" type="time"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
          </div>
        </form>
      </template>

      <template #footer>
        <PrimaryButton variant="ghost" @click="closeModal">Cancel</PrimaryButton>
        <PrimaryButton type="submit" form="gp-form" variant="success" :loading="form.processing">
          Approve
        </PrimaryButton>
      </template>
    </Modal>
  </AppLayout>
</template>
