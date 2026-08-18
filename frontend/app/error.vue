<script setup lang="ts">
import type { NuxtError } from '#app'

const props = defineProps<{ error: NuxtError }>()

const is404 = computed(() => props.error.statusCode === 404)
</script>

<template>
  <div class="min-h-screen bg-mt-bg flex flex-col items-center justify-center px-6 text-center">
    <p class="font-mono text-[13px] text-mt-down mb-4">{{ error.statusCode }}</p>
    <h1 class="text-2xl font-semibold mb-2">
      {{ is404 ? 'Page not found' : 'Something went wrong' }}
    </h1>
    <p class="text-mt-muted text-[14px] mb-8 max-w-sm">
      {{ is404 ? 'The page you are looking for does not exist or has moved.' : 'An unexpected error occurred. Try again in a moment.' }}
    </p>
    <button class="mt-btn-primary" @click="clearError({ redirect: '/' })">Back to home</button>
  </div>
</template>
