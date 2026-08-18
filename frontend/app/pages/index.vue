<script setup lang="ts">
import type { ApiEnvelope, PublicCheckReport } from '~/types/api'
import type { BillingCycle } from '~/composables/usePaddle'

const { api } = useApi()
const auth = useAuthStore()
const { openCheckout, opening } = usePaddle()

// Logged-out visitors register first; logged-in users go straight to Paddle.
const upgrade = async (cycle: BillingCycle) => {
  if (auth.isAuthenticated) {
    await openCheckout(cycle)
  } else {
    await navigateTo('/register?plan=pro')
  }
}

const url = ref('')
const checking = ref(false)
const report = ref<PublicCheckReport | null>(null)
const checkError = ref('')

const runCheck = async () => {
  if (!url.value.trim() || checking.value) return
  checking.value = true
  checkError.value = ''
  report.value = null

  try {
    const res = await api<ApiEnvelope<{ report: PublicCheckReport }>>('/check', {
      method: 'POST',
      body: { url: url.value.trim() },
    })
    report.value = res.data.report
  } catch (err: any) {
    const status = err?.response?.status ?? err?.statusCode
    checkError.value = status === 429
      ? 'Rate limit reached. Wait a minute and try again.'
      : apiErrorMessage(err, 'Could not run the check. Verify the URL and try again.')
  } finally {
    checking.value = false
  }
}

const features = [
  {
    title: '24/7 monitoring',
    body: 'Full protocol checks on a schedule: connect, handshake, tools/list. Not just a ping — a real MCP client talks to your server.',
    icon: 'M12 3v3m0 12v3M3 12h3m12 0h3M5.6 5.6l2.2 2.2m8.4 8.4l2.2 2.2m0-12.8l-2.2 2.2M7.8 16.2l-2.2 2.2',
  },
  {
    title: 'Tool drift detection',
    body: 'A tool silently renamed or removed breaks every client that depends on it. mcptrax diffs your tool list on every check and flags drift.',
    icon: 'M8 7h12M8 12h12M8 17h12M4 7h.01M4 12h.01M4 17h.01',
  },
  {
    title: 'Instant alerts',
    body: 'Email, Slack or webhook the moment a check fails — with the failing phase and the exact error, so you fix it before anyone notices.',
    icon: 'M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
  },
]
</script>

