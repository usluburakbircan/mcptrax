<script setup lang="ts">
definePageMeta({ middleware: 'guest' })

const auth = useAuthStore()
const route = useRoute()

const email = ref('')
const password = ref('')
const errorMessage = ref('')

const onSubmit = async () => {
  errorMessage.value = ''
  try {
    await auth.login(email.value, password.value)
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/app'
    await navigateTo(redirect.startsWith('/') ? redirect : '/app')
  } catch (err: any) {
    errorMessage.value = apiErrorMessage(err, 'Sign in failed. Check your email and password.')
  }
}
</script>

<template>
  <div class="max-w-site mx-auto px-4 sm:px-6 py-16 sm:py-24">
    <div class="max-w-sm mx-auto">
      <h1 class="text-2xl font-semibold mb-1">Sign in</h1>
      <p class="text-[13.5px] text-mt-muted mb-8">Back to your monitors.</p>

      <form class="space-y-4" @submit.prevent="onSubmit">
        <div>
          <label class="mt-label" for="email">Email</label>
          <input id="email" v-model="email" type="email" required autocomplete="email" class="mt-input">
        </div>
        <div>
          <label class="mt-label" for="password">Password</label>
          <input id="password" v-model="password" type="password" required autocomplete="current-password" class="mt-input">
        </div>

        <p v-if="errorMessage" class="rounded-md border border-mt-down/25 bg-[var(--mt-down-dim)] px-3 py-2.5 text-[13px] text-mt-text">
          {{ errorMessage }}
        </p>

        <button type="submit" class="mt-btn-primary w-full" :disabled="auth.loading">
          {{ auth.loading ? 'Signing in…' : 'Sign in' }}
        </button>
      </form>

      <p class="mt-6 text-[13.5px] text-mt-muted">
        No account yet?
        <NuxtLink to="/register" class="text-mt-up hover:underline font-medium">Create one</NuxtLink>
      </p>
    </div>
  </div>
</template>
