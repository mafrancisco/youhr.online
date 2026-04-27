<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import FlashMessage from '@/Components/FlashMessage.vue'

const props = defineProps({
  employee: Object,
  month:    String,
  records:  Array,
  requests: Array,
})

const selectedMonth = ref(props.month)

function loadMonth() {
  router.get(`/employees/${props.employee.id}/dtr`, { month: selectedMonth.value }, { preserveState: false })
}

// Inline time editing — clicking a cell lets HR type a time directly
const editing  = ref(null)  // { id, column }
const editForm = useForm({ column: '', time: '' })

function startEdit(id, column, current) {
  editing.value   = { id, column }
  editForm.column = column
  editForm.time   = current || ''
}

function saveEdit(cleanId) {
  editForm.put(`/attendance/${cleanId}/time`, {
    onSuccess: () => { editing.value = null },
    onError:   () => { editing.value = null },
  })
}

function cancelEdit() {
  editing.value = null
}

function isEditing(id, column) {
  return editing.value?.id === id && editing.value?.column === column
}

// Find matching request for a record
function requestFor(record) {
  return props.requests.find(r => r.attDate === record.attDate)
}

function formatTime(t) {
  if (!t) return '—'
  if (['L', 'A', 'T', 'OB', 'AWA', 'Saturday', 'Sunday'].includes(t)) return t
  const d = new Date('1970-01-01T' + t + ':00')
  if (isNaN(d)) return t
  return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
}

function formatMins(mins) {
  if (!mins) return ''
  const h = Math.floor(mins / 60)
  const m = mins % 60
  return h > 0 ? `${h}h ${m}m` : `${m}m`
}

function downloadPDF() {
  const [y, m] = selectedMonth.value.split('-')
  const start = `${y}-${m}-01`
  const lastDay = new Date(y, m, 0).getDate()
  const end = `${y}-${m}-${String(lastDay).padStart(2, '0')}`
  window.location.href = `/dtr/download?badge=${props.employee.badgeID}&start_date=${start}&end_date=${end}`
}

const columns = ['StartTime1', 'StartTime2', 'StartTime3', 'StartTime4']
const colLabels = { StartTime1: 'AM In', StartTime2: 'AM Out', StartTime3: 'PM In', StartTime4: 'PM Out' }
</script>

<template>
  <AppLayout :title="`DTR — ${employee.empName}`">
    <template #header>DTR Log — {{ employee.empName }}</template>
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
        <button @click="downloadPDF"
          class="ml-auto px-4 py-1.5 border border-gray-300 text-sm rounded-lg hover:bg-gray-50 text-gray-700">
          Download PDF
        </button>
      </div>

      <p class="text-xs text-blue-600 bg-blue-50 rounded-lg px-3 py-2">
        Click any time cell to edit it directly. Red values indicate pending correction requests.
      </p>

      <!-- DTR Table -->
      <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
              <th v-for="col in columns" :key="col"
                class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase">
                {{ colLabels[col] }}
              </th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase">OT In</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase">OT Out</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Tardy</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase">UT</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase">OT</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Remarks</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="rec in records" :key="rec.id" class="hover:bg-blue-50">
              <td class="px-3 py-2 font-medium text-gray-800 whitespace-nowrap text-xs">{{ rec.attDate }}</td>

              <!-- Editable time columns -->
              <td v-for="col in columns" :key="col" class="px-1 py-1 text-center">
                <div v-if="isEditing(rec.id, col)" class="flex items-center gap-1">
                  <input v-model="editForm.time" type="time"
                    class="border border-blue-400 rounded px-1 py-0.5 text-xs w-24 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    @keydown.enter="saveEdit(rec.id)"
                    @keydown.escape="cancelEdit"
                    autofocus />
                  <button @click="saveEdit(rec.id)" class="text-green-600 hover:text-green-800 text-xs font-bold">✓</button>
                  <button @click="cancelEdit" class="text-gray-400 hover:text-gray-600 text-xs">✕</button>
                </div>
                <button v-else
                  @click="startEdit(rec.id, col, rec[col])"
                  :class="[
                    'text-xs px-2 py-1 rounded w-full hover:bg-blue-100 transition-colors',
                    requestFor(rec) ? 'text-red-600 font-medium' : 'text-gray-700',
                  ]">
                  {{ formatTime(rec[col]) }}
                </button>
              </td>

              <td class="px-3 py-2 text-center text-xs text-purple-600">{{ rec.OTIn }}</td>
              <td class="px-3 py-2 text-center text-xs text-purple-600">{{ rec.OTOut }}</td>
              <td class="px-3 py-2 text-center text-xs text-red-600">{{ formatMins(rec.Tardiness) }}</td>
              <td class="px-3 py-2 text-center text-xs text-orange-600">{{ formatMins(rec.undertime) }}</td>
              <td class="px-3 py-2 text-center text-xs text-green-600">{{ formatMins(rec.OT) }}</td>
              <td class="px-3 py-2 text-center text-xs">{{ rec.remarks }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pending Requests -->
      <div v-if="requests.length > 0" class="bg-white rounded-xl shadow p-4">
        <h3 class="text-sm font-semibold text-gray-800 mb-3">Pending Correction Requests</h3>
        <table class="min-w-full text-xs">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-3 py-2 text-left">Date</th>
              <th class="px-3 py-2 text-center">AM In</th>
              <th class="px-3 py-2 text-center">AM Out</th>
              <th class="px-3 py-2 text-center">PM In</th>
              <th class="px-3 py-2 text-center">PM Out</th>
              <th class="px-3 py-2 text-left">Remarks</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="req in requests" :key="req.id" class="bg-amber-50">
              <td class="px-3 py-2">{{ req.attDate }}</td>
              <td class="px-3 py-2 text-center">{{ formatTime(req.StartTime1) }}</td>
              <td class="px-3 py-2 text-center">{{ formatTime(req.StartTime2) }}</td>
              <td class="px-3 py-2 text-center">{{ formatTime(req.StartTime3) }}</td>
              <td class="px-3 py-2 text-center">{{ formatTime(req.StartTime4) }}</td>
              <td class="px-3 py-2">{{ req.remarks }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
