<script setup>
import { ref } from 'vue'
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

// Edit time log modal
const showEditModal = ref(false)
const editDay       = ref(null)
const editForm      = useForm({
  AttDate:     '',
  attID:       null,
  StartTime1:  '',
  StartTime2:  '',
  StartTime3:  '',
  StartTime4:  '',
  remarks:     '',
})

function openEdit(day) {
  editDay.value       = day
  editForm.AttDate    = day.attDate
  editForm.attID      = null
  editForm.StartTime1 = ''
  editForm.StartTime2 = ''
  editForm.StartTime3 = ''
  editForm.StartTime4 = ''
  editForm.remarks    = day.remarks || ''
  showEditModal.value = true
}

function isValidTime(t) {
  if (!t || t === '') return false
  return /^(0?[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/.test(t)
}

function hasExistingLog(field) {
  if (!editDay.value) return false
  return isValidTime(editDay.value[field])
}

function submitEdit() {
  editForm.post('/dtr/requests', {
    onSuccess: () => {
      showEditModal.value = false
      editForm.reset()
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

function isWeekend(dayName) {
  return ['Sat', 'Sun'].includes(dayName)
}

// Check if a time field was manually edited (from request record)
function isEdited(day, field) {
  if (!day.request) return false
  const logMap = { StartTime1: 'log1', StartTime2: 'log2', StartTime3: 'log3', StartTime4: 'log4' }
  return day.request[logMap[field]] === '1'
}
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

      <!-- Legend -->
      <div class="flex items-center gap-4 text-xs text-gray-500">
        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500"></span> Manually edited</span>
        <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-gray-400"></span> Biometric recorded</span>
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
              <td class="px-3 py-2 text-center text-xs" :class="isEdited(day, 'StartTime1') ? 'text-red-600 font-medium' : ''">
                {{ formatTime(day.StartTime1) }}
              </td>
              <td class="px-3 py-2 text-center text-xs" :class="isEdited(day, 'StartTime2') ? 'text-red-600 font-medium' : ''">
                {{ formatTime(day.StartTime2) }}
              </td>
              <td class="px-3 py-2 text-center text-xs" :class="isEdited(day, 'StartTime3') ? 'text-red-600 font-medium' : ''">
                {{ formatTime(day.StartTime3) }}
              </td>
              <td class="px-3 py-2 text-center text-xs" :class="isEdited(day, 'StartTime4') ? 'text-red-600 font-medium' : ''">
                {{ formatTime(day.StartTime4) }}
              </td>
              <td class="px-3 py-2 text-center text-xs text-purple-600">{{ day.OTIn }}</td>
              <td class="px-3 py-2 text-center text-xs text-purple-600">{{ day.OTOut }}</td>
              <td class="px-3 py-2 text-center text-xs text-red-600">{{ formatMins(day.Tardiness) }}</td>
              <td class="px-3 py-2 text-center text-xs text-orange-600">{{ formatMins(day.undertime) }}</td>
              <td class="px-3 py-2 text-center text-xs text-green-600">{{ formatMins(day.OT) }}</td>
              <td class="px-3 py-2 text-center text-xs">{{ day.remarks }}</td>
              <td v-if="!submitted" class="px-3 py-2 text-center">
                <template v-if="!isWeekend(day.dayName)">
                  <button v-if="!day.editBlocked" @click="openEdit(day)"
                    class="text-xs text-blue-600 hover:underline">
                    Edit
                  </button>
                  <span v-else class="text-xs text-gray-400 cursor-not-allowed" :title="day.editBlocked">
                    🔒
                  </span>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Edit Time Log Modal -->
    <Modal :show="showEditModal" @close="showEditModal = false">
      <template #header>Edit Time Log — {{ editDay?.attDate }}</template>
      <template #body>
        <form @submit.prevent="submitEdit" class="space-y-3">
          <p class="text-xs text-gray-500 mb-2">
            Only blank time entries can be edited. Fields with biometric data are locked.
          </p>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">AM Time In</label>
              <input v-model="editForm.StartTime1" type="time" :disabled="hasExistingLog('StartTime1')"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100 disabled:cursor-not-allowed" />
              <p v-if="hasExistingLog('StartTime1')" class="text-xs text-gray-400 mt-0.5">🔒 {{ editDay?.StartTime1 }}</p>
              <p v-if="editForm.errors.StartTime1" class="text-xs text-red-500 mt-0.5">{{ editForm.errors.StartTime1 }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">AM Time Out</label>
              <input v-model="editForm.StartTime2" type="time" :disabled="hasExistingLog('StartTime2')"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100 disabled:cursor-not-allowed" />
              <p v-if="hasExistingLog('StartTime2')" class="text-xs text-gray-400 mt-0.5">🔒 {{ editDay?.StartTime2 }}</p>
              <p v-if="editForm.errors.StartTime2" class="text-xs text-red-500 mt-0.5">{{ editForm.errors.StartTime2 }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">PM Time In</label>
              <input v-model="editForm.StartTime3" type="time" :disabled="hasExistingLog('StartTime3')"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100 disabled:cursor-not-allowed" />
              <p v-if="hasExistingLog('StartTime3')" class="text-xs text-gray-400 mt-0.5">🔒 {{ editDay?.StartTime3 }}</p>
              <p v-if="editForm.errors.StartTime3" class="text-xs text-red-500 mt-0.5">{{ editForm.errors.StartTime3 }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">PM Time Out</label>
              <input v-model="editForm.StartTime4" type="time" :disabled="hasExistingLog('StartTime4')"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100 disabled:cursor-not-allowed" />
              <p v-if="hasExistingLog('StartTime4')" class="text-xs text-gray-400 mt-0.5">🔒 {{ editDay?.StartTime4 }}</p>
              <p v-if="editForm.errors.StartTime4" class="text-xs text-red-500 mt-0.5">{{ editForm.errors.StartTime4 }}</p>
            </div>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Remarks</label>
            <textarea v-model="editForm.remarks" rows="2"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Reason for edit (e.g. forgot to log, brownout)"></textarea>
          </div>
          <div v-if="editForm.errors.AttDate" class="text-xs text-red-600">
            {{ editForm.errors.AttDate }}
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="showEditModal = false"
              class="px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
            <PrimaryButton type="submit" :loading="editForm.processing">Save Changes</PrimaryButton>
          </div>
        </form>
      </template>
    </Modal>
  </AppLayout>
</template>
