<script setup>
import { ref, computed, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Modal, PrimaryButton, FlashMessage, DataTable, FormInput, SelectInput, SearchInput, Pagination, PageHeader, StatusBadge, ConfirmDialog } from '@/Components'

const props = defineProps({
  users:     Array,
  employees: Array,
})

// ── Table columns ──────────────────────────────────────────────────────────
const columns = [
  { key: 'username', label: 'Username', cellClass: 'font-mono text-gray-700' },
  { key: 'fullname', label: 'Full Name', cellClass: 'font-medium text-gray-900' },
  { key: 'email',    label: 'Email',     cellClass: 'text-gray-500' },
  { key: 'type',     label: 'Role' },
]

// ── Search & pagination ────────────────────────────────────────────────────
const search      = ref('')
const perPage     = ref(15)
const currentPage = ref(1)

const PER_PAGE_OPTIONS = [10, 15, 25, 50]

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.users
  return props.users.filter(u =>
    u.fullname.toLowerCase().includes(q) ||
    u.username.toLowerCase().includes(q) ||
    (u.email ?? '').toLowerCase().includes(q)
  )
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage.value)))

const paginated = computed(() => {
  const start = (currentPage.value - 1) * perPage.value
  return filtered.value.slice(start, start + perPage.value)
})

watch([search, perPage], () => { currentPage.value = 1 })

// ── Modal state ────────────────────────────────────────────────────────────
const modalMode  = ref(null)   // 'add' | 'edit'
const editTarget = ref(null)

const confirmState = ref({ show: false, title: '', message: '', action: null })

// ── Forms ──────────────────────────────────────────────────────────────────
const form = useForm({
  // add-only
  badgeID:               '',
  // shared
  username:              '',
  fullname:              '',
  email:                 '',
  type:                  '2',
  password:              '',
  password_confirmation: '',
})

function openAdd() {
  modalMode.value = 'add'
  editTarget.value = null
  form.reset()
  form.clearErrors()
}

function openEdit(user) {
  modalMode.value = 'edit'
  editTarget.value = user
  form.reset()
  form.clearErrors()
  form.username = user.username
  form.fullname = user.fullname
  form.email    = user.email ?? ''
  form.type     = String(user.type)
  form.password = ''
  form.password_confirmation = ''
}

function closeModal() {
  modalMode.value = null
  form.reset()
  form.clearErrors()
}

function submit() {
  if (modalMode.value === 'add') {
    form.post('/users', { onSuccess: closeModal })
  } else {
    form.put(`/users/${editTarget.value.id}`, { onSuccess: closeModal })
  }
}

function destroy(user) {
  confirmState.value = {
    show: true,
    title: 'Delete User Account',
    message: `Are you sure you want to delete the account for ${user.fullname}?`,
    action: () => router.delete(`/users/${user.id}`),
  }
}

function onConfirm() {
  confirmState.value.action?.()
  confirmState.value.show = false
}
</script>

<template>
  <AppLayout title="Users">
    <FlashMessage />

    <PageHeader title="User Accounts" :subtitle="`${filtered.length} user${filtered.length !== 1 ? 's' : ''}`">
      <PrimaryButton @click="openAdd">+ Add User</PrimaryButton>
    </PageHeader>

    <!-- Search + per-page -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
      <SearchInput v-model="search" placeholder="Search by name, username or email…" class="w-full sm:w-72" />
      <div class="flex items-center gap-2 text-xs text-gray-500">
        <span>Show</span>
        <select v-model="perPage"
          class="border border-gray-300 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400">
          <option v-for="n in PER_PAGE_OPTIONS" :key="n" :value="n">{{ n }}</option>
        </select>
        <span>per page</span>
      </div>
    </div>

    <DataTable :columns="columns" :rows="paginated">
      <template #cell-type="{ row }">
        <StatusBadge :status="row.typeLabel" :color="row.type === 1 ? 'purple' : 'blue'" />
      </template>
      <template #actions="{ row }">
        <button @click="openEdit(row)" class="text-indigo-600 hover:underline text-xs mr-3">Edit</button>
        <button @click="destroy(row)" class="text-red-500 hover:underline text-xs">Delete</button>
      </template>
      <template #empty>No user accounts found.</template>
    </DataTable>

    <!-- Pagination -->
    <div class="mt-4">
      <Pagination
        :currentPage="currentPage"
        :totalPages="totalPages"
        :totalItems="filtered.length"
        @update:currentPage="currentPage = $event"
      />
    </div>

    <!-- Add / Edit Modal -->
    <Modal :show="modalMode !== null" size="md" @close="closeModal">
      <template #header>{{ modalMode === 'edit' ? 'Edit User Account' : 'Add User Account' }}</template>

      <template #body>
        <form @submit.prevent="submit" id="user-form" class="space-y-4">

          <!-- Employee selector — add mode only -->
          <SelectInput v-if="modalMode === 'add'"
            label="Employee" v-model="form.badgeID" :error="form.errors.badgeID"
            :options="employees.map(e => ({ value: e.badgeID, label: e.empName }))" />

          <FormInput label="Full Name" v-model="form.fullname" :error="form.errors.fullname" />

          <FormInput label="Username" v-model="form.username" :error="form.errors.username" />

          <FormInput label="Email" v-model="form.email" type="email" :error="form.errors.email" />

          <SelectInput label="Role" v-model="form.type" :error="form.errors.type"
            :options="[{ value: '2', label: 'Employee' }, { value: '1', label: 'HR Officer' }]" />

          <FormInput label="Password" v-model="form.password" type="password" :error="form.errors.password"
            :placeholder="modalMode === 'edit' ? 'Leave blank to keep current password' : ''" />

          <FormInput label="Confirm Password" v-model="form.password_confirmation" type="password"
            :placeholder="modalMode === 'edit' ? 'Leave blank to keep current password' : ''" />

        </form>
      </template>

      <template #footer>
        <PrimaryButton variant="ghost" @click="closeModal">Cancel</PrimaryButton>
        <PrimaryButton type="submit" form="user-form" :loading="form.processing">
          {{ modalMode === 'edit' ? 'Save Changes' : 'Create Account' }}
        </PrimaryButton>
      </template>
    </Modal>

    <ConfirmDialog :show="confirmState.show" :title="confirmState.title" :message="confirmState.message"
      @confirm="onConfirm" @cancel="confirmState.show = false" />
  </AppLayout>
</template>

