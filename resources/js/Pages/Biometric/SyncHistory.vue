<script setup>
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { FlashMessage, DataTable, PageHeader, StatusBadge } from '@/Components'

const props = defineProps({
  device:  Object,
  history: Object, // paginated
})

const columns = [
  { key: 'type',            label: 'Type',          cellClass: 'capitalize text-gray-700' },
  { key: 'status',          label: 'Status' },
  { key: 'records_fetched', label: 'Fetched',       cellClass: 'text-center' },
  { key: 'records_new',     label: 'New',           cellClass: 'text-center text-green-700' },
  { key: 'records_skipped', label: 'Skipped',       cellClass: 'text-center text-gray-400' },
  { key: 'error_message',   label: 'Error',         cellClass: 'text-xs text-red-500 max-w-[200px] truncate' },
  { key: 'started_at',      label: 'Started',       cellClass: 'text-xs text-gray-500' },
  { key: 'completed_at',    label: 'Completed',     cellClass: 'text-xs text-gray-500' },
]

function goToPage(page) {
  router.get(`/biometric/devices/${props.device.id}/history`, { page }, { preserveState: true })
}
</script>

<template>
  <AppLayout :title="`Sync History — ${device.name}`">
    <FlashMessage />

    <PageHeader :title="`Sync History — ${device.name}`" :subtitle="`(${history.total} records)`" />

    <DataTable :columns="columns" :rows="history.data">
      <template #cell-status="{ row }">
        <StatusBadge :status="row.status" />
      </template>
      <template #empty>No sync history for this device.</template>
    </DataTable>

    <!-- Simple pagination -->
    <div v-if="history.last_page > 1" class="flex items-center justify-center gap-2 mt-4">
      <button v-for="page in history.last_page" :key="page"
        @click="goToPage(page)"
        class="px-3 py-1 text-xs rounded border transition-colors"
        :class="page === history.current_page
          ? 'bg-blue-600 text-white border-blue-600'
          : 'border-gray-300 hover:bg-gray-50'">
        {{ page }}
      </button>
    </div>
  </AppLayout>
</template>
