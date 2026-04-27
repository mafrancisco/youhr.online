<script setup>
import { ref } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import FlashMessage from '@/Components/FlashMessage.vue'

defineProps({
  users:     Array,
  employees: Array,
})

const showModal = ref(false)

const form = useForm({
  badgeID:              '',
  username:             '',
  password:             '',
  password_confirmation:'',
  type:                 '2',
})

function openAdd() {
  form.reset()
  form.clearErrors()
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  form.reset()
  form.clearErrors()
}

function submit() {
  form.post('/users', { onSuccess: closeModal })
}

function destroy(user) {
  if (!confirm(`Delete account for ${user.fullname}?`)) return
  router.delete(`/users/${user.id}`)
}
</script>

<template>
  <AppLayout title="Users">
    <FlashMessage />

    <div class="flex items-center justify-between mb-5">
      <h2 class="text-base font-semibold text-gray-700">User Accounts ({{ users.length }})</h2>
      <PrimaryButton @click="openAdd">+ Add User</PrimaryButton>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-4 py-3 text-left">Username</th>
            <th class="px-4 py-3 text-left">Full Name</th>
            <th class="px-4 py-3 text-left">Email</th>
            <th class="px-4 py-3 text-left">Role</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="u in users" :key="u.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-gray-700">{{ u.username }}</td>
            <td class="px-4 py-3 font-medium text-gray-900">{{ u.fullname }}</td>
            <td class="px-4 py-3 text-gray-500">{{ u.email }}</td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                :class="u.type === 1 ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'">
                {{ u.typeLabel }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <button @click="destroy(u)" class="text-red-500 hover:underline text-xs">Delete</button>
            </td>
          </tr>
          <tr v-if="!users.length">
            <td colspan="5" class="px-4 py-8 text-center text-gray-400">No user accounts yet.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <Modal :show="showModal" size="md" @close="closeModal">
      <template #header>Add User Account</template>

      <template #body>
        <form @submit.prevent="submit" id="user-form" class="space-y-4">
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Employee</label>
            <select v-model="form.badgeID"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
              :class="form.errors.badgeID ? 'border-red-400' : 'border-gray-300'">
              <option value="">— select —</option>
              <option v-for="e in employees" :key="e.badgeID" :value="e.badgeID">{{ e.empName }}</option>
            </select>
            <p v-if="form.errors.badgeID" class="text-xs text-red-500 mt-1">{{ form.errors.badgeID }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Username</label>
            <input v-model="form.username" type="text" autocomplete="off"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
              :class="form.errors.username ? 'border-red-400' : 'border-gray-300'" />
            <p v-if="form.errors.username" class="text-xs text-red-500 mt-1">{{ form.errors.username }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Role</label>
            <select v-model="form.type"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
              <option value="2">Employee</option>
              <option value="1">HR Officer</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Password</label>
            <input v-model="form.password" type="password" autocomplete="new-password"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
              :class="form.errors.password ? 'border-red-400' : 'border-gray-300'" />
            <p v-if="form.errors.password" class="text-xs text-red-500 mt-1">{{ form.errors.password }}</p>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Confirm Password</label>
            <input v-model="form.password_confirmation" type="password" autocomplete="new-password"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
          </div>
        </form>
      </template>

      <template #footer>
        <PrimaryButton variant="ghost" @click="closeModal">Cancel</PrimaryButton>
        <PrimaryButton type="submit" form="user-form" :loading="form.processing">Create Account</PrimaryButton>
      </template>
    </Modal>
  </AppLayout>
</template>
