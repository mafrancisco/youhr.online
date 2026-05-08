<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Modal, PrimaryButton, FlashMessage, DataTable, FormInput, PageHeader, StatusBadge, ConfirmDialog } from '@/Components'

defineProps({ passes: Array })

const columns = [
  { key: 'controlno',         label: 'Control No.',  cellClass: 'font-mono text-xs text-gray-600' },
  { key: 'empName',           label: 'Employee',     cellClass: 'font-medium text-gray-900' },
  { key: 'gatepass_type',     label: 'Type' },
  { key: 'gatepass_date',     label: 'Date',         cellClass: 'text-gray-600' },
  { key: 'gatepass_timeout',  label: 'Time Out',     cellClass: 'font-mono text-xs text-gray-600' },
  { key: 'gatepass_timein',   label: 'Time In',      cellClass: 'font-mono text-xs text-gray-600' },
  { key: 'purpose',           label: 'Purpose',      cellClass: 'text-gray-500 text-xs max-w-[160px] truncate' },
  { key: 'gatepass_datefiled', label: 'Filed',       cellClass: 'text-gray-400 text-xs' },
]

const typeColorMap = {
  'Official Business': 'blue',
  'Official Time':     'green',
  'Personal':          'gray',
}

const modalTarget = ref(null)
const confirmState = ref({ show: false, title: '', message: '', action: null })

const form = useForm({
  actual_timeout: '',
  actual_timein:  '',
})

function openApprove(gp) {
  form.reset()
  form.clearErrors()
  form.actual_timeout = gp.gatepass_timeout ?? ''
  form.actual_timein  = gp.gatepass_timein  ?? ''
  modalTarget.value   = gp
}

function closeModal() {
  modalTarget.value = null
  form.reset()
  form.clearErrors()
}

function approve() {
  form.put(`/gate-passes/${modalTarget.value.id}/approve`, { onSuccess: closeModal })
}

function cancel(gp) {
  confirmState.value = {
    show: true,
    title: 'Cancel Gate Pass',
    message: `Are you sure you want to cancel gate pass ${gp.controlno}?`,
    action: () => router.put(`/gate-passes/${gp.id}/cancel`),
  }
}

function onConfirm() {
  confirmState.value.action?.()
  confirmState.value.show = false
}
</script>

<template>
  <AppLayout title="Gate Passes">
    <FlashMessage />

    <PageHeader title="Pending Gate Passes" :subtitle="`(${passes.length})`" />

    <DataTable :columns="columns" :rows="passes">
      <template #cell-gatepass_type="{ row }">
        <StatusBadge :status="row.gatepass_type" :color="typeColorMap[row.gatepass_type] ?? 'gray'" />
      </template>
      <template #actions="{ row }">
        <button @click="openApprove(row)" class="text-green-600 hover:underline text-xs mr-3">Approve</button>
        <button @click="cancel(row)" class="text-red-500 hover:underline text-xs">Cancel</button>
      </template>
      <template #empty>No pending gate passes.</template>
    </DataTable>

    <Modal :show="!!modalTarget" size="sm" @close="closeModal">
      <template #header>Approve Gate Pass — {{ modalTarget?.controlno }}</template>

      <template #body>
        <form @submit.prevent="approve" id="gp-form" class="space-y-3">
          <FormInput label="Actual Time Out" v-model="form.actual_timeout" type="time" />
          <FormInput label="Actual Time In" v-model="form.actual_timein" type="time" />
        </form>
      </template>

      <template #footer>
        <PrimaryButton variant="ghost" @click="closeModal">Cancel</PrimaryButton>
        <PrimaryButton type="submit" form="gp-form" variant="success" :loading="form.processing">
          Approve
        </PrimaryButton>
      </template>
    </Modal>

    <ConfirmDialog :show="confirmState.show" :title="confirmState.title" :message="confirmState.message"
      @confirm="onConfirm" @cancel="confirmState.show = false" />
  </AppLayout>
</template>
