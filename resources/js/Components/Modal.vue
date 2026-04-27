<script setup>
const props = defineProps({
  show: Boolean,
  size: { type: String, default: 'md' },
})
const emit = defineEmits(['close'])

const sizeClass = {
  sm: 'max-w-md',
  md: 'max-w-lg',
  lg: 'max-w-2xl',
  xl: 'max-w-4xl',
}
</script>

<template>
  <Teleport to="body">
    <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0"
      enter-to-class="opacity-100" leave-active-class="transition ease-in duration-150"
      leave-from-class="opacity-100" leave-to-class="opacity-0">
      <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/50" @click="emit('close')" />
        <div class="relative bg-white rounded-xl shadow-xl w-full flex flex-col max-h-[90vh]"
          :class="sizeClass[size] ?? sizeClass.md">
          <div class="flex items-center justify-between px-6 py-4 border-b">
            <h3 class="text-base font-semibold text-gray-900"><slot name="header" /></h3>
            <button @click="emit('close')" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
          </div>
          <div class="px-6 py-4 overflow-y-auto flex-1"><slot name="body" /></div>
          <div v-if="$slots.footer" class="px-6 py-4 border-t flex justify-end gap-3">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
