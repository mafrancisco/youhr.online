<script setup>
import { ref, computed, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Modal, PrimaryButton, FlashMessage, DataTable, FormInput, SelectInput, SearchInput, Pagination, PageHeader, StatusBadge, ConfirmDialog } from '@/Components'

const props = defineProps({ devices: Array })

const columns = [
  { key: 'name',            label: 'Device Name',  cellClass: 'font-medium text-gray-900' },
  { key: 'model',           label: 'Model',        cellClass: 'text-gray-600 text-xs' },
  { key: 'ip_address',      label: 'IP Address',   cellClass: 'font-mono text-gray-700' },
  { key: 'port',            label: 'Port',         cellClass: 'text-gray-500' },
  { key: 'connection_type', label: 'Connection',   cellClass: 'text-gray-600' },
  { key: 'location',        label: 'Location',     cellClass: 'text-gray-500 text-xs' },
  { key: 'status',          label: 'Status' },
  { key: 'is_online',       label: 'Online' },
]

const modalMode  = ref(null)
const editTarget = ref(null)
const confirmState = ref({ show: false, title: '', message: '', action: null })
const testResult = ref(null)

const form = useForm({
  name:            '',
  model:           'ZK IN05-A',
  serial_number:   '',
  ip_address:      '',
  port:            4370,
  connection_type: 'LAN',
  location:        '',
  status:          'active',
  remarks:         '',
})

const testForm = useForm({
  ip_address: '',
  port:       4370,
})

function openAdd() {
  form.reset()
  form.clearErrors()
  form.model = 'ZK IN05-A'
  form.port = 4370
  form.connection_type = 'LAN'
  form.status = 'active'
  editTarget.value = null
  modalMode.value = 'add'
}

function openEdit(device) {
  form.reset()
  form.clearErrors()
  form.name            = device.name
  form.model           = device.model
  form.serial_number   = device.serial_number || ''
  form.ip_address      = device.ip_address
  form.port            = device.port
  form.connection_type = device.connection_type
  form.location        = device.location || ''
  form.status          = device.status
  form.remarks         = device.remarks || ''
  editTarget.value     = device
  modalMode.value      = 'edit'
}

function closeModal() {
  modalMode.value = null
  editTarget.value = null
  form.reset()
}

function submit() {
  if (modalMode.value === 'add') {
    form.post('/biometric/devices', { onSuccess: closeModal })
  } else {
    form.put(`/biometric/devices/${editTarget.value.id}`, { onSuccess: closeModal })
  }
}

function destroy(device) {
  confirmState.value = {
    show: true,
    title: 'Delete Device',
    message: `Delete "${device.name}" (${device.ip_address})? All associated logs and users will be removed.`,
    action: () => router.delete(`/biometric/devices/${device.id}`),
  }
}

function onConfirm() {
  confirmState.value.action?.()
  confirmState.value.show = false
}

function testConnection() {
  testForm.ip_address = form.ip_address
  testForm.port = form.port
  testForm.post('/biometric/devices/test-connection', {
    preserveScroll: true,
  })
}

function viewDevice(device) {
  router.get(`/biometric/devices/${device.id}`)
}

