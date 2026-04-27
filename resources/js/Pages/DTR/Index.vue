<script setup>
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import FlashMessage from '@/Components/FlashMessage.vue'
import Modal from '@/Components/Modal.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const props = defineProps({
  employee:  Object,
  month:     String,
  days:      Array,
  submitted: Boolean,
})

const selectedMonth = ref(props.month)

function loadMonth() {
  router.get('/dtr', { month: selectedMonth.value }, { preserveState: false })
}

// Submit DTR
const submitForm = useForm({ attRange: props.month })
function submitDTR() {
  if (!confirm('Submit DTR for this period? This cannot be undone.')) return
  submitForm.attRange = props.month
  submitForm.post('/dtr/submit')
}

// Download individual PDF
function downloadPDF() {
  const [y, m] = props.month.split('-')
  const start = `${y}-${m}-01`
  const endDate = new Date(y, m, 0)
  const end = `${y}-${m}-${String(endDate.getDate()).padStart(2, '0')}`
  window.location.href = `/dtr/download?start_date=${start}&end_date=${end}`
}

// Request correction modal
const showRequestModal = ref(false)
const requestDay       = ref(null)
const requestForm      = useForm({
  BadgeNumber: props.employee?.badgeID,
  AttDate:     '',
  StartTime1:  '',
  StartTime2:  '',
  StartTime3:  '',
  StartTime4:  '',
  remarks:     '',
})

function openRequest(day) {
  requestDay.value       = day
  requestForm.AttDate    = day.attDate
  requestForm.StartTime1 = day.StartTime1 || ''
  requestForm.StartTime2 = day.StartTime2 || ''
  requestForm.StartTime3 = day.StartTime3 || ''
  requestForm.StartTime4 = day.StartTime4 || ''
  requestForm.remarks    = day.remarks || ''
  showRequestModal.value = true
}

function submitRequest() {
  requestForm.post('/dtr/requests', {
    onSuccess: () => {
      showRequestModal.value = false
      requestForm.reset()
    },
  })
}

function formatTime(t) {
  if (!t) return ''
  if (['L', 'A', 'T', 'OB', 'AWA', 'Saturday', 'Sunday'].includes(t)) return t
  const d = new Date('1970-01-01T' + t + ':00')
  if (isNaN(d)) return t
  return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
}

function formatMins(mins) {
  if (!mins || mins === 0) return ''
  const h = Math.floor(mins / 60)
  const m = mins % 60
  return h > 0 ? `${h}h ${m}m` : `${m}m`
}

const isWeekend = (dayName) => ['Sat', 'Sun'].includes(dayName)
</script>

<template>
  <AppLayout title="My DTR">
    <template #header>My Daily Time Record</template>
    <FlashMessage />

    <div class="space-y-4">
      <!-- Controls -->
      <div class="flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-2">
          <label class="text-sm font-medium text-gray-700">Period:</label>
          <input v-model="selectedMonth" type="month"
            class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          <button @click="loadMonth"
            class="px-3 py-1.5 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">
            View
          </button>
        </div>

        <div class="ml-auto flex gap-2">
          <button @click="downloadPDF"
            class="px-4 py-1.5 border border-gray-300 text-sm rounded-lg hover:bg-gray-50 text-gray-700">
            Download PDF
          </button>
          <PrimaryButton v-if="!submitted" :loading="submitForm.processing" @click="submitDTR">
            Submit DTR
          </PrimaryButton>
          <span v-else class="px-4 py-1.5 bg-green-100 text-green-700 text-sm rounded-lg font-medium">
            Submitted
          </span>
        </div>
      </div>

      <!-- DTR Table -->
      <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Day</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase">AM In</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase">AM Out</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase">PM In</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase">PM Out</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase">OT In</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase">OT Out</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Tardy</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Undertime</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase">OT</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Remarks</th>
              <th v-if="!submitted" class="px-3 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="day in days" :key="day.date"
              :class="[isWeekend(day.dayName) ? 'bg-gray-50 text-gray-400' : 'hover:bg-blue-50']">
              <td class="px-3 py-2 font-medium text-gray-800 whitespace-nowrap">
                {{ new Date(day.date + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', weekday: 'short' }) }}
              </td>
              <td class="px-3 py-2 text-center text-xs">{{ formatTime(day.StartTime1) }}</td>
              <td class="px-3 py-2 text-center text-xs">{{ formatTime(day.StartTime2) }}</td>
              <td class="px-3 py-2 text-center text-xs">{{ formatTime(day.StartTime3) }}</td>
              <td class="px-3 py-2 text-center text-xs">{{ formatTime(day.StartTime4) }}</td>
              <td class="px-3 py-2 text-center text-xs text-purple-600">{{ day.OTIn }}</td>
              <td class="px-3 py-2 text-center text-xs text-purple-600">{{ day.OTOut }}</td>
              <td class="px-3 py-2 text-center text-xs text-red-600">{{ formatMins(day.Tardiness) }}</td>
              <td class="px-3 py-2 text-center text-xs text-orange-600">{{ formatMins(day.undertime) }}</td>
              <td class="px-3 py-2 text-center text-xs text-green-600">{{ formatMins(day.OT) }}</td>
              <td class="px-3 py-2 text-center text-xs">{{ day.remarks }}</td>
              <td v-if="!submitted" class="px-3 py-2 text-center">
                <button v-if="!isWeekend(day.dayName)" @click="openRequest(day)"
                  class="text-xs text-blue-600 hover:underline">
                  Request
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Correction Request Modal -->
    <Modal :show="showRequestModal" @close="showRequestModal = false">
      <div class="p-6">
        <h3 class="text-base font-semibold text-gray-800 mb-4">
          Request Correction — {{ requestDay?.attDate }}
        </h3>
        <form @submit.prevent="submitRequest" class="space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">AM Time In</label>
              <input v-model="requestForm.StartTime1" type="time"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">AM Time Out</label>
              <input v-model="requestForm.StartTime2" type="time"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">PM Time In</label>
              <input v-model="requestForm.StartTime3" type="time"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">PM Time Out</label>
              <input v-model="requestForm.StartTime4" type="time"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Remarks / Reason</label>
            <textarea v-model="requestForm.remarks" rows="2"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Reason for correction request"></textarea>
          </div>
          <div v-if="requestForm.errors.StartTime1 || requestForm.errors.AttDate" class="text-xs text-red-600">
            {{ requestForm.errors.StartTime1 || requestForm.errors.AttDate }}
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="showRequestModal = false"
              class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
            <PrimaryButton type="submit" :loading="requestForm.processing">Submit Request</PrimaryButton>
          </div>
        </form>
      </div>
    </Modal>
  </AppLayout>
</template>
