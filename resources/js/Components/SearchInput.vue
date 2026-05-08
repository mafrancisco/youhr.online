<script setup>
/**
 * Reusable search input with icon and clear button.
 *
 * Props:
 *   modelValue  - v-model binding
 *   placeholder - Placeholder text
 *   class       - Additional CSS classes for the wrapper
 */
defineProps({
  modelValue:  { type: String, default: '' },
  placeholder: { type: String, default: 'Search…' },
})

const emit = defineEmits(['update:modelValue'])

function onInput(e) {
  emit('update:modelValue', e.target.value)
}

function clear() {
  emit('update:modelValue', '')
}
</script>

<template>
  <div class="relative">
    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
      fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
    </svg>
    <input
      type="search"
      :value="modelValue"
      @input="onInput"
      :placeholder="placeholder"
      class="w-full border border-gray-300 rounded-lg pl-9 pr-8 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400"
    />
    <button v-if="modelValue" @click="clear"
      class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
      aria-label="Clear search">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>
  </div>
</template>
