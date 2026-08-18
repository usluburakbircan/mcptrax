<script setup lang="ts">
import { GUIDES } from '#shared/guides'

useSeo({
  title: 'MCP Monitoring Guides — mcptrax',
  description: 'Practical, technically deep guides on running MCP servers in production: monitoring, initialize failures, transports, tool drift, and health checks.',
  path: '/guides',
})
</script>

<template>
  <div class="max-w-site mx-auto px-4 sm:px-6 py-12 sm:py-16">
    <div class="max-w-2xl">
      <p class="mt-eyebrow mb-3">Guides</p>
      <h1 class="text-3xl sm:text-4xl font-semibold leading-tight mb-3">Running MCP servers in production</h1>
      <p class="text-[15px] text-mt-muted leading-relaxed">
        What we've learned building an MCP monitoring service — the failure modes, the protocol details,
        and the checks that actually catch problems.
      </p>
    </div>

    <ul class="mt-10 max-w-2xl space-y-3">
      <li v-for="g in GUIDES" :key="g.slug">
        <NuxtLink
          :to="`/guides/${g.slug}`"
          class="mt-card group block px-5 sm:px-6 py-5 transition hover:border-mt-border hover:bg-mt-soft/60"
        >
          <h2 class="text-[16.5px] font-semibold text-mt-text group-hover:text-mt-up transition">{{ g.title }}</h2>
          <p class="mt-1.5 text-[13.5px] leading-relaxed text-mt-muted">{{ g.description }}</p>
          <p class="mt-2.5 font-mono text-[11.5px] text-mt-faint">{{ g.readMinutes }} min read</p>
        </NuxtLink>
      </li>
    </ul>
  </div>
</template>
