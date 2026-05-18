<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import FlashMessage from '@/Components/FlashMessage.vue'

const importForm = useForm({
  files:      [],
  start_date: '',
  end_date:   '',
  emp_status: '1',
})
const fileNames = ref([])

const computeForm = useForm({
  start_date: '',
  end_date:   '',
})

function onFileChange(e) {
  const selected = Array.from(e.target.files)
  if (!selected.length) return
  importForm.files = selected
  fileNames.value = selected.map(f => f.name)
}

function removeFile(index) {
  const updated = [...importForm.files]
  updated.splice(index, 1)
  importForm.files = updated
  fileNames.value.splice(index, 1)
}

function submitImport() {
  importForm.post('/attendance/import', {
    forceFormData: true,
    onSuccess: () => {
      importForm.reset()
      fileNames.value = []
    },
  })
}

function submitCompute() {
  computeForm.post('/attendance/compute', {
    onSuccess: () => {
      computeForm.reset()
    },
  })
}
</script>

<template>
  <AppLayout title="Import DTR">
    <FlashMessage />

    <div class="max-w-2xl space-y-6">

      <!-- Import Section -->
      <div class="bg-white rounded-xl shadow p-6">
        <h2 class="text-base font-semibold text-gray-800 mb-1">Step 1 — Upload Attendance Files</h2>
        <p class="text-sm text-gray-500 mb-5">
          Upload one or more biometric <span class="font-mono text-xs bg-gray-100 px-1 rounded">.DAT</span> or CSV files
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
            <label class="block text-xs font-medium text-gray-700 mb-2">Attendance Files (.DAT, .CSV, or .TXT)</label>
            <div
              class="flex items-center gap-3 border-2 border-dashed rounded-lg px-4 py-5 cursor-pointer hover:border-blue-400 transition-colors"
              :class="fileNames.length ? 'border-blue-400 bg-blue-50' : 'border-gray-300'"
              @click="$refs.fileInput.click()">
              <svg class="w-8 h-8 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              <div>
                <p class="text-sm font-medium text-gray-700">
                  {{ fileNames.length ? `${fileNames.length} file${fileNames.length > 1 ? 's' : ''} selected` : 'Click to select files' }}
                </p>
                <p class="text-xs text-gray-400">DAT, CSV, or TXT — select multiple files at once</p>
              </div>
            </div>
            <input ref="fileInput" type="file" accept=".dat,.csv,.txt" multiple class="hidden" @change="onFileChange" />
            <p v-if="importForm.errors.files" class="text-xs text-red-500 mt-1">{{ importForm.errors.files }}</p>
            <p v-if="importForm.errors['files.0']" class="text-xs text-red-500 mt-1">{{ importForm.errors['files.0'] }}</p>
          </div>

          <!-- File list -->
          <div v-if="fileNames.length" class="space-y-1">
            <div v-for="(name, i) in fileNames" :key="i"
              class="flex items-center justify-between bg-gray-50 rounded-lg px-3 py-2">
              <span class="text-sm text-gray-700 truncate">
                <span class="font-mono text-xs text-gray-500 mr-2">{{ i + 1 }}.</span>
                {{ name }}
              </span>
              <button type="button" @click="removeFile(i)" class="text-red-400 hover:text-red-600 text-xs ml-2">
                ✕
              </button>
            </div>
          </div>

          <PrimaryButton type="submit" :loading="importForm.processing"
            :disabled="!importForm.files.length || !importForm.start_date || !importForm.end_date">
            Import {{ fileNames.length > 1 ? `${fileNames.length} Files` : 'Attendance' }}
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
