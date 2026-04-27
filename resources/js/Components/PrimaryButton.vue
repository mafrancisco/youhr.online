<script setup>
defineProps({
  variant: { type: String, default: 'primary' },
  loading: { type: Boolean, default: false },
  type: { type: String, default: 'button' },
})

const colors = {
  primary: 'bg-blue-600 hover:bg-blue-700 text-white',
  success: 'bg-green-600 hover:bg-green-700 text-white',
  danger:  'bg-red-600 hover:bg-red-700 text-white',
  warning: 'bg-amber-500 hover:bg-amber-600 text-white',
  ghost:   'bg-white hover:bg-gray-50 text-gray-700 border border-gray-300',
}
</script>

<template>
  <button :type="type" :disabled="loading"
    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
    :class="colors[variant] ?? colors.primary">
    <svg v-if="loading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
    </svg>
    <slot />
  </button>
</template>
