<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import FlashMessage from '@/Components/FlashMessage.vue'

defineProps({
  credits:   Array,
  employees: Array,
})

const addModal  = ref(false)
const editTarget = ref(null)

const addForm = useForm({ badgeID: '' })

const editForm = useForm({
  vl:      '',
  sl:      '',
  ot:      '',
  service: '',
})

function openEdit(credit) {
  editForm.reset()
  editForm.clearErrors()
  editForm.vl      = credit.vl
  editForm.sl      = credit.sl
  editForm.ot      = credit.ot
  editForm.service = credit.service
  editTarget.value = credit
}

function closeEdit() {
  editTarget.value = null
  editForm.reset()
  editForm.clearErrors()
}

function submitAdd() {
  addForm.post('/credits', {
    onSuccess: () => { addModal.value = false; addForm.reset() },
  })
}

function submitEdit() {
  editForm.put(`/credits/${editTarget.value.badgeID}`, { onSuccess: closeEdit })
}
</script>

<template>
  <AppLayout title="Leave Credits">
    <FlashMessage />

    <div class="flex items-center justify-between mb-5">
      <h2 class="text-base font-semibold text-gray-700">Leave Credits ({{ credits.length }})</h2>
      <PrimaryButton @click="addModal = true">+ Add Employee</PrimaryButton>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-4 py-3 text-left">Badge ID</th>
            <th class="px-4 py-3 text-left">Name</th>
            <th class="px-4 py-3 text-right">Vacation Leave</th>
            <th class="px-4 py-3 text-right">Sick Leave</th>
            <th class="px-4 py-3 text-right">OT Credits</th>
            <th class="px-4 py-3 text-right">Service Credits</th>
            <th class="px-4 py-3 text-left">Updated</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="c in credits" :key="c.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-gray-600">{{ c.badgeID }}</td>
            <td class="px-4 py-3 font-medium text-gray-900">{{ c.empName }}</td>
            <td class="px-4 py-3 text-right text-gray-700">{{ c.vl }}</td>
            <td class="px-4 py-3 text-right text-gray-700">{{ c.sl }}</td>
            <td class="px-4 py-3 text-right text-gray-700">{{ c.ot }}</td>
            <td class="px-4 py-3 text-right text-gray-700">{{ c.service }}</td>
            <td class="px-4 py-3 text-gray-400 text-xs">{{ c.dateupdated }}</td>
            <td class="px-4 py-3 text-right">
              <button @click="openEdit(c)" class="text-blue-600 hover:underline text-xs">Edit</button>
            </td>
          </tr>
          <tr v-if="!credits.length">
            <td colspan="8" class="px-4 py-8 text-center text-gray-400">No leave credit records yet.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add employee modal -->
    <Modal :show="addModal" size="sm" @close="addModal = false">
      <template #header>Add Leave Credit Record</template>
      <template #body>
        <form @submit.prevent="submitAdd" id="add-form" class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Employee</label>
            <select v-model="addForm.badgeID"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
              :class="addForm.errors.badgeID ? 'border-red-400' : 'border-gray-300'">
              <option value="">— select —</option>
              <option v-for="e in employees" :key="e.badgeID" :value="e.badgeID">
                {{ e.empName }}
              </option>
            </select>
            <p v-if="addForm.errors.badgeID" class="text-xs text-red-500 mt-1">{{ addForm.errors.badgeID }}</p>
          </div>
        </form>
      </template>
      <template #footer>
        <PrimaryButton variant="ghost" @click="addModal = false">Cancel</PrimaryButton>
        <PrimaryButton type="submit" form="add-form" :loading="addForm.processing">Add</PrimaryButton>
      </template>
    </Modal>

    <!-- Edit modal -->
    <Modal :show="!!editTarget" size="sm" @close="closeEdit">
      <template #header>Edit Credits — {{ editTarget?.empName }}</template>
      <template #body>
        <form @submit.prevent="submitEdit" id="edit-form" class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Vacation Leave</label>
            <input v-model="editForm.vl" type="number" min="0" step="0.001"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Sick Leave</label>
            <input v-model="editForm.sl" type="number" min="0" step="0.001"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">OT Credits</label>
            <input v-model="editForm.ot" type="number" min="0" step="0.001"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Service Credits</label>
            <input v-model="editForm.service" type="number" min="0" step="0.001"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
          </div>
        </form>
      </template>
      <template #footer>
        <PrimaryButton variant="ghost" @click="closeEdit">Cancel</PrimaryButton>
        <PrimaryButton type="submit" form="edit-form" :loading="editForm.processing">Save</PrimaryButton>
      </template>
    </Modal>
  </AppLayout>
</template>
