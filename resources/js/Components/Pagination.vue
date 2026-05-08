<script setup>
/**
 * Reusable pagination component.
 *
 * Props:
 *   currentPage - Current active page (1-indexed)
 *   totalPages  - Total number of pages
 *   totalItems  - Total number of items (optional, for display)
 *   window      - Number of page buttons to show around current (default: 2)
 */
const props = defineProps({
  currentPage: { type: Number, required: true },
  totalPages:  { type: Number, required: true },
  totalItems:  { type: Number, default: 0 },
  window:      { type: Number, default: 2 },
})

const emit = defineEmits(['update:currentPage'])

function goTo(page) {
  if (page >= 1 && page <= props.totalPages) {
    emit('update:currentPage', page)
  }
}

function shouldShow(page) {
  return Math.abs(page - props.currentPage) <= props.window
    || page === 1
    || page === props.totalPages
}

function isEllipsis(page) {
  return Math.abs(page - props.currentPage) === props.window + 1
    && page !== 1
    && page !== props.totalPages
}
</script>

<template>
  <div v-if="totalPages > 1" class="flex items-center justify-between px-1">
    <p class="text-xs text-gray-500">
      Page {{ currentPage }} of {{ totalPages }}
      <template v-if="totalItems">
        &nbsp;·&nbsp; {{ totalItems }} result{{ totalItems !== 1 ? 's' : '' }}
      </template>
    </p>

    <div class="flex items-center gap-1">
      <button @click="goTo(1)" :disabled="currentPage === 1"
        class="px-2 py-1 text-xs rounded border border-gray-300 disabled:opacity-40 hover:bg-gray-50"
        aria-label="First page">
        ««
      </button>
      <button @click="goTo(currentPage - 1)" :disabled="currentPage === 1"
        class="px-2 py-1 text-xs rounded border border-gray-300 disabled:opacity-40 hover:bg-gray-50"
        aria-label="Previous page">
        ‹
      </button>

      <template v-for="p in totalPages" :key="p">
        <button v-if="shouldShow(p)"
          @click="goTo(p)"
          class="px-2.5 py-1 text-xs rounded border transition-colors"
          :class="p === currentPage
            ? 'bg-blue-600 text-white border-blue-600'
            : 'border-gray-300 hover:bg-gray-50'"
          :aria-current="p === currentPage ? 'page' : undefined">
          {{ p }}
        </button>
        <span v-else-if="isEllipsis(p)" class="px-1 text-gray-400 text-xs">…</span>
      </template>

      <button @click="goTo(currentPage + 1)" :disabled="currentPage === totalPages"
        class="px-2 py-1 text-xs rounded border border-gray-300 disabled:opacity-40 hover:bg-gray-50"
        aria-label="Next page">
        ›
      </button>
      <button @click="goTo(totalPages)" :disabled="currentPage === totalPages"
        class="px-2 py-1 text-xs rounded border border-gray-300 disabled:opacity-40 hover:bg-gray-50"
        aria-label="Last page">
        »»
      </button>
    </div>
  </div>
</template>
