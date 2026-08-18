<script setup lang="ts">
const auth = useAuthStore()
const route = useRoute()

const onLogout = () => auth.logout()

const menuOpen = ref(false)
watch(() => route.fullPath, () => { menuOpen.value = false })

const links = [
  { to: '/app', label: 'Monitors' },
  { to: '/app/settings', label: 'Alerts & settings' },
]

const isActive = (to: string) =>
  to === '/app' ? route.path === '/app' || route.path.startsWith('/app/monitors') : route.path.startsWith(to)
</script>

<template>
  <div class="min-h-full flex flex-col">
    <header class="border-b border-mt-border-soft bg-mt-raised/60 backdrop-blur sticky top-0 z-40">
      <div class="max-w-site mx-auto px-4 sm:px-6 h-14 flex items-center justify-between gap-3">
        <div class="flex items-center gap-8">
          <AppLogo to="/app" />
          <nav class="hidden md:flex items-center gap-1">
            <NuxtLink
              v-for="l in links" :key="l.to" :to="l.to"
              class="px-3 py-1.5 rounded-md text-[13px] transition"
              :class="isActive(l.to) ? 'text-mt-text bg-mt-soft' : 'text-mt-muted hover:text-mt-text'"
            >{{ l.label }}</NuxtLink>
          </nav>
        </div>

        <div class="hidden md:flex items-center gap-4">
          <span class="font-mono text-[12px] text-mt-faint hidden lg:inline">{{ auth.user?.email }}</span>
          <button class="text-[13px] text-mt-muted hover:text-mt-text transition" @click="onLogout">Sign out</button>
        </div>

        <button
          type="button"
          class="md:hidden grid place-items-center w-9 h-9 -mr-1.5 rounded-md text-mt-muted hover:text-mt-text"
          aria-label="Menu"
          @click="menuOpen = !menuOpen"
        >
          <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
            <path v-if="!menuOpen" d="M4 7h16M4 12h16M4 17h16" />
            <path v-else d="M6 6l12 12M18 6L6 18" />
          </svg>
        </button>
      </div>

      <div v-if="menuOpen" class="md:hidden border-t border-mt-border-soft bg-mt-raised px-4 py-3 space-y-1">
        <NuxtLink
          v-for="l in links" :key="l.to" :to="l.to"
          class="block px-3 py-2 rounded-md text-[14px]"
          :class="isActive(l.to) ? 'text-mt-text bg-mt-soft' : 'text-mt-muted'"
        >{{ l.label }}</NuxtLink>
        <button class="block w-full text-left px-3 py-2 text-[14px] text-mt-muted" @click="onLogout">Sign out</button>
      </div>
    </header>

    <main class="flex-1">
      <div class="max-w-site mx-auto px-4 sm:px-6 py-8">
        <slot />
      </div>
    </main>
  </div>
</template>
