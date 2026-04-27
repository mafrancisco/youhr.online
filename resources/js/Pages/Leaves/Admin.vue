<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import FlashMessage from '@/Components/FlashMessage.vue'

defineProps({ leaves: Array })

const modalTarget = ref(null)

const form = useForm({
  status:          'Approved',
  credits_vl:      '',
  credits_sl:      '',
  ot_credits:      '',
  service_credits: '',
})

function openReview(leave) {
  form.reset()
  form.clearErrors()
  form.status = 'Approved'
  modalTarget.value = leave
}

function closeModal() {
  modalTarget.value = null
  form.reset()
  form.clearErrors()
}

function submit() {
  form.put(`/leaves/${modalTarget.value.id}`, { onSuccess: closeModal })
}
</script>

<template>
  <AppLayout title="Pending Leaves">
    <FlashMessage />

    <div class="flex items-center justify-between mb-5">
      <h2 class="text-base font-semibold text-gray-700">Pending Leaves ({{ leaves.length }})</h2>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-4 py-3 text-left">Control No.</th>
            <th class="px-4 py-3 text-left">Employee</th>
            <th class="px-4 py-3 text-left">Type</th>
            <th class="px-4 py-3 text-left">Date Filed</th>
            <th class="px-4 py-3 text-left">Dates</th>
            <th class="px-4 py-3 text-left">Days</th>
            <th class="px-4 py-3 text-left">Details</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="l in leaves" :key="l.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ l.controlno }}</td>
            <td class="px-4 py-3 font-medium text-gray-900">{{ l.empName }}</td>
            <td class="px-4 py-3 text-gray-600">{{ l.type_name }}</td>
            <td class="px-4 py-3 text-gray-500 text-xs">{{ l.date_filed }}</td>
            <td class="px-4 py-3 text-gray-600 text-xs max-w-[180px] truncate" :title="l.dates">{{ l.dates }}</td>
            <td class="px-4 py-3 text-center text-gray-700">{{ l.noofdays }}</td>
            <td class="px-4 py-3 text-gray-500 text-xs max-w-[140px] truncate" :title="l.details">{{ l.details || '—' }}</td>
            <td class="px-4 py-3 text-right">
              <button @click="openReview(l)" class="text-blue-600 hover:underline text-xs">Review</button>
            </td>
          </tr>
          <tr v-if="!leaves.length">
            <td colspan="8" class="px-4 py-8 text-center text-gray-400">No pending leaves.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <Modal :show="!!modalTarget" size="md" @close="closeModal">
      <template #header>Review Leave — {{ modalTarget?.controlno }}</template>

      <template #body>
        <form @submit.prevent="submit" id="leave-form" class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Decision</label>
            <select v-model="form.status"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
              <option value="Approved">Approved</option>
              <option value="Cancelled">Cancelled</option>
            </select>
          </div>

          <template v-if="form.status === 'Approved'">
            <p class="text-xs font-medium text-gray-600">Credits to deduct</p>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs text-gray-600 mb-1">Vacation Leave</label>
                <input v-model="form.credits_vl" type="number" min="0" step="0.001"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
              </div>
              <div>
                <label class="block text-xs text-gray-600 mb-1">Sick Leave</label>
                <input v-model="form.credits_sl" type="number" min="0" step="0.001"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
              </div>
              <div>
                <label class="block text-xs text-gray-600 mb-1">OT Credits</label>
                <input v-model="form.ot_credits" type="number" min="0" step="0.001"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
              </div>
              <div>
                <label class="block text-xs text-gray-600 mb-1">Service Credits</label>
                <input v-model="form.service_credits" type="number" min="0" step="0.001"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
              </div>
            </div>
          </template>
        </form>
      </template>

      <template #footer>
        <PrimaryButton variant="ghost" @click="closeModal">Cancel</PrimaryButton>
        <PrimaryButton type="submit" form="leave-form" :loading="form.processing"
          :variant="form.status === 'Cancelled' ? 'danger' : 'primary'">
          {{ form.status === 'Approved' ? 'Approve' : 'Cancel Leave' }}
        </PrimaryButton>
      </template>
    </Modal>
  </AppLayout>
</template>