const search      = ref('')
const perPage     = ref(15)
const currentPage = ref(1)
const PER_PAGE_OPTIONS = [10, 15, 25, 50]

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.devices
  return props.devices.filter(d =>
    (d.name ?? '').toLowerCase().includes(q) ||
    (d.ip_address ?? '').includes(q) ||
    (d.location ?? '').toLowerCase().includes(q)
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
  <AppLayout title="Biometric Devices">
    <FlashMessage />

    <PageHeader title="Biometric Devices" :subtitle="`${filtered.length} device${filtered.length !== 1 ? 's' : ''}`">
      <PrimaryButton @click="openAdd">+ Register Device</PrimaryButton>
    </PageHeader>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
      <SearchInput v-model="search" placeholder="Search by name, IP or location…" class="w-full sm:w-72" />
      <div class="flex items-center gap-2 text-xs text-gray-500">
        <span>Show</span>
        <select v-model="perPage" class="border border-gray-300 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400">
          <option v-for="n in PER_PAGE_OPTIONS" :key="n" :value="n">{{ n }}</option>
        </select>
        <span>per page</span>
      </div>
    </div>

    <DataTable :columns="columns" :rows="paginated">
      <template #cell-status="{ row }">
        <StatusBadge :status="row.status === 'active' ? 'Active' : 'Inactive'" />
      </template>
      <template #cell-is_online="{ row }">
        <span class="flex items-center gap-1.5">
          <span class="w-2 h-2 rounded-full" :class="row.is_online ? 'bg-green-500' : 'bg-gray-300'" />
          <span class="text-xs" :class="row.is_online ? 'text-green-700' : 'text-gray-400'">
            {{ row.is_online ? 'Online' : 'Offline' }}
          </span>
        </span>
      </template>
      <template #actions="{ row }">
        <button @click="viewDevice(row)" class="text-blue-600 hover:underline text-xs mr-3">Details</button>
        <button @click="openEdit(row)" class="text-gray-600 hover:underline text-xs mr-3">Edit</button>
        <button @click="destroy(row)" class="text-red-500 hover:underline text-xs">Delete</button>
      </template>
      <template #empty>No biometric devices registered yet.</template>
    </DataTable>

    <div class="mt-4">
      <Pagination :currentPage="currentPage" :totalPages="totalPages" :totalItems="filtered.length" @update:currentPage="currentPage = $event" />
    </div>

    <!-- Add / Edit Modal -->
    <Modal :show="modalMode !== null" size="lg" @close="closeModal">
      <template #header>{{ modalMode === 'add' ? 'Register Device' : 'Edit Device' }}</template>

      <template #body>
        <form @submit.prevent="submit" id="device-form" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <FormInput label="Device Name" v-model="form.name" :error="form.errors.name" required />
            <SelectInput label="Model" v-model="form.model" :error="form.errors.model"
              :options="['ZK IN05-A', 'ZK F18', 'ZK K40', 'ZK MB20', 'ZK MB460 Plus', 'ZK iClock 880', 'Other']" />
          </div>

          <FormInput label="Serial Number" v-model="form.serial_number" :error="form.errors.serial_number"
            placeholder="e.g. ABCD123456789" required />

          <div class="grid grid-cols-3 gap-4">
            <div class="col-span-2">
              <FormInput label="IP Address" v-model="form.ip_address" :error="form.errors.ip_address"
                placeholder="192.168.1.100" required />
            </div>
            <FormInput label="Port" v-model="form.port" type="number" :error="form.errors.port" required />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <SelectInput label="Connection Type" v-model="form.connection_type" :error="form.errors.connection_type"
              :options="[{ value: 'LAN', label: 'LAN (Wired)' }, { value: 'WLAN', label: 'WLAN (Wireless)' }]" />
            <FormInput label="Location" v-model="form.location" placeholder="e.g. Main Entrance" />
          </div>

          <div v-if="modalMode === 'edit'">
            <SelectInput label="Status" v-model="form.status" :error="form.errors.status"
              :options="[{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }]" />
          </div>

          <FormInput label="Remarks" v-model="form.remarks" type="textarea" :rows="2" />

          <!-- Test Connection -->
          <div class="border-t pt-4">
            <PrimaryButton type="button" variant="ghost" @click="testConnection"
              :loading="testForm.processing" :disabled="!form.ip_address || !form.port">
              🔌 Test Connection
            </PrimaryButton>
          </div>
        </form>
      </template>

      <template #footer>
        <PrimaryButton variant="ghost" @click="closeModal">Cancel</PrimaryButton>
        <PrimaryButton type="submit" form="device-form" :loading="form.processing">
          {{ modalMode === 'add' ? 'Register Device' : 'Save Changes' }}
        </PrimaryButton>
      </template>
    </Modal>

    <ConfirmDialog :show="confirmState.show" :title="confirmState.title" :message="confirmState.message"
      @confirm="onConfirm" @cancel="confirmState.show = false" />
  </AppLayout>
</template>
