<script setup>
import { ref, computed } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import FlashMessage from '@/Components/FlashMessage.vue'

const props = defineProps({
  month:     String,
  employees: Array,
})

const selectedMonth = ref(props.month)
const selected      = ref([])
const allChecked    = computed(() => props.employees.length > 0 && selected.value.length === props.employees.length)

// Bulk download form
const bulkForm = useForm({
  start_date: '',
  end_date:   '',
  emp_status: '1',
})

// Derive start/end from the selected month picker
const monthStart = computed(() => {
  if (!selectedMonth.value) return ''
  return selectedMonth.value + '-01'
})
const monthEnd = computed(() => {
  if (!selectedMonth.value) return ''
  const [y, m] = selectedMonth.value.split('-')
  const last = new Date(y, m, 0).getDate()
  return `${y}-${m}-${String(last).padStart(2, '0')}`
})

function toggleAll() {
  selected.value = allChecked.value ? [] : props.employees.map(e => e.badgeID)
}

function changeMonth() {
  router.get('/reports/dtr', { month: selectedMonth.value }, { preserveState: true })
}

function downloadIndividual(badgeID) {
  const url = `/dtr/download?badge=${badgeID}&start_date=${monthStart.value}&end_date=${monthEnd.value}`
  window.open(url, '_blank')
}

function downloadSelectedIndividual() {
  selected.value.forEach(b => downloadIndividual(b))
}

function downloadBulk() {
  bulkForm.start_date = monthStart.value
  bulkForm.end_date   = monthEnd.value
  // Use a form submission to trigger file download
  const form = document.createElement('form')
  form.method = 'POST'
  form.action = '/reports/dtr/download'
  form.target = '_blank'

  const fields = {
    _token:     document.querySelector('meta[name="csrf-token"]')?.content || '',
    start_date: monthStart.value,
    end_date:   monthEnd.value,
    emp_status: bulkForm.emp_status,
  }

  for (const [name, value] of Object.entries(fields)) {
    const input = document.createElement('input')
    input.type  = 'hidden'
    input.name  = name
    input.value = value
    form.appendChild(input)
  }

  document.body.appendChild(form)
  form.submit()
  document.body.removeChild(form)
}
</script>

<template>
  <AppLayout title="DTR Reports">
    <FlashMessage />

    <!-- Controls -->
    <div class="flex flex-wrap items-end gap-4 mb-5">
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Period (Month)</label>
        <input v-model="selectedMonth" type="month"
          class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
          @change="changeMonth" />
      </div>

      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Employee Type</label>
        <select v-model="bulkForm.emp_status"
          class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
          <option value="1">Regular</option>
          <option value="2">Contractual</option>
        </select>
      </div>

      <div class="flex gap-2 ml-auto">
        <button @click="downloadBulk" :disabled="!selectedMonth"
          class="px-4 py-2 bg-blue-700 text-white text-sm rounded-lg hover:bg-blue-800 disabled:opacity-50">
          Bulk PDF (all employees)
        </button>
        <PrimaryButton :disabled="!selected.length" @click="downloadSelectedIndividual">
          Individual PDFs ({{ selected.length }})
        </PrimaryButton>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-4 py-3 text-left w-10">
              <input type="checkbox" :checked="allChecked" @change="toggleAll"
                class="rounded border-gray-300 text-blue-600 focus:ring-blue-400" />
            </th>
            <th class="px-4 py-3 text-left">Badge ID</th>
            <th class="px-4 py-3 text-left">Name</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="emp in employees" :key="emp.badgeID" class="hover:bg-gray-50">
            <td class="px-4 py-3">
              <input type="checkbox" :value="emp.badgeID" v-model="selected"
                class="rounded border-gray-300 text-blue-600 focus:ring-blue-400" />
            </td>
            <td class="px-4 py-3 font-mono text-gray-600">{{ emp.badgeID }}</td>
            <td class="px-4 py-3 font-medium text-gray-900">{{ emp.empName }}</td>
            <td class="px-4 py-3 text-right">
              <button @click="downloadIndividual(emp.badgeID)"
                class="text-blue-600 hover:underline text-xs">
                Download PDF
              </button>
            </td>
          </tr>
          <tr v-if="!employees.length">
            <td colspan="4" class="px-4 py-8 text-center text-gray-400">No active employees.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </AppLayout>
</template>
