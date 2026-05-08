<script setup>
/**
 * Reusable select dropdown with label and error display.
 *
 * Props:
 *   label       - Field label text
 *   modelValue  - v-model binding
 *   options     - Array of { value, label } or simple values
 *   error       - Error message string
 *   placeholder - Placeholder option text (shown as first disabled option)
 *   required    - Whether field is required
 *   disabled    - Whether field is disabled
 *   valueKey    - Key for option value when options are objects (default: 'value')
 *   labelKey    - Key for option label when options are objects (default: 'label')
 */
const props = defineProps({
  label:       { type: String, default: '' },
  modelValue:  { type: [String, Number], default: '' },
  options:     { type: Array, required: true },
  error:       { type: String, default: '' },
  placeholder: { type: String, default: '— Select —' },
  required:    { type: Boolean, default: false },
  disabled:    { type: Boolean, default: false },
  valueKey:    { type: String, default: 'value' },
  labelKey:    { type: String, default: 'label' },
})

const emit = defineEmits(['update:modelValue'])

function onChange(e) {
  emit('update:modelValue', e.target.value)
}

function optionValue(opt) {
  if (typeof opt === 'object' && opt !== null) return opt[props.valueKey]
  return opt
}

function optionLabel(opt) {
  if (typeof opt === 'object' && opt !== null) return opt[props.labelKey]
  return opt
}
</script>

<template>
  <div>
    <label v-if="label" class="block text-xs font-medium text-gray-700 mb-1">
      {{ label }}
      <span v-if="required" class="text-red-400">*</span>
    </label>

    <select
      :value="modelValue"
      @change="onChange"
      :required="required"
      :disabled="disabled"
      class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 disabled:bg-gray-100 disabled:cursor-not-allowed"
      :class="error ? 'border-red-400' : 'border-gray-300'"
    >
      <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
      <option v-for="opt in options" :key="optionValue(opt)" :value="optionValue(opt)">
        {{ optionLabel(opt) }}
      </option>
    </select>

    <p v-if="error" class="text-xs text-red-500 mt-1">{{ error }}</p>
  </div>
</template>
