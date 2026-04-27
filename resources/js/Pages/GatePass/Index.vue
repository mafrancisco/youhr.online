<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import FlashMessage from '@/Components/FlashMessage.vue'
import Modal from '@/Components/Modal.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const props = defineProps({
  passes: Array,
})

const showModal  = ref(false)
const editPass   = ref(null)

function blankForm() {
  return {
    gatepass_type:    'Official Business',
    gatepass_date:    '',
    gatepass_timeout: '',
    gatepass_timein:  '',
    purpose:          '',
    destination:      '',
  }
}

const form = useForm(blankForm())

function openCreate() {
  editPass.value = null
  Object.assign(form, blankForm())
  form.reset()
  showModal.value = true
}

function openEdit(pass) {
  editPass.value = pass
  form.gatepass_type    = pass.gatepass_type
  form.gatepass_date    = pass.gatepass_date
  form.gatepass_timeout = pass.gatepass_timeout || ''
  form.gatepass_timein  = pass.gatepass_timein  || ''
  form.purpose          = pass.purpose          || ''
  form.destination      = pass.destination      || ''
  showModal.value = true
}

function submit() {
  if (editPass.value) {
    form.put(`/gate-passes/${editPass.value.id}`, {
      onSuccess: () => { showModal.value = false },
    })
  } else {
    form.post('/gate-passes', {
      onSuccess: () => {
        showModal.value = false
        form.reset()
      },
    })
  }
}

function cancelPass(id) {
  if (!confirm('Cancel this gate pass?')) return
  useForm({}).delete(`/gate-passes/${id}`)
}

function downloadPass(id) {
  window.location.href = `/gate-passes/${id}/download`
}

function statusClass(status) {
  if (status === 'Approved') return 'bg-green-100 text-green-700'
  if (status === 'Pending')  return 'bg-amber-100 text-amber-700'
  return 'bg-red-100 text-red-700'
}
</script>

<template>
  <AppLayout title="Gate Pass">
    <template #header>Gate Pass</template>
    <FlashMessage />

    <div class="space-y-4">
      <div class="flex justify-end">
        <PrimaryButton @click="openCreate">File Gate Pass</PrimaryButton>
      </div>

      <!-- Gate Pass Table -->
      <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Control No.</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Type</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Time Out</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Time In</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Purpose</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Destination</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Approved</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="passes.length === 0">
              <td colspan="10" class="px-4 py-8 text-center text-gray-400 text-sm">No gate passes yet.</td>
            </tr>
            <tr v-for="pass in passes" :key="pass.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ pass.controlno }}</td>
              <td class="px-4 py-3 text-xs">{{ pass.gatepass_type }}</td>
              <td class="px-4 py-3 text-xs">{{ pass.gatepass_date }}</td>
              <td class="px-4 py-3 text-center text-xs">{{ pass.gatepass_timeout }}</td>
              <td class="px-4 py-3 text-center text-xs">{{ pass.gatepass_timein }}</td>
              <td class="px-4 py-3 text-xs max-w-xs truncate" :title="pass.purpose">{{ pass.purpose }}</td>
              <td class="px-4 py-3 text-xs max-w-xs truncate" :title="pass.destination">{{ pass.destination }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statusClass(pass.status)]">
                  {{ pass.status }}
                </span>
              </td>
              <td class="px-4 py-3 text-xs text-gray-500">{{ pass.date_time_approved || '—' }}</td>
              <td class="px-4 py-3 text-center space-x-2 whitespace-nowrap">
                <button @click="downloadPass(pass.id)" class="text-xs text-blue-600 hover:underline">PDF</button>
                <button v-if="pass.status === 'Pending'" @click="openEdit(pass)"
                  class="text-xs text-amber-600 hover:underline">Edit</button>
                <button v-if="pass.status === 'Pending'" @click="cancelPass(pass.id)"
                  class="text-xs text-red-500 hover:underline">Cancel</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- File / Edit Gate Pass Modal -->
    <Modal :show="showModal" @close="showModal = false">
      <div class="p-6">
        <h3 class="text-base font-semibold text-gray-800 mb-4">
          {{ editPass ? 'Edit Gate Pass' : 'File Gate Pass' }}
        </h3>
        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Type</label>
            <select v-model="form.gatepass_type" required
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option>Official Business</option>
              <option>Official Time</option>
              <option>Personal</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Date</label>
            <input v-model="form.gatepass_date" type="date" required
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Expected Time Out</label>
              <input v-model="form.gatepass_timeout" type="time"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Expected Return Time</label>
              <input v-model="form.gatepass_timein" type="time"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Purpose</label>
            <input v-model="form.purpose" type="text" required placeholder="Purpose of gate pass"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Destination</label>
            <input v-model="form.destination" type="text" placeholder="Destination (optional)"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>

          <div v-if="Object.keys(form.errors).length" class="text-xs text-red-600 space-y-0.5">
            <p v-for="(err, key) in form.errors" :key="key">{{ err }}</p>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="showModal = false"
              class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
            <PrimaryButton type="submit" :loading="form.processing">
              {{ editPass ? 'Update' : 'Submit' }}
            </PrimaryButton>
          </div>
        </form>
      </div>
    </Modal>
  </AppLayout>
</template>
