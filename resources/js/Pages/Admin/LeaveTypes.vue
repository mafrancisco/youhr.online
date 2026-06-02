<script setup>
import { ref, computed, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Modal, PrimaryButton, FlashMessage, DataTable, FormInput, SearchInput, Pagination, PageHeader, ConfirmDialog } from '@/Components'

const props = defineProps({ types: Array })

const columns = [
  { key: 'id',          label: 'ID',          cellClass: 'text-gray-500 w-16' },
  { key: 'leave_type',  label: 'Leave Type',  cellClass: 'font-medium text-gray-900' },
  { key: 'acronym',     label: 'Acronym',     cellClass: 'font-mono text-gray-600' },
  { key: 'description', label: 'Description', cellClass: 'text-gray-500 text-xs' },
]

const modalMode  = ref(null)
const editTarget = ref(null)
const confirmState = ref({ show: false, title: '', message: '', action: null })

const form = useForm({
  leave_type:  '',
  description: '',
  acronym:     '',
})

function openAdd() {
  form.reset()
  form.clearErrors()
  editTarget.value = null
  modalMode.value = 'add'
}

function openEdit(type) {
  form.reset()
  form.clearErrors()
  form.leave_type  = type.leave_type
  form.description = type.description || ''
  form.acronym     = type.acronym || ''
  editTarget.value = type
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
    form.post('/admin/leave-types', { onSuccess: closeModal })
  } else {
    form.put(`/admin/leave-types/${editTarget.value.id}`, { onSuccess: closeModal })
  }
}

function destroy(type) {
  confirmState.value = {
    show: true,
    title: 'Delete Leave Type',
    message: `Are you sure you want to delete "${type.leave_type}"? Existing leave records using this type may be affected.`,
    action: () => router.delete(`/admin/leave-types/${type.id}`),
  }
}

function onConfirm() {
  confirmState.value.action?.()
  confirmState.value.show = false
}

const search      = ref('')
const perPage     = ref(15)
const currentPage = ref(1)
const PER_PAGE_OPTIONS = [10, 15, 25, 50]

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.types
  return props.types.filter(t =>
    (t.leave_type ?? '').toLowerCase().includes(q) ||
    (t.acronym ?? '').toLowerCase().includes(q)
  )
})
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage.value)))
const paginated  = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return filtered.value.slice(start, start + perPage.value)
})
watch([search, perPage], () => { currentPage.value = 1 })
</script>

<template>
  <AppLayout title="Leave Types">
    <FlashMessage />

    <PageHeader title="Leave Types" :subtitle="`${filtered.length} type${filtered.length !== 1 ? 's' : ''}`">
      <PrimaryButton @click="openAdd">+ Add Leave Type</PrimaryButton>
    </PageHeader>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
      <SearchInput v-model="search" placeholder="Search by name or acronym…" class="w-full sm:w-72" />
      <div class="flex items-center gap-2 text-xs text-gray-500">
        <span>Show</span>
        <select v-model="perPage" class="border border-gray-300 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400">
          <option v-for="n in PER_PAGE_OPTIONS" :key="n" :value="n">{{ n }}</option>
        </select>
        <span>per page</span>
      </div>
    </div>

    <DataTable :columns="columns" :rows="paginated">
      <template #actions="{ row }">
        <button @click="openEdit(row)" class="text-blue-600 hover:underline text-xs mr-3">Edit</button>
        <button @click="destroy(row)" class="text-red-500 hover:underline text-xs">Delete</button>
      </template>
      <template #empty>No leave types yet. Add one to get started.</template>
    </DataTable>

    <div class="mt-4">
      <Pagination :currentPage="currentPage" :totalPages="totalPages" :totalItems="filtered.length" @update:currentPage="currentPage = $event" />
    </div>

    <!-- Add / Edit Modal -->
    <Modal :show="modalMode !== null" size="md" @close="closeModal">
      <template #header>{{ modalMode === 'add' ? 'Add Leave Type' : 'Edit Leave Type' }}</template>
      <template #body>
        <form @submit.prevent="submit" id="ltype-form" class="space-y-4">
          <FormInput label="Leave Type Name" v-model="form.leave_type" :error="form.errors.leave_type"
            placeholder="e.g. Vacation Leave" required />
          <div class="grid grid-cols-2 gap-4">
            <FormInput label="Acronym" v-model="form.acronym" :error="form.errors.acronym"
              placeholder="e.g. VL" />
            <FormInput label="Description" v-model="form.description" :error="form.errors.description"
              placeholder="Optional description" />
          </div>
        </form>
      </template>
      <template #footer>
        <PrimaryButton variant="ghost" @click="closeModal">Cancel</PrimaryButton>
        <PrimaryButton type="submit" form="ltype-form" :loading="form.processing">
          {{ modalMode === 'add' ? 'Add Leave Type' : 'Save Changes' }}
        </PrimaryButton>
      </template>
    </Modal>

    <ConfirmDialog :show="confirmState.show" :title="confirmState.title" :message="confirmState.message"
      @confirm="onConfirm" @cancel="confirmState.show = false" />
  </AppLayout>
</template>
