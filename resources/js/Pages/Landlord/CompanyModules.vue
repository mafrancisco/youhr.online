<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import FlashMessage from '@/Components/FlashMessage.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const props = defineProps({
  company: Object,
  modules: Array,
})

const form = useForm({
  modules: props.modules.filter(m => m.enabled).map(m => m.key),
})

function toggle(moduleKey) {
  if (form.modules.includes(moduleKey)) {
    form.modules = form.modules.filter(k => k !== moduleKey)
  } else {
    form.modules.push(moduleKey)
  }
}

function selectAll() {
  form.modules = props.modules.map(m => m.key)
}

function deselectAll() {
  form.modules = []
}

function submit() {
  form.put(`/landlord/companies/${props.company.id}/modules`)
}
</script>

<template>
  <div class="min-h-screen bg-slate-950 text-slate-100">
    <FlashMessage />

    <div class="mx-auto max-w-4xl px-6 py-8 space-y-8">
      <!-- Header -->
      <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-2xl">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <p class="text-xs uppercase tracking-[0.35em] text-cyan-300">Landlord Console</p>
            <h1 class="mt-2 text-3xl font-semibold text-white">Module Access</h1>
            <p class="mt-1 text-sm text-slate-300">
              Configure which modules are available for
              <span class="font-semibold text-cyan-200">{{ company.name }}</span>
              ({{ company.slug }})
            </p>
          </div>
          <Link href="/landlord" class="rounded-xl border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800 hover:text-white transition">
            ← Back to Dashboard
          </Link>
        </div>
      </section>

      <!-- Module Toggles -->
      <section class="rounded-3xl bg-white p-6 text-slate-900 shadow-xl">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-lg font-semibold">Enabled Modules</h2>
          <div class="flex gap-2">
            <button
              type="button"
              class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-100 transition"
              @click="selectAll"
            >
              Select All
            </button>
            <button
              type="button"
              class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-100 transition"
              @click="deselectAll"
            >
              Deselect All
            </button>
          </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
          <label
            v-for="mod in modules"
            :key="mod.key"
            class="flex items-center gap-3 rounded-xl border p-4 cursor-pointer transition"
            :class="form.modules.includes(mod.key)
              ? 'border-cyan-500 bg-cyan-50'
              : 'border-slate-200 bg-slate-50 hover:border-slate-300'"
          >
            <input
              type="checkbox"
              :value="mod.key"
              :checked="form.modules.includes(mod.key)"
              class="h-5 w-5 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500"
              @change="toggle(mod.key)"
            />
            <div>
              <p class="font-medium text-slate-900">{{ mod.label }}</p>
              <p class="text-xs text-slate-500">{{ mod.key }}</p>
            </div>
          </label>
        </div>

        <div class="mt-6 flex items-center justify-between border-t border-slate-200 pt-6">
          <p class="text-sm text-slate-500">
            {{ form.modules.length }} of {{ modules.length }} modules enabled
          </p>
          <PrimaryButton
            @click="submit"
            :disabled="form.processing"
          >
            {{ form.processing ? 'Saving...' : 'Save Changes' }}
          </PrimaryButton>
        </div>

        <p v-if="form.errors.modules" class="mt-2 text-sm text-red-600">{{ form.errors.modules }}</p>
      </section>
    </div>
  </div>
</template>
