<script setup lang="ts">
definePageMeta({ middleware: 'guest' })

const auth = useAuthStore()
const route = useRoute()

// Landing "Start with Pro" sends people here with ?plan=pro: after the
// account exists, settings opens the Paddle checkout for them.
const wantsPro = computed(() => route.query.plan === 'pro')

const name = ref('')
const email = ref('')
const password = ref('')
const errorMessage = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const err = (field: string) => fieldErrors.value[field]?.[0]

const onSubmit = async () => {
  errorMessage.value = ''
  fieldErrors.value = {}
  try {
    await auth.register(name.value, email.value, password.value)
    await navigateTo(wantsPro.value ? '/app/settings?upgrade=pro' : '/app')
  } catch (e: any) {
    fieldErrors.value = apiFieldErrors(e)
    if (!Object.keys(fieldErrors.value).length) {
      errorMessage.value = apiErrorMessage(e, 'Could not create your account. Try again.')
    }
  }
}
</script>

<template>
  <div class="max-w-site mx-auto px-4 sm:px-6 py-16 sm:py-24">
    <div class="max-w-sm mx-auto">
      <h1 class="text-2xl font-semibold mb-1">Create your account</h1>
      <p class="text-[13.5px] text-mt-muted mb-8">
        {{ wantsPro ? 'Pro checkout opens right after your account is created.' : 'Free plan: 1 server, checks every 15 minutes.' }}
      </p>

      <form class="space-y-4" @submit.prevent="onSubmit">
        <div>
          <label class="mt-label" for="name">Name</label>
          <input id="name" v-model="name" type="text" required autocomplete="name" class="mt-input">
          <p v-if="err('name')" class="mt-1.5 text-[12px] text-mt-down">{{ err('name') }}</p>
        </div>
        <div>
          <label class="mt-label" for="email">Email</label>
          <input id="email" v-model="email" type="email" required autocomplete="email" class="mt-input">
          <p v-if="err('email')" class="mt-1.5 text-[12px] text-mt-down">{{ err('email') }}</p>
        </div>
        <div>
          <label class="mt-label" for="password">Password</label>
          <input id="password" v-model="password" type="password" required autocomplete="new-password" minlength="8" class="mt-input">
          <p v-if="err('password')" class="mt-1.5 text-[12px] text-mt-down">{{ err('password') }}</p>
        </div>

        <p v-if="errorMessage" class="rounded-md border border-mt-down/25 bg-[var(--mt-down-dim)] px-3 py-2.5 text-[13px] text-mt-text">
          {{ errorMessage }}
        </p>

        <button type="submit" class="mt-btn-primary w-full" :disabled="auth.loading">
          {{ auth.loading ? 'Creating account…' : 'Create account' }}
        </button>
      </form>

      <p class="mt-6 text-[13.5px] text-mt-muted">
        Already have an account?
        <NuxtLink to="/login" class="text-mt-up hover:underline font-medium">Sign in</NuxtLink>
      </p>
    </div>
  </div>
</template>
