<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Modal, PrimaryButton, FlashMessage, DataTable, FormInput, SelectInput, PageHeader } from '@/Components'

defineProps({
  credits:   Array,
  employees: Array,
})

const columns = [
  { key: 'badgeID',      label: 'Badge ID',        cellClass: 'font-mono text-gray-600' },
  { key: 'empName',      label: 'Name',            cellClass: 'font-medium text-gray-900' },
  { key: 'vl',           label: 'Vacation Leave',  headerClass: 'text-right', cellClass: 'text-right text-gray-700' },
  { key: 'sl',           label: 'Sick Leave',      headerClass: 'text-right', cellClass: 'text-right text-gray-700' },
  { key: 'ot',           label: 'OT Credits',      headerClass: 'text-right', cellClass: 'text-right text-gray-700' },
  { key: 'service',      label: 'Service Credits', headerClass: 'text-right', cellClass: 'text-right text-gray-700' },
  { key: 'dateupdated',  label: 'Updated',         cellClass: 'text-gray-400 text-xs' },
]

const addModal   = ref(false)
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

    <PageHeader title="Leave Credits" :subtitle="`(${credits.length})`">
      <PrimaryButton @click="addModal = true">+ Add Employee</PrimaryButton>
    </PageHeader>

    <DataTable :columns="columns" :rows="credits">
      <template #actions="{ row }">
        <button @click="openEdit(row)" class="text-blue-600 hover:underline text-xs">Edit</button>
      </template>
      <template #empty>No leave credit records yet.</template>
    </DataTable>

    <!-- Add employee modal -->
    <Modal :show="addModal" size="sm" @close="addModal = false">
      <template #header>Add Leave Credit Record</template>
      <template #body>
        <form @submit.prevent="submitAdd" id="add-form" class="space-y-3">
          <SelectInput label="Employee" v-model="addForm.badgeID" :error="addForm.errors.badgeID"
            :options="employees.map(e => ({ value: e.badgeID, label: e.empName }))" />
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
          <FormInput label="Vacation Leave" v-model="editForm.vl" type="number" />
          <FormInput label="Sick Leave" v-model="editForm.sl" type="number" />
          <FormInput label="OT Credits" v-model="editForm.ot" type="number" />
          <FormInput label="Service Credits" v-model="editForm.service" type="number" />
        </form>
      </template>
      <template #footer>
        <PrimaryButton variant="ghost" @click="closeEdit">Cancel</PrimaryButton>
        <PrimaryButton type="submit" form="edit-form" :loading="editForm.processing">Save</PrimaryButton>
      </template>
    </Modal>
  </AppLayout>
</template>
