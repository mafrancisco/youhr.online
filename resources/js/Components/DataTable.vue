<script setup>
/**
 * Reusable data table with consistent styling.
 *
 * Props:
 *   columns - Array of { key, label, class? } defining table headers
 *   rows    - Array of data objects
 *   rowKey  - String key to use for :key binding (default: 'id')
 *
 * Slots:
 *   cell(column.key) - Custom cell rendering, receives { row, value }
 *   actions          - Row action buttons, receives { row }
 *   empty            - Empty state content
 */
defineProps({
  columns: { type: Array, required: true },
  rows:    { type: Array, required: true },
  rowKey:  { type: String, default: 'id' },
  striped: { type: Boolean, default: false },
})
</script>

<template>
  <div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
          <tr>
            <th v-for="col in columns" :key="col.key"
              class="px-4 py-3 text-left" :class="col.headerClass">
              {{ col.label }}
            </th>
            <th v-if="$slots.actions" class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="(row, index) in rows" :key="row[rowKey] ?? index"
            class="hover:bg-gray-50" :class="striped && index % 2 ? 'bg-gray-50/50' : ''">
            <td v-for="col in columns" :key="col.key" class="px-4 py-3" :class="col.cellClass">
              <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                {{ row[col.key] ?? '—' }}
              </slot>
            </td>
            <td v-if="$slots.actions" class="px-4 py-3 text-right whitespace-nowrap">
              <slot name="actions" :row="row" />
            </td>
          </tr>
          <tr v-if="!rows.length">
            <td :colspan="columns.length + ($slots.actions ? 1 : 0)"
              class="px-4 py-8 text-center text-gray-400">
              <slot name="empty">No data available.</slot>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
