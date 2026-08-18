<script setup lang="ts">
const auth = useAuthStore()

onMounted(() => {
  auth.initialize()
})
</script>

<template>
  <div class="min-h-full">
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
    <Toaster />
  </div>
</template>