<template>
  <div>
    <!-- Hero + checker -->
    <section class="relative overflow-hidden">
      <!-- faint scanline grid, instrument-panel texture -->
      <div
        class="pointer-events-none absolute inset-0 opacity-[0.35]"
        style="background-image: linear-gradient(var(--mt-border-soft) 1px, transparent 1px); background-size: 100% 56px;"
        aria-hidden="true"
      />

      <div class="relative max-w-site mx-auto px-4 sm:px-6 pt-16 sm:pt-24 pb-16">
        <div class="max-w-3xl">
          <p class="mt-eyebrow mb-4">MCP server monitoring</p>
          <h1 class="text-4xl sm:text-5xl md:text-[3.4rem] font-semibold leading-[1.08] mb-5">
            Know when your MCP server breaks —
            <span class="text-mt-up">before your users do.</span>
          </h1>
          <p class="text-mt-muted text-[16px] sm:text-[17px] leading-relaxed max-w-xl">
            mcptrax speaks the Model Context Protocol. It connects, completes the handshake,
            lists your tools and calls them — on a schedule — and alerts you the second anything drifts.
          </p>
        </div>

        <!-- Free checker -->
        <div class="mt-10 max-w-2xl">
          <form
            class="flex flex-col sm:flex-row gap-2 p-2 rounded-lg border border-mt-border bg-mt-raised shadow-[0_0_60px_-20px_rgba(62,232,147,0.25)]"
            @submit.prevent="runCheck"
          >
            <label for="check-url" class="sr-only">MCP server URL</label>
            <input
              id="check-url" v-model="url" type="url" required
              class="flex-1 bg-transparent px-3 py-2.5 font-mono text-[13.5px] text-mt-text placeholder:text-mt-faint outline-none min-w-0"
              placeholder="https://your-server.com/mcp"
            >
            <button type="submit" class="mt-btn-primary whitespace-nowrap" :disabled="checking">
              <span v-if="checking" class="inline-flex items-center gap-2">
                <span class="w-3.5 h-3.5 rounded-full border-2 border-[#04120a]/30 border-t-[#04120a] animate-spin" />
                Checking…
              </span>
              <span v-else>Check my MCP server</span>
            </button>
          </form>
          <p class="mt-2 font-mono text-[11.5px] text-mt-faint">Free · no signup · a real MCP handshake, not a ping</p>

          <p v-if="checkError" class="mt-4 rounded-md border border-mt-down/25 bg-[var(--mt-down-dim)] px-4 py-3 text-[13.5px] text-mt-text">
            {{ checkError }}
          </p>

          <div v-if="report" class="mt-5">
            <CheckReport :report="report" />
            <p class="mt-3 text-[13.5px] text-mt-muted">
              Want this to run every minute, forever?
              <NuxtLink to="/register" class="text-mt-up hover:underline font-medium">Start monitoring free →</NuxtLink>
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Features -->
    <section class="border-t border-mt-border-soft">
      <div class="max-w-site mx-auto px-4 sm:px-6 py-16 sm:py-20">
        <p class="mt-eyebrow mb-8">What it watches</p>
        <div class="grid gap-4 md:grid-cols-3">
          <div v-for="f in features" :key="f.title" class="mt-card p-6">
            <svg viewBox="0 0 24 24" class="w-5 h-5 text-mt-up mb-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path :d="f.icon" />
            </svg>
            <h2 class="text-[16px] font-semibold mb-2">{{ f.title }}</h2>
            <p class="text-[13.5px] leading-relaxed text-mt-muted">{{ f.body }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Pricing -->
    <section class="border-t border-mt-border-soft">
      <div class="max-w-site mx-auto px-4 sm:px-6 py-16 sm:py-20">
        <p class="mt-eyebrow mb-8">Pricing</p>
        <div class="grid gap-4 md:grid-cols-2 max-w-3xl">
          <div class="mt-card p-6 flex flex-col">
            <h2 class="text-[16px] font-semibold">Free</h2>
            <p class="mt-2 mb-5"><span class="font-mono text-3xl font-semibold">$0</span></p>
            <ul class="space-y-2.5 text-[13.5px] text-mt-muted flex-1">
              <li class="flex gap-2"><span class="text-mt-up font-mono">✓</span> 1 monitored server</li>
              <li class="flex gap-2"><span class="text-mt-up font-mono">✓</span> Checks every 15 minutes</li>
              <li class="flex gap-2"><span class="text-mt-up font-mono">✓</span> Tool drift detection</li>
              <li class="flex gap-2"><span class="text-mt-up font-mono">✓</span> Email alerts</li>
            </ul>
            <NuxtLink to="/register" class="mt-btn-ghost mt-6 w-full">Start free</NuxtLink>
          </div>

          <div class="mt-card p-6 flex flex-col border-mt-up/30 relative">
            <span class="absolute -top-2.5 left-6 rounded-full bg-mt-up px-2 py-0.5 font-mono text-[10px] font-semibold uppercase tracking-wider text-[#04120a]">Pro</span>
            <h2 class="text-[16px] font-semibold">Pro</h2>
            <p class="mt-2 mb-1"><span class="font-mono text-3xl font-semibold">$20</span><span class="text-mt-faint text-[13px]"> / month</span></p>
            <p class="mb-5 font-mono text-[11.5px] text-mt-faint">or $192/yr — 2 months free</p>
            <ul class="space-y-2.5 text-[13.5px] text-mt-muted flex-1">
              <li class="flex gap-2"><span class="text-mt-up font-mono">✓</span> Unlimited servers</li>
              <li class="flex gap-2"><span class="text-mt-up font-mono">✓</span> Checks every minute</li>
              <li class="flex gap-2"><span class="text-mt-up font-mono">✓</span> Synthetic tool calls with response assertions</li>
              <li class="flex gap-2"><span class="text-mt-up font-mono">✓</span> Slack &amp; webhook alerts</li>
            </ul>
            <div class="mt-6 space-y-2">
              <button type="button" class="mt-btn-primary w-full" :disabled="opening" @click="upgrade('monthly')">
                {{ opening ? 'Opening checkout…' : 'Start with Pro — $20/mo' }}
              </button>
              <button type="button" class="mt-btn-ghost w-full" :disabled="opening" @click="upgrade('yearly')">
                $192/yr · 2 months free
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="border-t border-mt-border-soft">
      <div class="max-w-site mx-auto px-4 sm:px-6 py-16 sm:py-20 text-center">
        <h2 class="text-2xl sm:text-3xl font-semibold mb-3">Your server is one silent failure away from broken agents.</h2>
        <p class="text-mt-muted text-[15px] mb-8">Set up your first monitor in under a minute.</p>
        <NuxtLink to="/register" class="mt-btn-primary">Start monitoring</NuxtLink>
      </div>
    </section>
  </div>
</template>
