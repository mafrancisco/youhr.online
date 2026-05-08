<script setup>
import { ref, computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Modal, PrimaryButton, FlashMessage, DataTable, FormInput, SelectInput, SearchInput, Pagination, PageHeader, StatusBadge, ConfirmDialog } from '@/Components'

const props = defineProps({
  active:    Array,
  inactive:  Array,
  statuses:  Array,
  schedules: Array,
  heads:     Array,
  divisions: Array,
  units:     Array,
})

const showInactive = ref(false)
const modalMode    = ref(null)
const editTarget   = ref(null)

const search      = ref('')
const perPage     = ref(15)
const currentPage = ref(1)

const PER_PAGE_OPTIONS = [10, 15, 25, 50]

// Confirm dialog state
const confirmState = ref({ show: false, title: '', message: '', action: null })

const searched = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.active
  return props.active.filter(e =>
    e.empName.toLowerCase().includes(q)      ||
    e.badgeID.toLowerCase().includes(q)      ||
    (e.email        ?? '').toLowerCase().includes(q) ||
    (e.empDesig     ?? '').toLowerCase().includes(q) ||
    (e.division_name ?? '').toLowerCase().includes(q) ||
    (e.unit_name    ?? '').toLowerCase().includes(q)
  )
})

const totalPages = computed(() => Math.max(1, Math.ceil(searched.value.length / perPage.value)))

const paginated = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return searched.value.slice(start, start + perPage.value)
})

watch([search, perPage], () => { currentPage.value = 1 })

const activeColumns = [
  { key: 'badgeID',       label: 'Badge ID',    cellClass: 'font-mono text-gray-600' },
  { key: 'empName',       label: 'Name',        cellClass: 'font-medium text-gray-900' },
  { key: 'email',         label: 'Email',       cellClass: 'text-gray-500' },
  { key: 'statusLabel',   label: 'Status' },
  { key: 'empDesig',      label: 'Designation', cellClass: 'text-gray-600' },
  { key: 'scheduleName',  label: 'Schedule',    cellClass: 'text-gray-600' },
  { key: 'empHead',       label: 'Head',        cellClass: 'text-gray-600' },
  { key: 'division_name', label: 'Division',    cellClass: 'text-gray-500 text-xs' },
  { key: 'unit_name',     label: 'Unit',        cellClass: 'text-gray-500 text-xs' },
]

const inactiveColumns = [
  { key: 'badgeID',    label: 'Badge ID',     cellClass: 'font-mono text-gray-500' },
  { key: 'empName',    label: 'Name',         cellClass: 'text-gray-700' },
  { key: 'date_deact', label: 'Deactivated',  cellClass: 'text-gray-400' },
]

const form = useForm({
  badgeID:     '',
  empName:     '',
  email:       '',
  empStatus:   '',
  empDesig:    '',
  empHead:     '',
  schedule:    '',
  division_id: '',
  unit_id:     '',
})

const filteredUnits = computed(() =>
  form.division_id ? props.units.filter(u => u.division_id == form.division_id) : []
)

watch(() => form.division_id, () => { form.unit_id = '' })

function openAdd() {
  form.reset()
  form.clearErrors()
  editTarget.value = null
  modalMode.value  = 'add'
}

function openEdit(emp) {
  form.reset()
  form.clearErrors()
  form.empName     = emp.empName
  form.email       = emp.email
  form.empStatus   = emp.empStatus
  form.empDesig    = emp.empDesig
  form.empHead     = emp.empHead
  form.schedule    = emp.schedule
  form.division_id = emp.division_id ?? ''
  form.unit_id     = emp.unit_id     ?? ''
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
  confirmState.value = {
    show: true,
    title: 'Deactivate Employee',
    message: `Are you sure you want to deactivate ${emp.empName}?`,
    action: () => useForm({}).put(`/employees/${emp.id}/deactivate`),
  }
}

function reactivate(emp) {
  confirmState.value = {
    show: true,
    title: 'Reactivate Employee',
    message: `Are you sure you want to reactivate ${emp.empName}?`,
    action: () => useForm({}).put(`/employees/${emp.id}/reactivate`),
  }
}

function onConfirm() {
  confirmState.value.action?.()
  confirmState.value.show = false
}
</script>

