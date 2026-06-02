<script setup>
import { ref, computed, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { Modal, PrimaryButton, FlashMessage, DataTable, FormInput, SearchInput, Pagination, PageHeader, ConfirmDialog } from '@/Components'

const props = defineProps({ schedules: Array })

const DAYS = [
  { key: 'm',   label: 'Monday' },
  { key: 't',   label: 'Tuesday' },
  { key: 'w',   label: 'Wednesday' },
  { key: 'th',  label: 'Thursday' },
  { key: 'f',   label: 'Friday' },
  { key: 'sat', label: 'Saturday' },
  { key: 'sun', label: 'Sunday' },
]

function blankSlots() {
  const slots = {}
  DAYS.forEach(({ key }) => {
    slots[`${key}_timein`]   = ''
    slots[`${key}_breakout`] = ''
    slots[`${key}_breakin`]  = ''
    slots[`${key}_timeout`]  = ''
    slots[`${key}_crossday`] = false
  })
  return slots
}

const columns = [
  { key: 'schedulename', label: 'Name', cellClass: 'font-medium text-gray-900' },
  { key: 'activeDays',   label: 'Active Days', cellClass: 'text-gray-500 text-xs' },
]

const modalMode   = ref(null)
const editTarget  = ref(null)
const viewTarget  = ref(null)
const confirmState = ref({ show: false, title: '', message: '', action: null })

const form = useForm({ schedulename: '', ...blankSlots() })

function openAdd() {
  form.reset()
  form.clearErrors()
  Object.assign(form, blankSlots())
  editTarget.value = null
  modalMode.value  = 'add'
}

function openEdit(sched) {
  form.reset()
  form.clearErrors()
  form.schedulename = sched.schedulename
  DAYS.forEach(({ key }) => {
    form[`${key}_timein`]   = sched[`${key}_timein`]   ?? ''
    form[`${key}_breakout`] = sched[`${key}_breakout`] ?? ''
    form[`${key}_breakin`]  = sched[`${key}_breakin`]  ?? ''
    form[`${key}_timeout`]  = sched[`${key}_timeout`]  ?? ''
    form[`${key}_crossday`] = sched[`${key}_crossday`] ?? false
  })
  editTarget.value = sched
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
    form.post('/schedules', { onSuccess: closeModal })
  } else {
    form.put(`/schedules/${editTarget.value.id}`, { onSuccess: closeModal })
  }
}

function destroy(sched) {
  confirmState.value = {
    show: true,
    title: 'Delete Schedule',
    message: `Are you sure you want to delete "${sched.schedulename}"?`,
    action: () => router.delete(`/schedules/${sched.id}`),
  }
}

function onConfirm() {
  confirmState.value.action?.()
  confirmState.value.show = false
}

function activeDays(sched) {
  return DAYS.filter(({ key }) => sched[`${key}_timein`]).map(d => d.label.slice(0, 3)).join(', ')
}

function tableRows(list) {
  return list.map(s => ({ ...s, activeDays: activeDays(s) || '—' }))
}

const search      = ref('')
const perPage     = ref(15)
const currentPage = ref(1)
const PER_PAGE_OPTIONS = [10, 15, 25, 50]

