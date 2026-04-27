<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
</script>

<template>
  <AppLayout title="Dashboard">
    <template #header>Employee Dashboard</template>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
      <a href="/dtr" class="bg-white rounded-xl shadow p-6 hover:shadow-md transition-shadow">
        <p class="text-2xl mb-2">📋</p>
        <p class="font-semibold text-gray-800">My DTR</p>
        <p class="text-sm text-gray-500 mt-1">View and manage your attendance</p>
      </a>
      <a href="/leaves" class="bg-white rounded-xl shadow p-6 hover:shadow-md transition-shadow">
        <p class="text-2xl mb-2">📝</p>
        <p class="font-semibold text-gray-800">My Leaves</p>
        <p class="text-sm text-gray-500 mt-1">File and track leave applications</p>
      </a>
      <a href="/gate-passes" class="bg-white rounded-xl shadow p-6 hover:shadow-md transition-shadow">
        <p class="text-2xl mb-2">🚪</p>
        <p class="font-semibold text-gray-800">Gate Pass</p>
        <p class="text-sm text-gray-500 mt-1">File official business/personal gate pass</p>
      </a>
    </div>
  </AppLayout>
</template>
