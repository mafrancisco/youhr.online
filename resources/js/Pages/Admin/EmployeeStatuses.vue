<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Modal, PrimaryButton, FlashMessage, DataTable, FormInput, PageHeader, ConfirmDialog } from '@/Components'

defineProps({ statuses: Array })

const columns = [
  { key: 'id',          label: 'ID',          cellClass: 'text-gray-500 w-16' },
  { key: 'description', label: 'Description', cellClass: 'font-medium text-gray-900' },
]

const modalMode  = ref(null)
const editTarget = ref(null)
const confirmState = ref({ show: false, title: '', message: '', action: null })

const form = useForm({ description: '' })

function openAdd() {
  form.reset()
  form.clearErrors()
  editTarget.value = null
  modalMode.value = 'add'
}

function openEdit(status) {
  form.reset()
  form.clearErrors()
  form.description = status.description
  editTarget.value = status
  modalMode.value = 'edit'
}

function closeModal() {
  modalMode.value = null
  editTarget.value = null
  form.reset()
  form.clearErrors()
}

function submit() {
  if (modalMode.value === 'add') {
    form.post('/admin/employee-statuses', { onSuccess: closeModal })
  } else {
    form.put(`/admin/employee-statuses/${editTarget.value.id}`, { onSuccess: closeModal })
  }
}

function destroy(status) {
  confirmState.value = {
    show: true,
    title: 'Delete Status',
    message: `Are you sure you want to delete "${status.description}"? Employees using this status may be affected.`,
    action: () => router.delete(`/admin/employee-statuses/${status.id}`),
  }
}

function onConfirm() {
  confirmState.value.action?.()
  confirmState.value.show = false
}
</script>

<template>
  <AppLayout title="Employee Statuses">
    <FlashMessage />

    <PageHeader title="Employee Statuses" :subtitle="`(${statuses.length})`">
      <PrimaryButton @click="openAdd">+ Add Status</PrimaryButton>
    </PageHeader>

    <DataTable :columns="columns" :rows="statuses">
      <template #actions="{ row }">
        <button @click="openEdit(row)" class="text-blue-600 hover:underline text-xs mr-3">Edit</button>
        <button @click="destroy(row)" class="text-red-500 hover:underline text-xs">Delete</button>
      </template>
      <template #empty>No employee statuses yet. Add one to get started.</template>
    </DataTable>

    <!-- Add / Edit Modal -->
    <Modal :show="modalMode !== null" size="sm" @close="closeModal">
      <template #header>{{ modalMode === 'add' ? 'Add Status' : 'Edit Status' }}</template>
      <template #body>
        <form @submit.prevent="submit" id="status-form" class="space-y-4">
          <FormInput label="Description" v-model="form.description" :error="form.errors.description"
            placeholder="e.g. Regular, Contractual, Job Order" required />
        </form>
      </template>
      <template #footer>
        <PrimaryButton variant="ghost" @click="closeModal">Cancel</PrimaryButton>
        <PrimaryButton type="submit" form="status-form" :loading="form.processing">
          {{ modalMode === 'add' ? 'Add Status' : 'Save Changes' }}
        </PrimaryButton>
      </template>
    </Modal>

    <ConfirmDialog :show="confirmState.show" :title="confirmState.title" :message="confirmState.message"
      @confirm="onConfirm" @cancel="confirmState.show = false" />
  </AppLayout>
</template>