const allRows  = computed(() => tableRows(props.schedules))
const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return allRows.value
  return allRows.value.filter(r =>
    r.schedulename.toLowerCase().includes(q) ||
    r.activeDays.toLowerCase().includes(q)
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
  <AppLayout title="Schedules">
    <FlashMessage />

    <PageHeader title="Schedules" :subtitle="`${filtered.length} schedule${filtered.length !== 1 ? 's' : ''}`">
      <PrimaryButton @click="openAdd">+ Add Schedule</PrimaryButton>
    </PageHeader>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
      <SearchInput v-model="search" placeholder="Search schedules…" class="w-full sm:w-72" />
      <div class="flex items-center gap-2 text-xs text-gray-500">
        <span>Show</span>
        <select v-model="perPage" class="border border-gray-300 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-indigo-400">
          <option v-for="n in PER_PAGE_OPTIONS" :key="n" :value="n">{{ n }}</option>
        </select>
        <span>per page</span>
      </div>
    </div>

    <DataTable :columns="columns" :rows="paginated">
      <template #actions="{ row }">
        <button @click="viewTarget = row" class="text-green-600 hover:underline text-xs mr-3">View</button>
        <button @click="openEdit(row)" class="text-blue-600 hover:underline text-xs mr-3">Edit</button>
        <button @click="destroy(row)" class="text-red-500 hover:underline text-xs">Delete</button>
      </template>
      <template #empty>No schedules yet.</template>
    </DataTable>

    <div class="mt-4">
      <Pagination :currentPage="currentPage" :totalPages="totalPages" :totalItems="filtered.length" @update:currentPage="currentPage = $event" />
    </div>

    <!-- Add / Edit modal -->
    <Modal :show="modalMode !== null" size="xl" @close="closeModal">
      <template #header>{{ modalMode === 'add' ? 'Add Schedule' : 'Edit Schedule' }}</template>

      <template #body>
        <form @submit.prevent="submit" id="sched-form">
          <div class="mb-4">
            <FormInput label="Schedule Name" v-model="form.schedulename" :error="form.errors.schedulename" />
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-xs">
              <thead>
                <tr class="bg-gray-50 text-gray-500">
                  <th class="px-2 py-2 text-left w-24">Day</th>
                  <th class="px-2 py-2 text-left">Time In</th>
                  <th class="px-2 py-2 text-left">Break Out</th>
                  <th class="px-2 py-2 text-left">Break In</th>
                  <th class="px-2 py-2 text-left">Time Out</th>
                  <th class="px-2 py-2 text-center">Cross-day</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="day in DAYS" :key="day.key" class="hover:bg-gray-50">
                  <td class="px-2 py-2 font-medium text-gray-700">{{ day.label }}</td>
                  <td class="px-2 py-1">
                    <input v-model="form[`${day.key}_timein`]" type="time"
                      class="w-full border border-gray-200 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-blue-400" />
                  </td>
                  <td class="px-2 py-1">
                    <input v-model="form[`${day.key}_breakout`]" type="time"
                      class="w-full border border-gray-200 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-blue-400" />
                  </td>
                  <td class="px-2 py-1">
                    <input v-model="form[`${day.key}_breakin`]" type="time"
                      class="w-full border border-gray-200 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-blue-400" />
                  </td>
                  <td class="px-2 py-1">
                    <input v-model="form[`${day.key}_timeout`]" type="time"
                      class="w-full border border-gray-200 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-blue-400" />
                  </td>
                  <td class="px-2 py-1 text-center">
                    <input v-model="form[`${day.key}_crossday`]" type="checkbox"
                      class="rounded border-gray-300 text-blue-600 focus:ring-blue-400" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </form>
      </template>

      <template #footer>
        <PrimaryButton variant="ghost" @click="closeModal">Cancel</PrimaryButton>
        <PrimaryButton type="submit" form="sched-form" :loading="form.processing">
          {{ modalMode === 'add' ? 'Add Schedule' : 'Save Changes' }}
        </PrimaryButton>
      </template>
    </Modal>

    <!-- View Schedule Detail Modal -->
    <Modal :show="!!viewTarget" size="lg" @close="viewTarget = null">
      <template #header>{{ viewTarget?.schedulename }}</template>
      <template #body>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-gray-50 text-gray-500 text-xs uppercase">
                <th class="px-3 py-2 text-left">Day</th>
                <th class="px-3 py-2 text-left">Time In</th>
                <th class="px-3 py-2 text-left">Break Out</th>
                <th class="px-3 py-2 text-left">Break In</th>
                <th class="px-3 py-2 text-left">Time Out</th>
                <th class="px-3 py-2 text-center">Cross-day</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="day in DAYS" :key="day.key" class="hover:bg-gray-50">
                <td class="px-3 py-2 font-medium text-gray-700">{{ day.label }}</td>
                <td class="px-3 py-2 font-mono text-gray-600">{{ viewTarget?.[`${day.key}_timein`] || '—' }}</td>
                <td class="px-3 py-2 font-mono text-gray-600">{{ viewTarget?.[`${day.key}_breakout`] || '—' }}</td>
                <td class="px-3 py-2 font-mono text-gray-600">{{ viewTarget?.[`${day.key}_breakin`] || '—' }}</td>
                <td class="px-3 py-2 font-mono text-gray-600">{{ viewTarget?.[`${day.key}_timeout`] || '—' }}</td>
                <td class="px-3 py-2 text-center">
                  <span v-if="viewTarget?.[`${day.key}_crossday`]" class="text-green-600 font-medium">✓</span>
                  <span v-else class="text-gray-300">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
      <template #footer>
        <PrimaryButton variant="ghost" @click="viewTarget = null">Close</PrimaryButton>
        <PrimaryButton @click="openEdit(viewTarget); viewTarget = null">Edit Schedule</PrimaryButton>
      </template>
    </Modal>

    <ConfirmDialog :show="confirmState.show" :title="confirmState.title" :message="confirmState.message"
      @confirm="onConfirm" @cancel="confirmState.show = false" />
  </AppLayout>
</template>
