<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const page    = usePage()
const pending = computed(() => page.props.pendingLeaves ?? 0)
const pendingGP = computed(() => page.props.pendingGatePasses ?? 0)
</script>

<template>
  <AppLayout title="Dashboard">
    <template #header>HR Dashboard</template>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <div class="bg-white rounded-xl shadow p-6">
        <p class="text-sm text-gray-500">Pending Leaves</p>
        <p class="text-3xl font-bold text-amber-600 mt-1">{{ pending }}</p>
      </div>
      <div class="bg-white rounded-xl shadow p-6">
        <p class="text-sm text-gray-500">Pending Gate Passes</p>
        <p class="text-3xl font-bold text-blue-600 mt-1">{{ pendingGP }}</p>
      </div>
      <div class="bg-white rounded-xl shadow p-6">
        <p class="text-sm text-gray-500">Quick Links</p>
        <div class="mt-2 space-y-1 text-sm">
          <a href="/employees" class="block text-blue-600 hover:underline">→ Employees</a>
          <a href="/attendance/upload" class="block text-blue-600 hover:underline">→ Import DTR</a>
          <a href="/reports/dtr" class="block text-blue-600 hover:underline">→ DTR Reports</a>
        </div>
      </div>
      <div class="bg-white rounded-xl shadow p-6">
        <p class="text-sm text-gray-500">System</p>
        <p class="text-xs text-gray-400 mt-2">IronOne DTR System</p>
      </div>
    </div>
  </AppLayout>
</template>
