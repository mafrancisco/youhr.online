<script setup>
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import FlashMessage from '@/Components/FlashMessage.vue'

const props = defineProps({
  active:    Array,
  inactive:  Array,
  statuses:  Array,
  schedules: Array,
  heads:     Array,
})

const showInactive = ref(false)
const modalMode    = ref(null) // 'add' | 'edit'
const editTarget   = ref(null)

const form = useForm({
  badgeID:   '',
  empName:   '',
  email:     '',
  empStatus: '',
  empDesig:  '',
  empHead:   '',
  schedule:  '',
})

function openAdd() {
  form.reset()
  form.clearErrors()
  editTarget.value = null
  modalMode.value  = 'add'
}

function openEdit(emp) {
  form.reset()
  form.clearErrors()
  form.empName   = emp.empName
  form.email     = emp.email
  form.empStatus = emp.empStatus
  form.empDesig  = emp.empDesig
  form.empHead   = emp.empHead
  form.schedule  = emp.schedule
  editTarget.value = emp
  modalMode.value  = 'edit'
}

function closeModal() {
  modalMode.value  = null
  editTarget.value = null
  form.reset()
  form.clearErrors()
}

function submit() {
  if (modalMode.value === 'add') {
    form.post('/employees', { onSuccess: closeModal })
  } else {
    form.put(`/employees/${editTarget.value.id}`, { onSuccess: closeModal })
  }
}

function deactivate(emp) {
  if (!confirm(`Deactivate ${emp.empName}?`)) return
  useForm({}).put(`/employees/${emp.id}/deactivate`)
}

function reactivate(emp) {
  if (!confirm(`Reactivate ${emp.empName}?`)) return
  useForm({}).put(`/employees/${emp.id}/reactivate`)
}
</script>

