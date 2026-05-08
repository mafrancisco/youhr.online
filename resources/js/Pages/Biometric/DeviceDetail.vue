<script setup>
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Modal, PrimaryButton, FlashMessage, DataTable, SelectInput, PageHeader, StatusBadge } from '@/Components'

const props = defineProps({
  device:      Object,
  users:       Array,
  recentLogs:  Array,
  syncHistory: Array,
  employees:   Array,
})

const activeTab = ref('users')

// ─── User Mapping ────────────────────────────────────────────────────────────
const mapTarget = ref(null)
const mapForm = useForm({ badge_id: '' })

function openMap(user) {
  mapForm.reset()
  mapForm.clearErrors()
  mapTarget.value = user
}

function closeMap() {
  mapTarget.value = null
  mapForm.reset()
}

function submitMap() {
  mapForm.post(`/biometric/users/${mapTarget.value.id}/map`, { onSuccess: closeMap })
}

function unmap(user) {
  if (!confirm(`Remove mapping for "${user.name}"?`)) return
  router.delete(`/biometric/users/${user.id}/map`)
}

// ─── Sync Actions ────────────────────────────────────────────────────────────
const syncing = ref(false)

function syncLogs() {
  syncing.value = true
  router.post(`/biometric/devices/${props.device.id}/sync-logs`, {}, {
    onFinish: () => { syncing.value = false },
  })
}

function syncUsers() {
  syncing.value = true
  router.post(`/biometric/devices/${props.device.id}/sync-users`, {}, {
    onFinish: () => { syncing.value = false },
  })
}

// ─── Computed ────────────────────────────────────────────────────────────────
const unmappedUsers = computed(() => props.users.filter(u => !u.is_mapped))
const mappedUsers = computed(() => props.users.filter(u => u.is_mapped))

const userColumns = [
  { key: 'user_id',  label: 'Device User ID', cellClass: 'font-mono text-gray-700' },
  { key: 'name',     label: 'Name on Device',  cellClass: 'text-gray-900' },
  { key: 'badge_id', label: 'Mapped Employee' },
]

const logColumns = [
  { key: 'device_user_id', label: 'User ID',    cellClass: 'font-mono text-gray-700' },
  { key: 'timestamp',      label: 'Timestamp',  cellClass: 'text-gray-600' },
  { key: 'punch_label',    label: 'Punch Type' },
  { key: 'is_processed',   label: 'Processed' },
]

const historyColumns = [
  { key: 'type',            label: 'Type',     cellClass: 'text-gray-700' },
  { key: 'status',          label: 'Status' },
  { key: 'records_fetched', label: 'Fetched',  cellClass: 'text-center' },
  { key: 'records_new',     label: 'New',      cellClass: 'text-center text-green-700' },
  { key: 'records_skipped', label: 'Skipped',  cellClass: 'text-center text-gray-400' },
  { key: 'created_at',      label: 'Date',     cellClass: 'text-xs text-gray-500' },
]
</script>

