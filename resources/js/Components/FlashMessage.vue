<script setup>
import { computed, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const visible = ref(false)
const message = ref('')
const type = ref('success')

watch(() => page.props.flash, (flash) => {
    if (flash.success) { message.value = flash.success; type.value = 'success'; visible.value = true }
    else if (flash.error) { message.value = flash.error; type.value = 'error'; visible.value = true }
    if (visible.value) setTimeout(() => visible.value = false, 4000)
}, { immediate: true, deep: true })
</script>

<template>
  <Transition enter-active-class="transition ease-out duration-300" enter-from-class="opacity-0 -translate-y-2"
    enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-200"
    leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 -translate-y-2">
    <div v-if="visible" class="mb-4 px-4 py-3 rounded-lg text-sm font-medium"
      :class="type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
      {{ message }}
    </div>
  </Transition>
</template>
