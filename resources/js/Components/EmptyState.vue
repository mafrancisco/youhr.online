<script setup>
/**
 * Reusable empty state placeholder for tables and lists.
 *
 * Props:
 *   icon    - Which icon to show: 'search', 'list', 'inbox' (default: 'inbox')
 *   title   - Main message
 *   message - Secondary description
 */
defineProps({
  icon:    { type: String, default: 'inbox' },
  title:   { type: String, default: 'No data' },
  message: { type: String, default: '' },
})
</script>

<template>
  <div class="flex flex-col items-center justify-center py-12 text-center">
    <!-- Inbox icon -->
    <svg v-if="icon === 'inbox'" class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
        d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
    </svg>
    <!-- Search icon -->
    <svg v-else-if="icon === 'search'" class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
    </svg>
    <!-- List icon -->
    <svg v-else class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
    </svg>

    <p class="text-sm font-medium text-gray-500">{{ title }}</p>
    <p v-if="message" class="text-xs text-gray-400 mt-1">{{ message }}</p>

    <div v-if="$slots.action" class="mt-4">
      <slot name="action" />
    </div>
  </div>
</template>
