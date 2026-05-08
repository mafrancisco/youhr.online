<script setup>
/**
 * Reusable page header with title, subtitle, and action slot.
 *
 * Props:
 *   title    - Page heading text
 *   subtitle - Optional subtitle/count text
 */
defineProps({
  title:    { type: String, required: true },
  subtitle: { type: String, default: '' },
})
</script>

<template>
  <div class="flex flex-wrap items-center gap-3 mb-5">
    <div class="mr-auto">
      <h2 class="text-base font-semibold text-gray-700">
        {{ title }}
        <span v-if="subtitle" class="text-gray-400 font-normal text-sm">{{ subtitle }}</span>
      </h2>
    </div>
    <slot />
  </div>
</template>
