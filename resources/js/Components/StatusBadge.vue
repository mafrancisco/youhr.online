<script setup>
/**
 * Status badge with predefined color mappings for common statuses.
 * Falls back to the base Badge component for custom colors.
 *
 * Props:
 *   status - Status string (Approved, Pending, Cancelled, Rejected, Active, Inactive, etc.)
 *   color  - Override color (green, red, yellow, blue, gray)
 */
const props = defineProps({
  status: { type: String, default: '' },
  color:  { type: String, default: '' },
})

const statusColors = {
  approved:  'bg-green-100 text-green-800',
  active:    'bg-green-100 text-green-800',
  pending:   'bg-yellow-100 text-yellow-800',
  processing:'bg-yellow-100 text-yellow-800',
  cancelled: 'bg-red-100 text-red-800',
  rejected:  'bg-red-100 text-red-800',
  inactive:  'bg-gray-100 text-gray-700',
  expired:   'bg-gray-100 text-gray-700',
}

const colorClasses = {
  green:  'bg-green-100 text-green-800',
  red:    'bg-red-100 text-red-800',
  yellow: 'bg-yellow-100 text-yellow-800',
  blue:   'bg-blue-100 text-blue-800',
  gray:   'bg-gray-100 text-gray-700',
  purple: 'bg-purple-100 text-purple-800',
}

function resolveClass() {
  if (props.color) return colorClasses[props.color] ?? colorClasses.gray
  const key = props.status.toLowerCase()
  return statusColors[key] ?? colorClasses.gray
}
</script>

<template>
  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" :class="resolveClass()">
    <slot>{{ status }}</slot>
  </span>
</template>
