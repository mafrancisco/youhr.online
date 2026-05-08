<script setup>
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import FlashMessage from '@/Components/FlashMessage.vue'

const props = defineProps({
  divisions: Array,
  employees: Array,
})

const expanded   = ref({})
const modalType  = ref(null) // 'add-division'|'edit-division'|'add-unit'|'edit-unit'
const editTarget = ref(null)

function toggle(id) {
  expanded.value[id] = !expanded.value[id]
}

// ── Division form ──────────────────────────────────────────────
const divForm = useForm({ division_name: '', division_chief: '' })

function openAddDivision() {
  divForm.reset(); divForm.clearErrors()
  editTarget.value = null
  modalType.value  = 'add-division'
}

function openEditDivision(div) {
  divForm.reset(); divForm.clearErrors()
  divForm.division_name  = div.division_name
  divForm.division_chief = div.division_chief ?? ''
  editTarget.value = div
  modalType.value  = 'edit-division'
}

function submitDivision() {
  if (modalType.value === 'add-division') {
    divForm.post('/admin/divisions', { onSuccess: closeModal })
  } else {
    divForm.put(`/admin/divisions/${editTarget.value.id}`, { onSuccess: closeModal })
  }
}

function destroyDivision(div) {
  if (!confirm(`Delete division "${div.division_name}"? All its units will also be deleted.`)) return
  router.delete(`/admin/divisions/${div.id}`)
}

// ── Unit form ──────────────────────────────────────────────────
const unitForm = useForm({ unit_name: '', division_id: '', unit_head: '' })

function openAddUnit(div) {
  unitForm.reset(); unitForm.clearErrors()
  unitForm.division_id = div.id
  editTarget.value = { _divisionName: div.division_name }
  modalType.value  = 'add-unit'
}

function openEditUnit(unit, div) {
  unitForm.reset(); unitForm.clearErrors()
  unitForm.unit_name   = unit.unit_name
  unitForm.division_id = div.id
  unitForm.unit_head   = unit.unit_head ?? ''
  editTarget.value = { ...unit, _divisionName: div.division_name }
  modalType.value  = 'edit-unit'
}

function submitUnit() {
  if (modalType.value === 'add-unit') {
    unitForm.post('/admin/divisions/units', { onSuccess: closeModal })
  } else {
    unitForm.put(`/admin/divisions/units/${editTarget.value.id}`, { onSuccess: closeModal })
  }
}

function destroyUnit(unit) {
  if (!confirm(`Delete unit "${unit.unit_name}"?`)) return
  router.delete(`/admin/divisions/units/${unit.id}`)
}

function closeModal() {
  modalType.value  = null
  editTarget.value = null
  divForm.reset();  divForm.clearErrors()
  unitForm.reset(); unitForm.clearErrors()
}

const totalEmployees = computed(() => props.divisions.reduce((s, d) => s + (d.employees_count ?? 0), 0))
</script>

