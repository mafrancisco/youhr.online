<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Modal, PrimaryButton, FlashMessage, DataTable, FormInput, SelectInput, PageHeader, StatusBadge, ConfirmDialog } from '@/Components'

defineProps({
  users:     Array,
  employees: Array,
})

const columns = [
  { key: 'username', label: 'Username', cellClass: 'font-mono text-gray-700' },
  { key: 'fullname', label: 'Full Name', cellClass: 'font-medium text-gray-900' },
  { key: 'email',    label: 'Email',     cellClass: 'text-gray-500' },
  { key: 'type',     label: 'Role' },
]

const showModal = ref(false)
const confirmState = ref({ show: false, title: '', message: '', action: null })

const form = useForm({
  badgeID:              '',
  username:             '',
  password:             '',
  password_confirmation:'',
  type:                 '2',
})

function openAdd() {
  form.reset()
  form.clearErrors()
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  form.reset()
  form.clearErrors()
}

function submit() {
  form.post('/users', { onSuccess: closeModal })
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

    <PageHeader title="User Accounts" :subtitle="`(${users.length})`">
      <PrimaryButton @click="openAdd">+ Add User</PrimaryButton>
    </PageHeader>

    <DataTable :columns="columns" :rows="users">
      <template #cell-type="{ row }">
        <StatusBadge :status="row.type === 1 ? 'HR Officer' : 'Employee'"
          :color="row.type === 1 ? 'purple' : 'blue'" />
      </template>
      <template #actions="{ row }">
        <button @click="destroy(row)" class="text-red-500 hover:underline text-xs">Delete</button>
      </template>
      <template #empty>No user accounts yet.</template>
    </DataTable>

    <Modal :show="showModal" size="md" @close="closeModal">
      <template #header>Add User Account</template>

      <template #body>
        <form @submit.prevent="submit" id="user-form" class="space-y-4">
          <SelectInput label="Employee" v-model="form.badgeID" :error="form.errors.badgeID"
            :options="employees.map(e => ({ value: e.badgeID, label: e.empName }))" />

          <FormInput label="Username" v-model="form.username" :error="form.errors.username" />

          <SelectInput label="Role" v-model="form.type"
            :options="[{ value: '2', label: 'Employee' }, { value: '1', label: 'HR Officer' }]" />

          <FormInput label="Password" v-model="form.password" type="password" :error="form.errors.password" />

          <FormInput label="Confirm Password" v-model="form.password_confirmation" type="password" />
        </form>
      </template>

      <template #footer>
        <PrimaryButton variant="ghost" @click="closeModal">Cancel</PrimaryButton>
        <PrimaryButton type="submit" form="user-form" :loading="form.processing">Create Account</PrimaryButton>
      </template>
    </Modal>

    <ConfirmDialog :show="confirmState.show" :title="confirmState.title" :message="confirmState.message"
      @confirm="onConfirm" @cancel="confirmState.show = false" />
  </AppLayout>
</template>
