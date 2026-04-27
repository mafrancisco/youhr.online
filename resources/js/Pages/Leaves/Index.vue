<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import FlashMessage from '@/Components/FlashMessage.vue'
import Modal from '@/Components/Modal.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const props = defineProps({
  leaveTypes: Array,
  leaves:     Array,
})

// File Leave Modal
const showModal    = ref(false)
const selectedType = ref(null)

const form = useForm({
  leave_type:    '',
  dates:         [],
  destination:   '',
  location:      '',
  sickleave:     '',
  illness:       '',
  women_illness: '',
  studyleave:    '',
  otherleave:    '',
})

function openModal() {
  form.reset()
  selectedType.value = null
  showModal.value    = true
}

function onTypeChange() {
  selectedType.value = props.leaveTypes.find(t => t.id == form.leave_type) || null
}

// Multi-date picker: comma-separated dates
const dateInput = ref('')
function addDate() {
  const d = dateInput.value.trim()
  if (d && !form.dates.includes(d)) form.dates.push(d)
  dateInput.value = ''
}
function removeDate(d) {
  form.dates = form.dates.filter(x => x !== d)
}

function submitLeave() {
  form.post('/leaves', {
    onSuccess: () => {
      showModal.value = false
      form.reset()
    },
  })
}

function cancelLeave(id) {
  if (!confirm('Cancel this leave application?')) return
  useForm({}).delete(`/leaves/${id}`)
}

function downloadForm(id) {
  window.location.href = `/leaves/${id}/download`
}

function statusClass(status) {
  if (status === 'Approved') return 'bg-green-100 text-green-700'
  if (status === 'Pending')  return 'bg-amber-100 text-amber-700'
  return 'bg-red-100 text-red-700'
}

// Leave type acronym determines which sub-fields to show
const typeCode = (acronym) => selectedType.value?.acronym === acronym
</script>

<template>
  <AppLayout title="My Leaves">
    <template #header>My Leaves</template>
    <FlashMessage />

    <div class="space-y-4">
      <div class="flex justify-end">
        <PrimaryButton @click="openModal">File Leave</PrimaryButton>
      </div>

      <!-- Leave Table -->
      <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Control No.</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date Filed</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Type</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Dates</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Days</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="leaves.length === 0">
              <td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">No leave applications yet.</td>
            </tr>
            <tr v-for="leave in leaves" :key="leave.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ leave.controlno }}</td>
              <td class="px-4 py-3 text-xs text-gray-600">{{ leave.date_filed }}</td>
              <td class="px-4 py-3 text-xs">{{ leave.type_name }}</td>
              <td class="px-4 py-3 text-xs text-gray-600 max-w-xs truncate" :title="leave.dates">{{ leave.dates }}</td>
              <td class="px-4 py-3 text-center text-xs">{{ leave.noofdays }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statusClass(leave.status)]">
                  {{ leave.status }}
                </span>
              </td>
              <td class="px-4 py-3 text-center space-x-2">
                <button @click="downloadForm(leave.id)"
                  class="text-xs text-blue-600 hover:underline">PDF</button>
                <button v-if="leave.status === 'Pending'"
                  @click="cancelLeave(leave.id)"
                  class="text-xs text-red-500 hover:underline">Cancel</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- File Leave Modal -->
    <Modal :show="showModal" @close="showModal = false">
      <div class="p-6 max-h-[90vh] overflow-y-auto">
        <h3 class="text-base font-semibold text-gray-800 mb-4">File Leave Application</h3>
        <form @submit.prevent="submitLeave" class="space-y-4">

          <!-- Leave Type -->
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Leave Type</label>
            <select v-model="form.leave_type" required @change="onTypeChange"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">Select leave type...</option>
              <option v-for="lt in leaveTypes" :key="lt.id" :value="lt.id">{{ lt.leave_type }}</option>
            </select>
            <p v-if="form.errors.leave_type" class="text-xs text-red-500 mt-1">{{ form.errors.leave_type }}</p>
          </div>

          <!-- Dates -->
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Leave Dates</label>
            <div class="flex gap-2 mb-2">
              <input v-model="dateInput" type="date"
                class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
              <button type="button" @click="addDate"
                class="px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">Add</button>
            </div>
            <div class="flex flex-wrap gap-2">
              <span v-for="d in form.dates" :key="d"
                class="flex items-center gap-1 px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs">
                {{ d }}
                <button type="button" @click="removeDate(d)" class="text-blue-400 hover:text-red-500 font-bold">×</button>
              </span>
            </div>
            <p v-if="form.errors.dates" class="text-xs text-red-500 mt-1">{{ form.errors.dates }}</p>
          </div>

          <!-- Vacation Leave sub-fields -->
          <template v-if="typeCode('VL')">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Type of vacation leave</label>
              <select v-model="form.destination"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Select...</option>
                <option value="Within the Philippines">Within the Philippines</option>
                <option value="Abroad">Abroad</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Destination / Specific Location</label>
              <input v-model="form.location" type="text" placeholder="City, Province..."
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
          </template>

          <!-- Sick Leave sub-fields -->
          <template v-if="typeCode('SL')">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">In case of sick leave</label>
              <select v-model="form.sickleave"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Select...</option>
                <option value="In Hospital">In Hospital</option>
                <option value="Out Patient">Out Patient</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Illness / Disease</label>
              <input v-model="form.illness" type="text"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
          </template>

          <!-- Study Leave -->
          <div v-if="typeCode('ST-L')">
            <label class="block text-xs font-medium text-gray-700 mb-1">Study leave details</label>
            <input v-model="form.studyleave" type="text"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>

          <!-- Other Leave -->
          <div v-if="selectedType && !['VL','SL','ST-L'].includes(selectedType.acronym)">
            <label class="block text-xs font-medium text-gray-700 mb-1">Details</label>
            <input v-model="form.otherleave" type="text"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="showModal = false"
              class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
            <PrimaryButton type="submit" :loading="form.processing" :disabled="!form.leave_type || form.dates.length === 0">
              Submit Application
            </PrimaryButton>
          </div>
        </form>
      </div>
    </Modal>
  </AppLayout>
</template>