<template>
  <AppLayout title="Divisions">
    <FlashMessage />

    <div class="flex items-center justify-between mb-5">
      <h2 class="text-base font-semibold text-gray-700">
        Divisions ({{ divisions.length }}) &nbsp;·&nbsp;
        <span class="text-gray-400 font-normal">{{ totalEmployees }} employees assigned</span>
      </h2>
      <PrimaryButton @click="openAddDivision">+ Add Division</PrimaryButton>
    </div>

    <div class="space-y-3">
      <div v-for="div in divisions" :key="div.id" class="bg-white rounded-xl shadow overflow-hidden">

        <!-- Division header -->
        <div class="flex items-center gap-3 px-5 py-4 cursor-pointer select-none hover:bg-gray-50"
          @click="toggle(div.id)">
          <span class="text-gray-400 text-sm w-4">{{ expanded[div.id] ? '▾' : '▸' }}</span>
          <div class="flex-1 min-w-0">
            <p class="font-semibold text-gray-900">{{ div.division_name }}</p>
            <p class="text-xs text-gray-400 mt-0.5">
              Chief: {{ div.chief_name || '—' }}
              &nbsp;·&nbsp; {{ div.units.length }} unit{{ div.units.length !== 1 ? 's' : '' }}
              &nbsp;·&nbsp; {{ div.employees_count }} employee{{ div.employees_count !== 1 ? 's' : '' }}
            </p>
          </div>
          <div class="flex items-center gap-3 shrink-0" @click.stop>
            <button @click="openAddUnit(div)" class="text-blue-600 hover:underline text-xs">+ Unit</button>
            <button @click="openEditDivision(div)" class="text-blue-600 hover:underline text-xs">Edit</button>
            <button @click="destroyDivision(div)" class="text-red-500 hover:underline text-xs">Delete</button>
          </div>
        </div>

        <!-- Units table -->
        <div v-if="expanded[div.id]" class="border-t border-gray-100">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
              <tr>
                <th class="px-8 py-2 text-left">Unit Name</th>
                <th class="px-4 py-2 text-left">Unit Head</th>
                <th class="px-4 py-2"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="unit in div.units" :key="unit.id" class="hover:bg-gray-50">
                <td class="px-8 py-2.5 font-medium text-gray-800">{{ unit.unit_name }}</td>
                <td class="px-4 py-2.5 text-gray-500">{{ unit.head_name || '—' }}</td>
                <td class="px-4 py-2.5 text-right whitespace-nowrap">
                  <button @click="openEditUnit(unit, div)" class="text-blue-600 hover:underline text-xs mr-3">Edit</button>
                  <button @click="destroyUnit(unit)" class="text-red-500 hover:underline text-xs">Delete</button>
                </td>
              </tr>
              <tr v-if="!div.units.length">
                <td colspan="3" class="px-8 py-4 text-gray-400 text-xs italic">No units yet. Click "+ Unit" to add one.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="!divisions.length" class="bg-white rounded-xl shadow px-6 py-12 text-center text-gray-400">
        No divisions yet. Click "+ Add Division" to get started.
      </div>
    </div>

    <!-- Division modal -->
    <Modal :show="modalType === 'add-division' || modalType === 'edit-division'" size="sm" @close="closeModal">
      <template #header>{{ modalType === 'add-division' ? 'Add Division' : 'Edit Division' }}</template>
      <template #body>
        <form @submit.prevent="submitDivision" id="div-form" class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Division Name</label>
            <input v-model="divForm.division_name" type="text"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
              :class="divForm.errors.division_name ? 'border-red-400' : 'border-gray-300'" />
            <p v-if="divForm.errors.division_name" class="text-xs text-red-500 mt-1">{{ divForm.errors.division_name }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Division Chief</label>
            <select v-model="divForm.division_chief"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
              <option value="">— none —</option>
              <option v-for="e in employees" :key="e.badgeID" :value="e.badgeID">{{ e.empName }}</option>
            </select>
            <p v-if="divForm.errors.division_chief" class="text-xs text-red-500 mt-1">{{ divForm.errors.division_chief }}</p>
          </div>
        </form>
      </template>
      <template #footer>
        <PrimaryButton variant="ghost" @click="closeModal">Cancel</PrimaryButton>
        <PrimaryButton type="submit" form="div-form" :loading="divForm.processing">
          {{ modalType === 'add-division' ? 'Create Division' : 'Save Changes' }}
        </PrimaryButton>
      </template>
    </Modal>

    <!-- Unit modal -->
    <Modal :show="modalType === 'add-unit' || modalType === 'edit-unit'" size="sm" @close="closeModal">
      <template #header>{{ modalType === 'add-unit' ? 'Add Unit' : 'Edit Unit' }} — {{ editTarget?._divisionName }}</template>
      <template #body>
        <form @submit.prevent="submitUnit" id="unit-form" class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Unit Name</label>
            <input v-model="unitForm.unit_name" type="text"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
              :class="unitForm.errors.unit_name ? 'border-red-400' : 'border-gray-300'" />
            <p v-if="unitForm.errors.unit_name" class="text-xs text-red-500 mt-1">{{ unitForm.errors.unit_name }}</p>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Unit Head</label>
            <select v-model="unitForm.unit_head"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
              <option value="">— none —</option>
              <option v-for="e in employees" :key="e.badgeID" :value="e.badgeID">{{ e.empName }}</option>
            </select>
            <p v-if="unitForm.errors.unit_head" class="text-xs text-red-500 mt-1">{{ unitForm.errors.unit_head }}</p>
          </div>
        </form>
      </template>
      <template #footer>
        <PrimaryButton variant="ghost" @click="closeModal">Cancel</PrimaryButton>
        <PrimaryButton type="submit" form="unit-form" :loading="unitForm.processing">
          {{ modalType === 'add-unit' ? 'Create Unit' : 'Save Changes' }}
        </PrimaryButton>
      </template>
    </Modal>
  </AppLayout>
</template>
