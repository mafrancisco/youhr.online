<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Modal, PrimaryButton, FlashMessage, DataTable, FormInput, PageHeader, StatusBadge, ConfirmDialog } from '@/Components'

const props = defineProps({
  pending:  Array,
  approved: Array,
  declined: Array,
})

const activeTab = ref('pending')

const pendingColumns = [
  { key: 'controlno',         label: 'Control No.',  cellClass: 'font-mono text-xs text-gray-600' },
  { key: 'empName',           label: 'Employee',     cellClass: 'font-medium text-gray-900' },
  { key: 'gatepass_type',     label: 'Type' },
  { key: 'gatepass_date',     label: 'Date',         cellClass: 'text-gray-600' },
  { key: 'gatepass_timeout',  label: 'Time Out',     cellClass: 'font-mono text-xs text-gray-600' },
  { key: 'gatepass_timein',   label: 'Time In',      cellClass: 'font-mono text-xs text-gray-600' },
  { key: 'purpose',           label: 'Purpose',      cellClass: 'text-gray-500 text-xs max-w-[160px] truncate' },
  { key: 'gatepass_datefiled', label: 'Filed',       cellClass: 'text-gray-400 text-xs' },
]

const approvedColumns = [
  ...pendingColumns,
  { key: 'date_time_approved', label: 'Approved On', cellClass: 'text-gray-500 text-xs' },
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
    title: 'Decline Gate Pass',
    message: `Are you sure you want to decline gate pass ${gp.controlno}?`,
    action: () => router.put(`/gate-passes/${gp.id}/cancel`),
  }
}

function onConfirm() {
  confirmState.value.action?.()
  confirmState.value.show = false
}
</script>

<template>
  <AppLayout title="Gate Pass Management">
    <FlashMessage />

    <PageHeader title="Gate Pass Management" />

    <!-- Tabs -->
    <div class="flex gap-1 mb-4 border-b">
      <button @click="activeTab = 'pending'"
        class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
        :class="activeTab === 'pending' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
        Pending <span class="ml-1 text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full">{{ pending.length }}</span>
      </button>
      <button @click="activeTab = 'approved'"
        class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
        :class="activeTab === 'approved' ? 'border-green-600 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
        Approved <span class="ml-1 text-xs text-gray-400">({{ approved.length }})</span>
      </button>
      <button @click="activeTab = 'declined'"
        class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
        :class="activeTab === 'declined' ? 'border-red-600 text-red-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
        Declined <span class="ml-1 text-xs text-gray-400">({{ declined.length }})</span>
      </button>
    </div>

    <!-- Pending Tab -->
    <div v-show="activeTab === 'pending'">
      <DataTable :columns="pendingColumns" :rows="pending">
        <template #cell-gatepass_type="{ row }">
          <StatusBadge :status="row.gatepass_type" :color="typeColorMap[row.gatepass_type] ?? 'gray'" />
        </template>
        <template #actions="{ row }">
          <button @click="openApprove(row)" class="text-green-600 hover:underline text-xs mr-3">Approve</button>
          <button @click="cancel(row)" class="text-red-500 hover:underline text-xs">Decline</button>
        </template>
        <template #empty>No pending gate passes.</template>
      </DataTable>
    </div>

    <!-- Approved Tab -->
    <div v-show="activeTab === 'approved'">
      <DataTable :columns="approvedColumns" :rows="approved">
        <template #cell-gatepass_type="{ row }">
          <StatusBadge :status="row.gatepass_type" :color="typeColorMap[row.gatepass_type] ?? 'gray'" />
        </template>
        <template #empty>No approved gate passes yet.</template>
      </DataTable>
    </div>

    <!-- Declined Tab -->
    <div v-show="activeTab === 'declined'">
      <DataTable :columns="pendingColumns" :rows="declined">
        <template #cell-gatepass_type="{ row }">
          <StatusBadge :status="row.gatepass_type" :color="typeColorMap[row.gatepass_type] ?? 'gray'" />
        </template>
        <template #empty>No declined gate passes.</template>
      </DataTable>
    </div>

    <!-- Approve Modal -->
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