<template>
  <AppLayout title="Employees">
    <FlashMessage />

    <PageHeader title="Active Employees"
      :subtitle="`(${searched.length}${searched.length !== active.length ? ' of ' + active.length : ''})`">
      <SearchInput v-model="search" placeholder="Search name, badge, email…" class="w-60" />
      <select v-model="perPage"
        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
        <option v-for="n in PER_PAGE_OPTIONS" :key="n" :value="n">{{ n }} / page</option>
      </select>
      <PrimaryButton @click="openAdd">+ Add Employee</PrimaryButton>
    </PageHeader>

    <!-- Active table -->
    <DataTable :columns="activeColumns" :rows="paginated" class="mb-3">
      <template #cell-statusLabel="{ row }">
        <StatusBadge :status="row.statusLabel ?? String(row.empStatus)" color="blue" />
      </template>
      <template #actions="{ row }">
        <button @click="openEdit(row)" class="text-blue-600 hover:underline text-xs mr-3">Edit</button>
        <button @click="deactivate(row)" class="text-red-500 hover:underline text-xs">Deactivate</button>
      </template>
      <template #empty>
        {{ search ? 'No employees match your search.' : 'No active employees.' }}
      </template>
    </DataTable>

    <!-- Pagination -->
    <Pagination v-model:currentPage="currentPage" :totalPages="totalPages" :totalItems="searched.length" class="mb-6" />

    <!-- Inactive toggle -->
    <button @click="showInactive = !showInactive"
      class="text-sm text-gray-500 hover:text-gray-700 mb-3 flex items-center gap-1">
      <span>{{ showInactive ? '▾' : '▸' }}</span>
      Inactive Employees ({{ inactive.length }})
    </button>

    <DataTable v-if="showInactive" :columns="inactiveColumns" :rows="inactive">
      <template #actions="{ row }">
        <button @click="reactivate(row)" class="text-green-600 hover:underline text-xs">Reactivate</button>
      </template>
      <template #empty>No inactive employees.</template>
    </DataTable>

    <!-- Add / Edit modal -->
    <Modal :show="modalMode !== null" size="lg" @close="closeModal">
      <template #header>{{ modalMode === 'add' ? 'Add Employee' : 'Edit Employee' }}</template>

      <template #body>
        <form @submit.prevent="submit" id="emp-form" class="grid grid-cols-2 gap-4">
          <div v-if="modalMode === 'add'" class="col-span-2">
            <FormInput label="Badge ID" v-model="form.badgeID" :error="form.errors.badgeID" />
          </div>

          <FormInput label="Full Name" v-model="form.empName" :error="form.errors.empName" />
          <FormInput label="Email" v-model="form.email" type="email" :error="form.errors.email" />

          <SelectInput label="Status" v-model="form.empStatus" :error="form.errors.empStatus"
            :options="statuses.map(s => ({ value: s.id, label: s.description }))" />

          <FormInput label="Designation" v-model="form.empDesig" :error="form.errors.empDesig" />

          <SelectInput label="Schedule" v-model="form.schedule" :error="form.errors.schedule"
            :options="schedules.map(s => ({ value: s.id, label: s.schedulename }))" />

          <div class="col-span-2">
            <SelectInput label="Head / Department" v-model="form.empHead" :error="form.errors.empHead"
              :options="heads.map(h => ({ value: h.headname, label: `${h.headname} — ${h.headposition}` }))" />
          </div>

          <SelectInput label="Division" v-model="form.division_id" placeholder="— none —"
            :options="divisions.map(d => ({ value: d.id, label: d.division_name }))" />

          <div>
            <SelectInput label="Unit" v-model="form.unit_id" placeholder="— none —" :disabled="!form.division_id"
              :options="filteredUnits.map(u => ({ value: u.id, label: u.unit_name }))" />
            <p v-if="!form.division_id" class="text-xs text-gray-400 mt-1">Select a division first</p>
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

    <!-- Confirm dialog -->
    <ConfirmDialog :show="confirmState.show" :title="confirmState.title" :message="confirmState.message"
      @confirm="onConfirm" @cancel="confirmState.show = false" />
  </AppLayout>
</template>