<template>
  <AppLayout title="Employees">
    <FlashMessage />

    <div class="flex items-center justify-between mb-5">
      <h2 class="text-base font-semibold text-gray-700">Active Employees ({{ active.length }})</h2>
      <PrimaryButton @click="openAdd">+ Add Employee</PrimaryButton>
    </div>

    <!-- Active table -->
    <div class="bg-white rounded-xl shadow overflow-hidden mb-6">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-4 py-3 text-left">Badge ID</th>
            <th class="px-4 py-3 text-left">Name</th>
            <th class="px-4 py-3 text-left">Email</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3 text-left">Designation</th>
            <th class="px-4 py-3 text-left">Schedule</th>
            <th class="px-4 py-3 text-left">Head</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="emp in active" :key="emp.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-gray-600">{{ emp.badgeID }}</td>
            <td class="px-4 py-3 font-medium text-gray-900">{{ emp.empName }}</td>
            <td class="px-4 py-3 text-gray-500">{{ emp.email }}</td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 rounded-full text-xs bg-blue-100 text-blue-700">{{ emp.statusLabel ?? emp.empStatus }}</span>
            </td>
            <td class="px-4 py-3 text-gray-600">{{ emp.empDesig }}</td>
            <td class="px-4 py-3 text-gray-600">{{ emp.scheduleName ?? emp.schedule }}</td>
            <td class="px-4 py-3 text-gray-600">{{ emp.empHead }}</td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <button @click="openEdit(emp)"
                class="text-blue-600 hover:underline text-xs mr-3">Edit</button>
              <button @click="deactivate(emp)"
                class="text-red-500 hover:underline text-xs">Deactivate</button>
            </td>
          </tr>
          <tr v-if="!active.length">
            <td colspan="8" class="px-4 py-8 text-center text-gray-400">No active employees.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Inactive toggle -->
    <button @click="showInactive = !showInactive"
      class="text-sm text-gray-500 hover:text-gray-700 mb-3 flex items-center gap-1">
      <span>{{ showInactive ? '▾' : '▸' }}</span>
      Inactive Employees ({{ inactive.length }})
    </button>

    <div v-if="showInactive" class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-4 py-3 text-left">Badge ID</th>
            <th class="px-4 py-3 text-left">Name</th>
            <th class="px-4 py-3 text-left">Deactivated</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="emp in inactive" :key="emp.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-gray-500">{{ emp.badgeID }}</td>
            <td class="px-4 py-3 text-gray-700">{{ emp.empName }}</td>
            <td class="px-4 py-3 text-gray-400">{{ emp.date_deact }}</td>
            <td class="px-4 py-3 text-right">
              <button @click="reactivate(emp)" class="text-green-600 hover:underline text-xs">Reactivate</button>
            </td>
          </tr>
          <tr v-if="!inactive.length">
            <td colspan="4" class="px-4 py-8 text-center text-gray-400">No inactive employees.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add / Edit modal -->
    <Modal :show="modalMode !== null" size="lg" @close="closeModal">
      <template #header>{{ modalMode === 'add' ? 'Add Employee' : 'Edit Employee' }}</template>

      <template #body>
        <form @submit.prevent="submit" id="emp-form" class="grid grid-cols-2 gap-4">
          <div v-if="modalMode === 'add'" class="col-span-2">
            <label class="block text-xs font-medium text-gray-700 mb-1">Badge ID</label>
            <input v-model="form.badgeID" type="text"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
              :class="form.errors.badgeID ? 'border-red-400' : 'border-gray-300'" />
            <p v-if="form.errors.badgeID" class="text-xs text-red-500 mt-1">{{ form.errors.badgeID }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Full Name</label>
            <input v-model="form.empName" type="text"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
              :class="form.errors.empName ? 'border-red-400' : 'border-gray-300'" />
            <p v-if="form.errors.empName" class="text-xs text-red-500 mt-1">{{ form.errors.empName }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Email</label>
            <input v-model="form.email" type="email"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
              :class="form.errors.email ? 'border-red-400' : 'border-gray-300'" />
            <p v-if="form.errors.email" class="text-xs text-red-500 mt-1">{{ form.errors.email }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
            <select v-model="form.empStatus"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
              :class="form.errors.empStatus ? 'border-red-400' : 'border-gray-300'">
              <option value="">— select —</option>
              <option v-for="s in statuses" :key="s.id" :value="s.id">{{ s.description }}</option>
            </select>
            <p v-if="form.errors.empStatus" class="text-xs text-red-500 mt-1">{{ form.errors.empStatus }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Designation</label>
            <input v-model="form.empDesig" type="text"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
              :class="form.errors.empDesig ? 'border-red-400' : 'border-gray-300'" />
            <p v-if="form.errors.empDesig" class="text-xs text-red-500 mt-1">{{ form.errors.empDesig }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Schedule</label>
            <select v-model="form.schedule"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
              :class="form.errors.schedule ? 'border-red-400' : 'border-gray-300'">
              <option value="">— select —</option>
              <option v-for="s in schedules" :key="s.id" :value="s.id">{{ s.schedulename }}</option>
            </select>
            <p v-if="form.errors.schedule" class="text-xs text-red-500 mt-1">{{ form.errors.schedule }}</p>
          </div>

          <div class="col-span-2">
            <label class="block text-xs font-medium text-gray-700 mb-1">Head / Department</label>
            <select v-model="form.empHead"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
              :class="form.errors.empHead ? 'border-red-400' : 'border-gray-300'">
              <option value="">— select —</option>
              <option v-for="h in heads" :key="h.id" :value="h.headname">{{ h.headname }} — {{ h.headposition }}</option>
            </select>
            <p v-if="form.errors.empHead" class="text-xs text-red-500 mt-1">{{ form.errors.empHead }}</p>
          </div>
        </form>
      </template>

      <template #footer>
        <PrimaryButton variant="ghost" @click="closeModal">Cancel</PrimaryButton>
        <PrimaryButton type="submit" form="emp-form" :loading="form.processing">
          {{ modalMode === 'add' ? 'Add Employee' : 'Save Changes' }}
        </PrimaryButton>
      </template>
    </Modal>
  </AppLayout>
</template>
