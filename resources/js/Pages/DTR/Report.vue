<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import FlashMessage from '@/Components/FlashMessage.vue'

const props = defineProps({
  start_date: String,
  end_date:   String,
  employees:  Array,
})

const startDate     = ref(props.start_date)
const endDate       = ref(props.end_date)
const selected      = ref([])
const bulkEmpStatus = ref('1')

const allChecked = computed(() =>
  props.employees.length > 0 && selected.value.length === props.employees.length
)

function toggleAll() {
  selected.value = allChecked.value ? [] : props.employees.map(e => e.badgeID)
}

function downloadIndividual(badgeID) {
  if (!startDate.value || !endDate.value) return
  window.open(`/dtr/download?badge=${badgeID}&start_date=${startDate.value}&end_date=${endDate.value}`, '_blank')
}

function downloadSelectedIndividual() {
  selected.value.forEach(b => downloadIndividual(b))
}

function downloadBulk() {
  if (!startDate.value || !endDate.value) return

  const form = document.createElement('form')
  form.method = 'POST'
  form.action = '/reports/dtr/download'
  form.target = '_blank'

  const fields = {
    _token:     document.querySelector('meta[name="csrf-token"]')?.content || '',
    start_date: startDate.value,
    end_date:   endDate.value,
    emp_status: bulkEmpStatus.value,
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

    <div class="flex flex-wrap items-end gap-4 mb-5">
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Start Date</label>
        <input v-model="startDate" type="date"
          class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
      </div>

      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">End Date</label>
        <input v-model="endDate" type="date" :min="startDate"
          class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
      </div>

      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Employee Type</label>
        <select v-model="bulkEmpStatus"
          class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
          <option value="1">Regular</option>
          <option value="2">Contractual</option>
        </select>
      </div>

      <div class="flex gap-2 ml-auto">
        <button @click="downloadBulk" :disabled="!startDate || !endDate"
          class="px-4 py-2 bg-blue-700 text-white text-sm rounded-lg hover:bg-blue-800 disabled:opacity-50">
          Bulk PDF (all employees)
        </button>
        <PrimaryButton :disabled="!selected.length || !startDate || !endDate" @click="downloadSelectedIndividual">
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
              <button @click="downloadIndividual(emp.badgeID)" :disabled="!startDate || !endDate"
                class="text-blue-600 hover:underline text-xs disabled:opacity-40 disabled:cursor-not-allowed">
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
