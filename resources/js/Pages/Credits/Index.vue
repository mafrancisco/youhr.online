<script setup>
import { ref, computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Modal, PrimaryButton, FlashMessage, DataTable, FormInput, SelectInput, SearchInput, Pagination, PageHeader } from '@/Components'

const props = defineProps({
  credits:   Array,
  employees: Array,
})

const columns = [
  { key: 'badgeID',      label: 'Badge ID',     cellClass: 'font-mono text-gray-600' },
  { key: 'empName',      label: 'Name',         cellClass: 'font-medium text-gray-900' },
  { key: 'vl',           label: 'VL',           headerClass: 'text-right', cellClass: 'text-right text-gray-700' },
  { key: 'sl',           label: 'SL',           headerClass: 'text-right', cellClass: 'text-right text-gray-700' },
  { key: 'maternity',    label: 'Maternity',    headerClass: 'text-right', cellClass: 'text-right text-gray-700' },
  { key: 'paternity',    label: 'Paternity',    headerClass: 'text-right', cellClass: 'text-right text-gray-700' },
  { key: 'spl',          label: 'SPL',          headerClass: 'text-right', cellClass: 'text-right text-gray-700' },
  { key: 'forced',       label: 'Forced',       headerClass: 'text-right', cellClass: 'text-right text-gray-700' },
  { key: 'wellness',     label: 'Wellness',     headerClass: 'text-right', cellClass: 'text-right text-gray-700' },
  { key: 'ot',           label: 'OT',           headerClass: 'text-right', cellClass: 'text-right text-gray-700' },
  { key: 'service',      label: 'Service',      headerClass: 'text-right', cellClass: 'text-right text-gray-700' },
  { key: 'dateupdated',  label: 'Updated',      cellClass: 'text-gray-400 text-xs' },
]

const addModal   = ref(false)
const editTarget = ref(null)

const addForm = useForm({ badgeID: '' })

const editForm = useForm({
  vl:        '',
  sl:        '',
  maternity: '',
  paternity: '',
  spl:       '',
  forced:    '',
  wellness:  '',
  ot:        '',
  service:   '',
})

function openEdit(credit) {
  editForm.reset()
  editForm.clearErrors()
  editForm.vl        = credit.vl
  editForm.sl        = credit.sl
  editForm.maternity = credit.maternity
  editForm.paternity = credit.paternity
  editForm.spl       = credit.spl
  editForm.forced    = credit.forced
  editForm.wellness  = credit.wellness
  editForm.ot        = credit.ot
  editForm.service   = credit.service
  editTarget.value   = credit
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

const search      = ref('')
const perPage     = ref(15)
const currentPage = ref(1)
const PER_PAGE_OPTIONS = [10, 15, 25, 50]

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.credits
  return props.credits.filter(c =>
    (c.empName ?? '').toLowerCase().includes(q) ||
    (c.badgeID ?? '').toLowerCase().includes(q)
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
  <AppLayout title="Leave Credits">
    <FlashMessage />

    <PageHeader title="Leave Credits" :subtitle="`${filtered.length} record${filtered.length !== 1 ? 's' : ''}`">
      <PrimaryButton @click="addModal = true">+ Add Employee</PrimaryButton>
    </PageHeader>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
      <SearchInput v-model="search" placeholder="Search by name or badge ID…" class="w-full sm:w-72" />
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
        <button @click="openEdit(row)" class="text-blue-600 hover:underline text-xs">Edit</button>
      </template>
      <template #empty>No leave credit records yet.</template>
    </DataTable>

    <div class="mt-4">
      <Pagination :currentPage="currentPage" :totalPages="totalPages" :totalItems="filtered.length" @update:currentPage="currentPage = $event" />
    </div>

    <!-- Add employee modal -->
    <Modal :show="addModal" size="sm" @close="addModal = false">
      <template #header>Add Leave Credit Record</template>
      <template #body>
        <form @submit.prevent="submitAdd" id="add-form" class="space-y-3">
          <SelectInput label="Employee" v-model="addForm.badgeID" :error="addForm.errors.badgeID"
            :options="employees.map(e => ({ value: e.badgeID, label: e.empName }))" />
          <p class="text-xs text-gray-500">Only employees without existing credit records are shown.</p>
        </form>
      </template>
      <template #footer>
        <PrimaryButton variant="ghost" @click="addModal = false">Cancel</PrimaryButton>
        <PrimaryButton type="submit" form="add-form" :loading="addForm.processing">Add</PrimaryButton>
      </template>
    </Modal>

    <!-- Edit modal -->
    <Modal :show="!!editTarget" size="lg" @close="closeEdit">
      <template #header>Edit Credits — {{ editTarget?.empName }}</template>
      <template #body>
        <form @submit.prevent="submitEdit" id="edit-form">
          <p class="text-xs font-medium text-gray-600 mb-3">Earned Leave Credits</p>
          <div class="grid grid-cols-2 gap-3 mb-4">
            <FormInput label="Vacation Leave (VL)" v-model="editForm.vl" type="number" :error="editForm.errors.vl" />
            <FormInput label="Sick Leave (SL)" v-model="editForm.sl" type="number" :error="editForm.errors.sl" />
          </div>

          <p class="text-xs font-medium text-gray-600 mb-3">Special Leave Credits</p>
          <div class="grid grid-cols-3 gap-3 mb-4">
            <FormInput label="Maternity (105 days)" v-model="editForm.maternity" type="number" :error="editForm.errors.maternity" />
            <FormInput label="Paternity (10 days)" v-model="editForm.paternity" type="number" :error="editForm.errors.paternity" />
            <FormInput label="SPL (3 days)" v-model="editForm.spl" type="number" :error="editForm.errors.spl" />
            <FormInput label="Forced Leave (5 days)" v-model="editForm.forced" type="number" :error="editForm.errors.forced" />
            <FormInput label="Wellness (5 days)" v-model="editForm.wellness" type="number" :error="editForm.errors.wellness" />
          </div>

          <p class="text-xs font-medium text-gray-600 mb-3">Other Credits</p>
          <div class="grid grid-cols-2 gap-3">
            <FormInput label="OT Credits" v-model="editForm.ot" type="number" :error="editForm.errors.ot" />
            <FormInput label="Service Credits" v-model="editForm.service" type="number" :error="editForm.errors.service" />
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
