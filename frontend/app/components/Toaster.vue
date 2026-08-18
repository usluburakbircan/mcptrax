<script setup lang="ts">
const { toasts } = useToast()

const border = (type: string) =>
  type === 'success' ? 'border-l-mt-up' : type === 'error' ? 'border-l-mt-down' : 'border-l-mt-muted'
</script>

<template>
  <div class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 w-[min(92vw,340px)]" aria-live="polite">
    <TransitionGroup name="toast">
      <div
        v-for="t in toasts" :key="t.id"
        class="rounded-md border border-mt-border border-l-2 bg-mt-soft px-3.5 py-3 text-[13px] text-mt-text shadow-lg shadow-black/40"
        :class="border(t.type)"
      >
        {{ t.message }}
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: all 0.22s ease; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(6px); }
</style>
