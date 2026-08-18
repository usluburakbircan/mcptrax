<script setup lang="ts">
const { openCheckout, opening } = usePaddle()
</script>

<template>
  <div class="flex flex-col sm:flex-row gap-2">
    <button type="button" class="mt-btn-primary" :disabled="opening" @click="openCheckout('monthly')">
      {{ opening ? 'Opening checkout…' : 'Upgrade to Pro — $20/mo' }}
    </button>
    <button type="button" class="mt-btn-ghost" :disabled="opening" @click="openCheckout('yearly')">
      $192/yr · 2 months free
    </button>
  </div>
</template>
