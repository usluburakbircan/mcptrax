<script setup lang="ts">
defineProps<{ message?: string }>()
</script>

<template>
  <div class="rounded-md border border-mt-up/30 bg-[var(--mt-up-dim)] px-4 py-4">
    <p class="text-[13.5px] font-medium text-mt-text">{{ message || 'This needs the Pro plan.' }}</p>
    <p class="mt-1 text-[12.5px] text-mt-muted">
      Pro: unlimited servers, 1-minute checks, synthetic tool calls, Slack &amp; webhook alerts.
    </p>
    <UpgradeButtons class="mt-3" />
  </div>
</template>
