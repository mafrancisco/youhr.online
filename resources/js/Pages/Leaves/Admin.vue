<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Modal, PrimaryButton, FlashMessage, DataTable, FormInput, SelectInput, PageHeader } from '@/Components'

defineProps({ leaves: Array })

const columns = [
  { key: 'controlno',  label: 'Control No.',  cellClass: 'font-mono text-xs text-gray-600' },
  { key: 'empName',    label: 'Employee',     cellClass: 'font-medium text-gray-900' },
  { key: 'type_name',  label: 'Type',         cellClass: 'text-gray-600' },
  { key: 'date_filed', label: 'Date Filed',   cellClass: 'text-gray-500 text-xs' },
  { key: 'dates',      label: 'Dates',        cellClass: 'text-gray-600 text-xs max-w-[180px] truncate' },
  { key: 'noofdays',   label: 'Days',         cellClass: 'text-center text-gray-700' },
  { key: 'details',    label: 'Details',      cellClass: 'text-gray-500 text-xs max-w-[140px] truncate' },
]

const modalTarget = ref(null)

const form = useForm({
  status:          'Approved',
  credits_vl:      '',
  credits_sl:      '',
  ot_credits:      '',
  service_credits: '',
})

function openReview(leave) {
  form.reset()
  form.clearErrors()
  form.status = 'Approved'
  modalTarget.value = leave
}

function closeModal() {
  modalTarget.value = null
  form.reset()
  form.clearErrors()
}

function submit() {
  form.put(`/leaves/${modalTarget.value.id}`, { onSuccess: closeModal })
}
</script>

<template>
  <AppLayout title="Pending Leaves">
    <FlashMessage />

    <PageHeader title="Pending Leaves" :subtitle="`(${leaves.length})`" />

    <DataTable :columns="columns" :rows="leaves">
      <template #actions="{ row }">
        <button @click="openReview(row)" class="text-blue-600 hover:underline text-xs">Review</button>
      </template>
      <template #empty>No pending leaves.</template>
    </DataTable>

    <Modal :show="!!modalTarget" size="md" @close="closeModal">
      <template #header>Review Leave — {{ modalTarget?.controlno }}</template>

      <template #body>
        <form @submit.prevent="submit" id="leave-form" class="space-y-4">
          <SelectInput label="Decision" v-model="form.status"
            :options="[{ value: 'Approved', label: 'Approved' }, { value: 'Cancelled', label: 'Cancelled' }]"
            :placeholder="null" />

          <template v-if="form.status === 'Approved'">
            <p class="text-xs font-medium text-gray-600">Credits to deduct</p>
            <div class="grid grid-cols-2 gap-3">
              <FormInput label="Vacation Leave" v-model="form.credits_vl" type="number" />
              <FormInput label="Sick Leave" v-model="form.credits_sl" type="number" />
              <FormInput label="OT Credits" v-model="form.ot_credits" type="number" />
              <FormInput label="Service Credits" v-model="form.service_credits" type="number" />
            </div>
          </template>
        </form>
      </template>

      <template #footer>
        <PrimaryButton variant="ghost" @click="closeModal">Cancel</PrimaryButton>
        <PrimaryButton type="submit" form="leave-form" :loading="form.processing"
          :variant="form.status === 'Cancelled' ? 'danger' : 'primary'">
          {{ form.status === 'Approved' ? 'Approve' : 'Cancel Leave' }}
        </PrimaryButton>
      </template>
    </Modal>
  </AppLayout>
</template>
