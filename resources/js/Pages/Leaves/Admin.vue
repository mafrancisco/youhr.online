<script setup>
import { ref, computed } from 'vue'
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
  credits_maternity: '',
  credits_paternity: '',
  credits_spl:     '',
  credits_forced:  '',
  credits_wellness:'',
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

const currentCredits = computed(() => modalTarget.value?.credits || null)
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

    <Modal :show="!!modalTarget" size="lg" @close="closeModal">
      <template #header>Review Leave — {{ modalTarget?.controlno }}</template>

      <template #body>
        <form @submit.prevent="submit" id="leave-form" class="space-y-4">

          <!-- Leave Info Summary -->
          <div class="bg-gray-50 rounded-lg p-3 text-sm space-y-1">
            <p><span class="text-gray-500">Employee:</span> <span class="font-medium">{{ modalTarget?.empName }}</span></p>
            <p><span class="text-gray-500">Leave Type:</span> <span class="font-medium">{{ modalTarget?.type_name }}</span></p>
            <p><span class="text-gray-500">Days Requested:</span> <span class="font-medium">{{ modalTarget?.noofdays }}</span></p>
            <p><span class="text-gray-500">Dates:</span> <span class="font-mono text-xs">{{ modalTarget?.dates }}</span></p>
          </div>

          <!-- Employee Leave Balance -->
          <div v-if="currentCredits" class="border border-blue-200 bg-blue-50 rounded-lg p-3">
            <p class="text-xs font-semibold text-blue-800 mb-2">Available Leave Balance</p>
            <div class="grid grid-cols-3 gap-2 text-xs">
              <div class="bg-white rounded px-2 py-1.5 border">
                <span class="text-gray-500">VL:</span>
                <span class="font-bold text-gray-800 ml-1">{{ currentCredits.vl }}</span>
              </div>
              <div class="bg-white rounded px-2 py-1.5 border">
                <span class="text-gray-500">SL:</span>
                <span class="font-bold text-gray-800 ml-1">{{ currentCredits.sl }}</span>
              </div>
              <div class="bg-white rounded px-2 py-1.5 border">
                <span class="text-gray-500">Maternity:</span>
                <span class="font-bold text-gray-800 ml-1">{{ currentCredits.maternity }}</span>
              </div>
              <div class="bg-white rounded px-2 py-1.5 border">
                <span class="text-gray-500">Paternity:</span>
                <span class="font-bold text-gray-800 ml-1">{{ currentCredits.paternity }}</span>
              </div>
              <div class="bg-white rounded px-2 py-1.5 border">
                <span class="text-gray-500">SPL:</span>
                <span class="font-bold text-gray-800 ml-1">{{ currentCredits.spl }}</span>
              </div>
              <div class="bg-white rounded px-2 py-1.5 border">
                <span class="text-gray-500">Forced:</span>
                <span class="font-bold text-gray-800 ml-1">{{ currentCredits.forced }}</span>
              </div>
              <div class="bg-white rounded px-2 py-1.5 border">
                <span class="text-gray-500">Wellness:</span>
                <span class="font-bold text-gray-800 ml-1">{{ currentCredits.wellness }}</span>
              </div>
              <div class="bg-white rounded px-2 py-1.5 border">
                <span class="text-gray-500">OT:</span>
                <span class="font-bold text-gray-800 ml-1">{{ currentCredits.ot }}</span>
              </div>
              <div class="bg-white rounded px-2 py-1.5 border">
                <span class="text-gray-500">Service:</span>
                <span class="font-bold text-gray-800 ml-1">{{ currentCredits.service }}</span>
              </div>
            </div>
          </div>
          <div v-else class="border border-amber-200 bg-amber-50 rounded-lg p-3">
            <p class="text-xs text-amber-800 font-medium">⚠ No leave credit record found for this employee.</p>
          </div>

          <SelectInput label="Decision" v-model="form.status"
            :options="[{ value: 'Approved', label: 'Approved' }, { value: 'Cancelled', label: 'Cancelled' }]"
            :placeholder="null" />

          <template v-if="form.status === 'Approved'">
            <p class="text-xs font-medium text-gray-600">Credits to deduct</p>
            <div class="grid grid-cols-3 gap-3">
              <div>
                <FormInput label="Vacation Leave" v-model="form.credits_vl" type="number" />
                <p v-if="currentCredits" class="text-xs text-gray-400 mt-0.5">Balance: {{ currentCredits.vl }}</p>
              </div>
              <div>
                <FormInput label="Sick Leave" v-model="form.credits_sl" type="number" />
                <p v-if="currentCredits" class="text-xs text-gray-400 mt-0.5">Balance: {{ currentCredits.sl }}</p>
              </div>
              <div>
                <FormInput label="Maternity" v-model="form.credits_maternity" type="number" />
                <p v-if="currentCredits" class="text-xs text-gray-400 mt-0.5">Balance: {{ currentCredits.maternity }}</p>
              </div>
              <div>
                <FormInput label="Paternity" v-model="form.credits_paternity" type="number" />
                <p v-if="currentCredits" class="text-xs text-gray-400 mt-0.5">Balance: {{ currentCredits.paternity }}</p>
              </div>
              <div>
                <FormInput label="SPL" v-model="form.credits_spl" type="number" />
                <p v-if="currentCredits" class="text-xs text-gray-400 mt-0.5">Balance: {{ currentCredits.spl }}</p>
              </div>
              <div>
                <FormInput label="Forced Leave" v-model="form.credits_forced" type="number" />
                <p v-if="currentCredits" class="text-xs text-gray-400 mt-0.5">Balance: {{ currentCredits.forced }}</p>
              </div>
              <div>
                <FormInput label="Wellness" v-model="form.credits_wellness" type="number" />
                <p v-if="currentCredits" class="text-xs text-gray-400 mt-0.5">Balance: {{ currentCredits.wellness }}</p>
              </div>
              <div>
                <FormInput label="OT Credits" v-model="form.ot_credits" type="number" />
                <p v-if="currentCredits" class="text-xs text-gray-400 mt-0.5">Balance: {{ currentCredits.ot }}</p>
              </div>
              <div>
                <FormInput label="Service Credits" v-model="form.service_credits" type="number" />
                <p v-if="currentCredits" class="text-xs text-gray-400 mt-0.5">Balance: {{ currentCredits.service }}</p>
              </div>
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