<template>
  <AppLayout :title="device.name">
    <FlashMessage />

    <!-- Device Info Header -->
    <div class="bg-white rounded-xl shadow p-6 mb-6">
      <div class="flex items-start justify-between">
        <div>
          <h2 class="text-lg font-semibold text-gray-900">{{ device.name }}</h2>
          <p class="text-sm text-gray-500 mt-1">
            {{ device.model }} · {{ device.ip_address }}:{{ device.port }} · {{ device.connection_type }}
            <span v-if="device.location"> · {{ device.location }}</span>
          </p>
          <div class="flex items-center gap-3 mt-3">
            <StatusBadge :status="device.status === 'active' ? 'Active' : 'Inactive'" />
            <span class="flex items-center gap-1.5 text-xs">
              <span class="w-2 h-2 rounded-full" :class="device.is_online ? 'bg-green-500' : 'bg-gray-300'" />
              {{ device.is_online ? 'Online' : 'Offline' }}
            </span>
            <span v-if="device.last_sync_at" class="text-xs text-gray-400">
              Last sync: {{ new Date(device.last_sync_at).toLocaleString() }}
            </span>
          </div>
        </div>
        <div class="flex gap-2">
          <PrimaryButton variant="ghost" @click="syncUsers" :loading="syncing">
            Sync Users
          </PrimaryButton>
          <PrimaryButton @click="syncLogs" :loading="syncing">
            Sync Logs
          </PrimaryButton>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 mb-4 border-b">
      <button v-for="tab in ['users', 'logs', 'history']" :key="tab"
        @click="activeTab = tab"
        class="px-4 py-2 text-sm font-medium border-b-2 transition-colors capitalize"
        :class="activeTab === tab
          ? 'border-blue-600 text-blue-600'
          : 'border-transparent text-gray-500 hover:text-gray-700'">
        {{ tab }}
        <span v-if="tab === 'users'" class="ml-1 text-xs text-gray-400">({{ users.length }})</span>
        <span v-if="tab === 'logs'" class="ml-1 text-xs text-gray-400">({{ recentLogs.length }})</span>
      </button>
    </div>

    <!-- Users Tab -->
    <div v-show="activeTab === 'users'">
      <div v-if="unmappedUsers.length" class="mb-4 bg-amber-50 border border-amber-200 rounded-lg p-3">
        <p class="text-sm text-amber-800 font-medium">
          {{ unmappedUsers.length }} unmapped user{{ unmappedUsers.length > 1 ? 's' : '' }} — assign them to employees for attendance tracking.
        </p>
      </div>

      <DataTable :columns="userColumns" :rows="users">
        <template #cell-badge_id="{ row }">
          <span v-if="row.is_mapped" class="text-green-700 text-xs">
            {{ row.emp_name }} ({{ row.badge_id }})
          </span>
          <span v-else class="text-amber-600 text-xs italic">Unmapped</span>
        </template>
        <template #actions="{ row }">
          <button v-if="!row.is_mapped" @click="openMap(row)" class="text-blue-600 hover:underline text-xs mr-2">Map</button>
          <button v-else @click="unmap(row)" class="text-red-500 hover:underline text-xs">Unmap</button>
        </template>
        <template #empty>No users synced from this device yet. Click "Sync Users" to fetch.</template>
      </DataTable>
    </div>

    <!-- Logs Tab -->
    <div v-show="activeTab === 'logs'">
      <DataTable :columns="logColumns" :rows="recentLogs">
        <template #cell-punch_label="{ row }">
          <StatusBadge :status="row.punch_label"
            :color="row.punch_type === 0 ? 'green' : row.punch_type === 1 ? 'red' : 'blue'" />
        </template>
        <template #cell-is_processed="{ row }">
          <span :class="row.is_processed ? 'text-green-600' : 'text-gray-400'" class="text-xs">
            {{ row.is_processed ? '✓ Yes' : 'Pending' }}
          </span>
        </template>
        <template #empty>No logs synced yet. Click "Sync Logs" to fetch attendance data.</template>
      </DataTable>
    </div>

    <!-- History Tab -->
    <div v-show="activeTab === 'history'">
      <DataTable :columns="historyColumns" :rows="syncHistory">
        <template #cell-status="{ row }">
          <StatusBadge :status="row.status" />
        </template>
        <template #empty>No sync history yet.</template>
      </DataTable>
    </div>

    <!-- Map User Modal -->
    <Modal :show="!!mapTarget" size="sm" @close="closeMap">
      <template #header>Map User — {{ mapTarget?.name || mapTarget?.user_id }}</template>
      <template #body>
        <form @submit.prevent="submitMap" id="map-form" class="space-y-4">
          <p class="text-sm text-gray-600">
            Device User ID: <span class="font-mono font-medium">{{ mapTarget?.user_id }}</span>
          </p>
          <SelectInput label="Employee" v-model="mapForm.badge_id" :error="mapForm.errors.badge_id"
            :options="employees.map(e => ({ value: e.badgeID, label: `${e.empName} (${e.badgeID})` }))" />
        </form>
      </template>
      <template #footer>
        <PrimaryButton variant="ghost" @click="closeMap">Cancel</PrimaryButton>
        <PrimaryButton type="submit" form="map-form" :loading="mapForm.processing">Map Employee</PrimaryButton>
      </template>
    </Modal>
  </AppLayout>
</template>
