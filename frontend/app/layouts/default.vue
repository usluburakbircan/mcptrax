<script setup lang="ts">
const auth = useAuthStore()
</script>

<template>
  <div class="min-h-full flex flex-col">
    <header class="border-b border-mt-border-soft">
      <div class="max-w-site mx-auto px-4 sm:px-6 h-14 flex items-center justify-between gap-3">
        <AppLogo />
        <nav class="flex items-center gap-2 sm:gap-4">
          <NuxtLink to="/guides" class="text-[13px] text-mt-muted hover:text-mt-text transition px-2 py-1.5">Guides</NuxtLink>
          <template v-if="auth.isAuthenticated">
            <NuxtLink to="/app" class="mt-btn-primary !py-[7px] !px-3.5 text-[13px]">Dashboard</NuxtLink>
          </template>
          <template v-else>
            <NuxtLink to="/login" class="text-[13px] text-mt-muted hover:text-mt-text transition px-2 py-1.5">Sign in</NuxtLink>
            <NuxtLink to="/register" class="mt-btn-primary !py-[7px] !px-3.5 text-[13px]">Start monitoring</NuxtLink>
          </template>
        </nav>
      </div>
    </header>

    <main class="flex-1">
      <slot />
    </main>

    <footer class="border-t border-mt-border-soft">
      <div class="max-w-site mx-auto px-4 sm:px-6 py-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <p class="font-mono text-[12px] text-mt-faint">mcptrax — uptime for Model Context Protocol servers</p>
        <nav class="flex items-center gap-5 text-[12px]">
          <NuxtLink to="/guides" class="text-mt-faint hover:text-mt-muted transition">Guides</NuxtLink>
          <NuxtLink to="/register" class="text-mt-faint hover:text-mt-muted transition">Start monitoring</NuxtLink>
          <span class="text-mt-faint">© {{ new Date().getFullYear() }} mcptrax</span>
        </nav>
      </div>
    </footer>
  </div>
</template>
