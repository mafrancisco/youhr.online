<script setup>
import { ref, computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'

defineProps({ title: String })

const page      = usePage()
const auth      = computed(() => page.props.auth)
const settings  = computed(() => page.props.settings ?? {})
const pending   = computed(() => page.props.pendingLeaves ?? 0)
const pendingGP = computed(() => page.props.pendingGatePasses ?? 0)
const sideOpen  = ref(true)

const systemName = computed(() => settings.value.system_name || 'DTR System')
const logoUrl    = computed(() => settings.value.logo_url || '')

const hrNav = computed(() => [
  { href: '/dashboard',         label: 'Dashboard',       icon: '⊞', badge: 0 },
  { href: '/employees',         label: 'Employees',       icon: '👥', badge: 0 },
  { href: '/schedules',         label: 'Schedules',       icon: '📅', badge: 0 },
  { href: '/attendance/upload', label: 'Import DTR',      icon: '📤', badge: 0 },
  { href: '/reports/dtr',       label: 'DTR Reports',     icon: '📄', badge: 0 },
  { href: '/holidays',          label: 'Holidays',        icon: '🗓', badge: 0 },
  { href: '/admin/leaves',      label: 'Pending Leaves',  icon: '📋', badge: pending.value },
  { href: '/admin/gate-passes', label: 'Gate Passes',     icon: '🚪', badge: pendingGP.value },
  { href: '/credits',           label: 'Leave Credits',   icon: '💳', badge: 0 },
  { href: '/users',             label: 'Users',           icon: '👤', badge: 0 },
  { href: '/admin/divisions',           label: 'Divisions',          icon: '🏢', badge: 0 },
  { href: '/admin/employee-statuses',  label: 'Employee Statuses',  icon: '📊', badge: 0 },
  { href: '/admin/leave-types',       label: 'Leave Types',        icon: '📑', badge: 0 },
  { href: '/admin/time-detection',   label: 'Time Detection',     icon: '⏱', badge: 0 },
  { href: '/biometric/devices',        label: 'Biometric Devices',  icon: '🖐', badge: 0 },
  { href: '/admin/settings',    label: 'Settings',        icon: '⚙️', badge: 0 },
])

const empNav = [
  { href: '/dashboard',   label: 'Dashboard',  icon: '⊞', badge: 0 },
  { href: '/dtr',         label: 'My DTR',     icon: '📋', badge: 0 },
  { href: '/leaves',      label: 'My Leaves',  icon: '📝', badge: 0 },
  { href: '/gate-passes', label: 'Gate Pass',  icon: '🚪', badge: 0 },
]

const navItems = computed(() => auth.value?.isHR ? hrNav.value : empNav)

function isActive(href) {
  return page.url.startsWith(href) && href !== '/dashboard'
    ? true
    : page.url === href
}

function logout() {
  router.post('/logout')
}
</script>

<template>
  <div class="min-h-screen flex bg-gray-100">
    <!-- Sidebar -->
    <aside class="w-64 bg-blue-900 text-white flex flex-col shrink-0">
      <div class="px-4 py-5 border-b border-blue-800 flex items-center gap-3">
        <img v-if="logoUrl" :src="logoUrl" class="h-8 w-8 rounded object-contain bg-white p-0.5" alt="logo" />
        <div>
          <p class="text-sm font-bold leading-tight">{{ systemName }}</p>
          <p class="text-xs text-blue-300 mt-0.5">{{ auth?.isHR ? 'HR Officer' : 'Employee' }}</p>
        </div>
      </div>

      <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        <Link v-for="item in navItems" :key="item.href" :href="item.href"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
          :class="isActive(item.href) ? 'bg-blue-700 text-white' : 'text-blue-100 hover:bg-blue-800'">
          <span class="w-5 text-center text-base">{{ item.icon }}</span>
          <span class="flex-1">{{ item.label }}</span>
          <span v-if="item.badge > 0"
            class="bg-red-500 text-white text-xs font-bold rounded-full px-1.5 py-0.5 min-w-[1.25rem] text-center">
            {{ item.badge }}
          </span>
        </Link>
      </nav>

      <div class="px-3 py-4 border-t border-blue-800">
        <p class="text-xs text-blue-300 truncate mb-2">{{ auth?.fullname }}</p>
        <button @click="logout"
          class="w-full text-left px-3 py-2 rounded-lg text-sm text-blue-100 hover:bg-blue-800 transition-colors">
          Sign Out
        </button>
      </div>
    </aside>

    <!-- Main -->
    <div class="flex-1 flex flex-col min-w-0">
      <header class="bg-white border-b px-6 py-4 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-gray-800">
          <slot name="header">{{ title }}</slot>
        </h1>
        <span class="text-sm text-gray-500">{{ auth?.fullname }}</span>
      </header>

      <main class="flex-1 p-6">
        <slot />
      </main>
    </div>
  </div>
</template>
