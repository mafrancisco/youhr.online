<script setup>
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { FlashMessage, PrimaryButton, PageHeader } from '@/Components'

const props = defineProps({ settings: Array })

const forms = props.settings.map(s => useForm({
  before_minutes: s.before_minutes,
  after_minutes:  s.after_minutes,
  pick_rule:      s.pick_rule,
}))

function save(index) {
  const setting = props.settings[index]
  forms[index].put(`/admin/time-detection/${setting.id}`)
}

function formatRange(before, after) {
  const fmtBefore = before >= 60 ? `${Math.floor(before/60)}h ${before%60}m` : `${before}m`
  const fmtAfter = after >= 60 ? `${Math.floor(after/60)}h ${after%60}m` : `${after}m`
  return `${fmtBefore} before → ${fmtAfter} after`
}
</script>

<template>
  <AppLayout title="Time Detection Rules">
    <FlashMessage />

    <PageHeader title="Attendance Time Log Detection Rules">
    </PageHeader>

    <p class="text-sm text-gray-500 mb-6 -mt-3">
      Configure how the system detects which biometric punch corresponds to Time In, Break Out, Break In, Time Out, and Overtime.
      The detection window defines how far before/after the scheduled time a punch is considered valid.
    </p>

    <div class="space-y-4">
      <div v-for="(setting, index) in settings" :key="setting.id"
        class="bg-white rounded-xl shadow p-5">
        <div class="flex items-start justify-between mb-3">
          <div>
            <h3 class="text-sm font-bold text-gray-900">{{ setting.label }}</h3>
            <p class="text-xs text-gray-400 mt-0.5" v-if="setting.punch_type !== 'otin'">
              Current window: {{ formatRange(forms[index].before_minutes, forms[index].after_minutes) }}
              · Pick: <span class="font-medium">{{ forms[index].pick_rule }}</span> log
            </p>
            <p class="text-xs text-gray-400 mt-0.5" v-else>
              OT starts {{ forms[index].before_minutes }} minutes after scheduled Time Out
            </p>
          </div>
          <span class="px-2 py-0.5 rounded text-xs font-mono bg-gray-100 text-gray-600">{{ setting.punch_type }}</span>
        </div>

        <!-- Regular detection window settings -->
        <div v-if="setting.punch_type !== 'otin'" class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Before (minutes)</label>
            <input v-model.number="forms[index].before_minutes" type="number" min="0" max="720"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
            <p class="text-xs text-gray-400 mt-0.5">
              {{ forms[index].before_minutes >= 60 ? Math.floor(forms[index].before_minutes/60) + 'h ' + forms[index].before_minutes%60 + 'm' : forms[index].before_minutes + ' min' }}
              before scheduled time
            </p>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">After (minutes)</label>
            <input v-model.number="forms[index].after_minutes" type="number" min="0" max="720"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
            <p class="text-xs text-gray-400 mt-0.5">
              {{ forms[index].after_minutes >= 60 ? Math.floor(forms[index].after_minutes/60) + 'h ' + forms[index].after_minutes%60 + 'm' : forms[index].after_minutes + ' min' }}
              after scheduled time
            </p>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Pick Rule</label>
            <select v-model="forms[index].pick_rule"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
              <option value="earliest">Earliest log</option>
              <option value="latest">Latest log</option>
            </select>
            <p class="text-xs text-gray-400 mt-0.5">
              If multiple punches in window
            </p>
          </div>
        </div>

        <!-- OT threshold setting (simpler) -->
        <div v-else class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Minutes after scheduled Time Out</label>
            <input v-model.number="forms[index].before_minutes" type="number" min="0" max="720"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
            <p class="text-xs text-gray-400 mt-0.5">
              Set to 0 for OT to start immediately after scheduled timeout.
              <br>Example: If timeout is 5:00 PM and offset is 60, OT starts at 6:00 PM.
            </p>
          </div>
          <div class="flex items-center">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-xs text-blue-700">
              <p class="font-medium mb-1">How OT is calculated:</p>
              <p>OT = Actual Time Out − (Scheduled Time Out + Offset)</p>
              <p class="mt-1">Example: Timeout 5:00 PM, offset 60 min, actual out 7:30 PM</p>
              <p>OT = 7:30 PM − 6:00 PM = <strong>90 minutes</strong></p>
            </div>
          </div>
        </div>

        <div class="flex justify-end mt-3">
          <PrimaryButton @click="save(index)" :loading="forms[index].processing" class="text-xs">
            Save
          </PrimaryButton>
        </div>
      </div>
    </div>

    <!-- Help Section -->
    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-xl p-4 text-xs text-blue-700 space-y-2">
      <p class="font-semibold">How Detection Works:</p>
      <ul class="space-y-1 ml-4 list-disc">
        <li><strong>Time In:</strong> Earliest punch within the window before/after scheduled time-in</li>
        <li><strong>Break Out:</strong> Latest punch within the window (employee leaving for break)</li>
        <li><strong>Break In:</strong> Earliest punch within the window (employee returning from break)</li>
        <li><strong>Time Out:</strong> Latest punch within the window before/after scheduled time-out</li>
        <li><strong>Overtime Start Time:</strong> Any time worked beyond this threshold is counted as overtime. OT = Time Out minus OT Start Time. For example, if OT starts at 6:00 PM and employee times out at 6:30 PM, OT = 30 minutes.</li>
      </ul>
      <p class="mt-2"><strong>Example:</strong> If Time In is scheduled at 8:00 AM with 180min before / 120min after, any punch between 5:00 AM and 10:00 AM is a valid Time In candidate. The earliest one is used.</p>
    </div>
  </AppLayout>
</template>
