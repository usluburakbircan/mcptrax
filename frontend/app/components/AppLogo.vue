<script setup lang="ts">
withDefaults(defineProps<{ to?: string }>(), { to: '/' })
</script>

<template>
  <NuxtLink :to="to" class="inline-flex items-center gap-2 select-none">
    <svg viewBox="0 0 32 32" class="w-[22px] h-[22px]" aria-hidden="true">
      <rect width="32" height="32" rx="7" class="fill-mt-soft" />
      <path
        d="M7 21 L13 21 L16 12 L19 21 L25 21"
        fill="none" stroke="var(--mt-up)" stroke-width="2.5"
        stroke-linecap="round" stroke-linejoin="round"
      />
    </svg>
    <span class="font-mono text-[15px] font-medium tracking-tight text-mt-text">mcptrax</span>
  </NuxtLink>
</template>
