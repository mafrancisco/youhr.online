<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import FlashMessage from '@/Components/FlashMessage.vue'

const props = defineProps({
  computationStatus: { type: String, default: 'idle' },
})

const importForm = useForm({
  file:       null,
  start_date: '',
  end_date:   '',
  emp_status: '1',
})
const fileName = ref('')

const computeForm = useForm({
  start_date: '',
  end_date:   '',
})

const status = ref(props.computationStatus)
let pollInterval = null

function startPolling() {
  if (pollInterval) return
  pollInterval = setInterval(async () => {
    try {
      const res = await fetch('/attendance/computation-status')
      const data = await res.json()
      status.value = data.status
      if (data.status === 'completed' || data.status === 'failed' || data.status === 'idle') {
        stopPolling()
      }
    } catch {
      // ignore fetch errors
    }
  }, 3000)
}

function stopPolling() {
  if (pollInterval) {
    clearInterval(pollInterval)
    pollInterval = null
  }
}

onMounted(() => {
  if (status.value === 'queued' || status.value === 'processing') {
    startPolling()
  }
})

onUnmounted(() => stopPolling())

function onFileChange(e) {
  const f = e.target.files[0]
  if (!f) return
  importForm.file = f
  fileName.value  = f.name
}

function submitImport() {
  importForm.post('/attendance/import', {
    forceFormData: true,
    onSuccess: () => {
      importForm.reset()
      fileName.value = ''
      status.value = 'queued'
      startPolling()
    },
  })
}

function submitCompute() {
  computeForm.post('/attendance/compute', {
    onSuccess: () => {
      computeForm.reset()
      status.value = 'queued'
      startPolling()
    },
  })
}
</script>

<template>
  <AppLayout title="Import DTR">
    <FlashMessage />

    <div class="max-w-2xl space-y-6">

      <!-- Computation Status Banner -->
      <div v-if="status === 'queued' || status === 'processing'"
        class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 flex items-center gap-3">
        <svg class="w-5 h-5 text-yellow-500 animate-spin" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <div>
          <p class="text-sm font-medium text-yellow-800">DTR computation in progress...</p>
          <p class="text-xs text-yellow-600">Tardiness, undertime, and overtime are being calculated. This page will update automatically.</p>
        </div>
      </div>

      <div v-if="status === 'completed'"
        class="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center gap-3">
        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <p class="text-sm font-medium text-green-800">DTR computation completed successfully.</p>
      </div>

      <div v-if="status === 'failed'"
        class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-center gap-3">
        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
        <p class="text-sm font-medium text-red-800">DTR computation failed. Please try again or contact support.</p>
      </div>

      <!-- Import Section -->
      <div class="bg-white rounded-xl shadow p-6">
        <h2 class="text-base font-semibold text-gray-800 mb-1">Step 1 — Upload Attendance File</h2>
        <p class="text-sm text-gray-500 mb-5">
          Upload a biometric <span class="font-mono text-xs bg-gray-100 px-1 rounded">.DAT</span> or CSV file
          exported from the biometric machine.
        </p>

        <form @submit.prevent="submitImport" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Start Date</label>
              <input v-model="importForm.start_date" type="date" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
              <p v-if="importForm.errors.start_date" class="text-xs text-red-500 mt-1">{{ importForm.errors.start_date }}</p>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">End Date</label>
              <input v-model="importForm.end_date" type="date" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
              <p v-if="importForm.errors.end_date" class="text-xs text-red-500 mt-1">{{ importForm.errors.end_date }}</p>
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Employee Type</label>
            <select v-model="importForm.emp_status" required
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="1">Regular Employees</option>
              <option value="2">Contractual Employees</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-700 mb-2">Attendance File (.DAT or .CSV)</label>
            <div
              class="flex items-center gap-3 border-2 border-dashed rounded-lg px-4 py-5 cursor-pointer hover:border-blue-400 transition-colors"
              :class="fileName ? 'border-blue-400 bg-blue-50' : 'border-gray-300'"
              @click="$refs.fileInput.click()">
              <svg class="w-8 h-8 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <div>
                <p class="text-sm font-medium text-gray-700">{{ fileName || 'Click to select a file' }}</p>
                <p class="text-xs text-gray-400">DAT, CSV, or TXT</p>
              </div>
            </div>
            <input ref="fileInput" type="file" accept=".dat,.csv,.txt" class="hidden" @change="onFileChange" />
            <p v-if="importForm.errors.file" class="text-xs text-red-500 mt-1">{{ importForm.errors.file }}</p>
          </div>

          <PrimaryButton type="submit" :loading="importForm.processing" :disabled="!importForm.file || !importForm.start_date || !importForm.end_date">
            Import Attendance
          </PrimaryButton>
        </form>
      </div>

      <!-- Recompute Section -->
      <div class="bg-white rounded-xl shadow p-6">
        <h2 class="text-base font-semibold text-gray-800 mb-1">Step 2 — Recompute DTR (Optional)</h2>
        <p class="text-sm text-gray-500 mb-5">
          Re-run tardiness, undertime, and overtime computation for an existing date range
          (e.g. after correcting attendance logs or leave approvals).
        </p>

        <form @submit.prevent="submitCompute" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">Start Date</label>
              <input v-model="computeForm.start_date" type="date" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">End Date</label>
              <input v-model="computeForm.end_date" type="date" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
          </div>

          <PrimaryButton type="submit" :loading="computeForm.processing" :disabled="!computeForm.start_date || !computeForm.end_date">
            Compute DTR
          </PrimaryButton>
        </form>
      </div>

      <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-xs text-blue-700 space-y-1">
        <p class="font-semibold">Biometric DAT format (tab-delimited):</p>
        <pre class="font-mono text-blue-600">10001	2025-04-01 08:02:00	0	0
10001	2025-04-01 17:05:00	0	3</pre>
        <p class="mt-2">AttType: 0=TimeIn, 1=BreakOut, 2=BreakIn, 3=Timeout, 4=OTIn, 5=OTOut</p>
      </div>
    </div>
  </AppLayout>
</template>
