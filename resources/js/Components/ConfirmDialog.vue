<script setup>
/**
 * Styled confirmation dialog to replace native confirm().
 *
 * Props:
 *   show    - Whether the dialog is visible
 *   title   - Dialog title
 *   message - Confirmation message
 *   confirmLabel - Text for confirm button (default: 'Confirm')
 *   cancelLabel  - Text for cancel button (default: 'Cancel')
 *   variant      - Confirm button variant: 'danger' | 'primary' | 'warning'
 */
defineProps({
  show:         { type: Boolean, default: false },
  title:        { type: String, default: 'Confirm Action' },
  message:      { type: String, default: 'Are you sure you want to proceed?' },
  confirmLabel: { type: String, default: 'Confirm' },
  cancelLabel:  { type: String, default: 'Cancel' },
  variant:      { type: String, default: 'danger' },
})

const emit = defineEmits(['confirm', 'cancel'])

const variantClasses = {
  danger:  'bg-red-600 hover:bg-red-700 text-white',
  primary: 'bg-blue-600 hover:bg-blue-700 text-white',
  warning: 'bg-amber-500 hover:bg-amber-600 text-white',
}
</script>

<template>
  <Teleport to="body">
    <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0"
      enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100" leave-to-class="opacity-0">
      <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="emit('cancel')" />
        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
          <div class="flex items-start gap-3 mb-4">
            <div class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center"
              :class="variant === 'danger' ? 'bg-red-100' : 'bg-blue-100'">
              <svg class="w-5 h-5" :class="variant === 'danger' ? 'text-red-600' : 'text-blue-600'"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path v-if="variant === 'danger'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div>
              <h3 class="text-base font-semibold text-gray-900">{{ title }}</h3>
              <p class="text-sm text-gray-500 mt-1">{{ message }}</p>
            </div>
          </div>
          <div class="flex justify-end gap-3">
            <button @click="emit('cancel')"
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
              {{ cancelLabel }}
            </button>
            <button @click="emit('confirm')"
              class="px-4 py-2 text-sm font-medium rounded-lg transition-colors"
              :class="variantClasses[variant] ?? variantClasses.danger">
              {{ confirmLabel }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
