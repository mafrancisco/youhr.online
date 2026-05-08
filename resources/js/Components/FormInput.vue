<script setup>
/**
 * Reusable form input with label and error display.
 *
 * Props:
 *   label       - Field label text
 *   modelValue  - v-model binding
 *   type        - Input type (text, email, number, date, time, password, textarea)
 *   error       - Error message string
 *   placeholder - Placeholder text
 *   required    - Whether field is required
 *   disabled    - Whether field is disabled
 *   rows        - Textarea rows (only for type="textarea")
 */
const props = defineProps({
  label:       { type: String, default: '' },
  modelValue:  { type: [String, Number], default: '' },
  type:        { type: String, default: 'text' },
  error:       { type: String, default: '' },
  placeholder: { type: String, default: '' },
  required:    { type: Boolean, default: false },
  disabled:    { type: Boolean, default: false },
  rows:        { type: Number, default: 3 },
})

const emit = defineEmits(['update:modelValue'])

function onInput(e) {
  emit('update:modelValue', e.target.value)
}
</script>

<template>
  <div>
    <label v-if="label" class="block text-xs font-medium text-gray-700 mb-1">
      {{ label }}
      <span v-if="required" class="text-red-400">*</span>
    </label>

    <textarea v-if="type === 'textarea'"
      :value="modelValue"
      @input="onInput"
      :placeholder="placeholder"
      :required="required"
      :disabled="disabled"
      :rows="rows"
      class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 disabled:bg-gray-100 disabled:cursor-not-allowed"
      :class="error ? 'border-red-400' : 'border-gray-300'"
    />

    <input v-else
      :type="type"
      :value="modelValue"
      @input="onInput"
      :placeholder="placeholder"
      :required="required"
      :disabled="disabled"
      class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 disabled:bg-gray-100 disabled:cursor-not-allowed"
      :class="error ? 'border-red-400' : 'border-gray-300'"
    />

    <p v-if="error" class="text-xs text-red-500 mt-1">{{ error }}</p>
  </div>
</template>
