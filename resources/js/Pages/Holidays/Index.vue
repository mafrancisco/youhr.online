<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import FlashMessage from '@/Components/FlashMessage.vue'

defineProps({
  types:      Array,
  parameters: Array,
})

const modalMode  = ref(null)
const editTarget = ref(null)

const form = useForm({
  type:        '',
  description: '',
  actualDate:  '',
  timein:      '',
  breakout:    '',
  breakin:     '',
  timeout:     '',
})

const needsTime = (type) =>
  type === 'Adjusted Flag Ceremony Schedule' || type === 'Override Official Time'

function openAdd() {
  form.reset()
  form.clearErrors()
  editTarget.value = null
  modalMode.value  = 'add'
}

function openEdit(param) {
  form.reset()
  form.clearErrors()
  form.type        = param.type
  form.description = param.description
  form.actualDate  = param.actualDate
  form.timein      = param.timein  ?? ''
  form.breakout    = param.breakout ?? ''
  form.breakin     = param.breakin  ?? ''
  form.timeout     = param.timeout  ?? ''
  editTarget.value = param
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
    form.post('/holidays', { onSuccess: closeModal })
  } else {
    form.put(`/holidays/${editTarget.value.id}`, { onSuccess: closeModal })
  }
}

function destroy(param) {
  if (!confirm(`Delete "${param.description}" (${param.actualDate})?`)) return
  router.delete(`/holidays/${param.id}`)
}

const typeColors = {
  'Regular Holiday':                  'bg-red-100 text-red-700',
  'Special Non-Working Holiday':      'bg-orange-100 text-orange-700',
  'Adjusted Flag Ceremony Schedule':  'bg-purple-100 text-purple-700',
  'Override Official Time':           'bg-blue-100 text-blue-700',
}

function badgeClass(type) {
  return typeColors[type] ?? 'bg-gray-100 text-gray-600'
}
</script>

<template>
  <AppLayout title="Date Parameters">
    <FlashMessage />

    <div class="flex items-center justify-between mb-5">
      <h2 class="text-base font-semibold text-gray-700">Date Parameters ({{ parameters.length }})</h2>
      <PrimaryButton @click="openAdd">+ Add Parameter</PrimaryButton>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-4 py-3 text-left">Date</th>
            <th class="px-4 py-3 text-left">Type</th>
            <th class="px-4 py-3 text-left">Description</th>
            <th class="px-4 py-3 text-left">Time In</th>
            <th class="px-4 py-3 text-left">Break Out</th>
            <th class="px-4 py-3 text-left">Break In</th>
            <th class="px-4 py-3 text-left">Time Out</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="p in parameters" :key="p.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-gray-700">{{ p.actualDate }}</td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 rounded-full text-xs font-medium" :class="badgeClass(p.type)">
                {{ p.type }}
              </span>
            </td>
            <td class="px-4 py-3 text-gray-700">{{ p.description }}</td>
            <td class="px-4 py-3 text-gray-500 font-mono text-xs">{{ p.timein   || '—' }}</td>
            <td class="px-4 py-3 text-gray-500 font-mono text-xs">{{ p.breakout || '—' }}</td>
            <td class="px-4 py-3 text-gray-500 font-mono text-xs">{{ p.breakin  || '—' }}</td>
            <td class="px-4 py-3 text-gray-500 font-mono text-xs">{{ p.timeout  || '—' }}</td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <button @click="openEdit(p)" class="text-blue-600 hover:underline text-xs mr-3">Edit</button>
              <button @click="destroy(p)"  class="text-red-500 hover:underline text-xs">Delete</button>
            </td>
          </tr>
          <tr v-if="!parameters.length">
            <td colspan="8" class="px-4 py-8 text-center text-gray-400">No date parameters yet.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add / Edit modal -->
    <Modal :show="modalMode !== null" size="md" @close="closeModal">
      <template #header>{{ modalMode === 'add' ? 'Add Date Parameter' : 'Edit Date Parameter' }}</template>

      <template #body>
        <form @submit.prevent="submit" id="param-form" class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Type</label>
            <select v-model="form.type"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
              :class="form.errors.type ? 'border-red-400' : 'border-gray-300'">
              <option value="">— select —</option>
              <option v-for="t in types" :key="t.id" :value="t.type">{{ t.type }}</option>
            </select>
            <p v-if="form.errors.type" class="text-xs text-red-500 mt-1">{{ form.errors.type }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
            <input v-model="form.description" type="text"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
              :class="form.errors.description ? 'border-red-400' : 'border-gray-300'" />
            <p v-if="form.errors.description" class="text-xs text-red-500 mt-1">{{ form.errors.description }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Date</label>
            <input v-model="form.actualDate" type="date"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
              :class="form.errors.actualDate ? 'border-red-400' : 'border-gray-300'" />
            <p v-if="form.errors.actualDate" class="text-xs text-red-500 mt-1">{{ form.errors.actualDate }}</p>
          </div>

          <template v-if="needsTime(form.type)">
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Time In</label>
                <input v-model="form.timein" type="time"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Break Out</label>
                <input v-model="form.breakout" type="time"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Break In</label>
                <input v-model="form.breakin" type="time"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Time Out</label>
                <input v-model="form.timeout" type="time"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
              </div>
            </div>
          </template>
        </form>
      </template>

      <template #footer>
        <PrimaryButton variant="ghost" @click="closeModal">Cancel</PrimaryButton>
        <PrimaryButton type="submit" form="param-form" :loading="form.processing">
          {{ modalMode === 'add' ? 'Add Parameter' : 'Save Changes' }}
        </PrimaryButton>
      </template>
    </Modal>
  </AppLayout>
</template>
