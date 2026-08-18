<script setup lang="ts">
import type { Monitor, MonitorPayload } from '~/types/api'

const props = defineProps<{
  monitor?: Monitor | null
  submitLabel: string
  loading?: boolean
  fieldErrors?: Record<string, string[]>
}>()

const emit = defineEmits<{ submit: [payload: MonitorPayload] }>()

const auth = useAuthStore()

// Plan limits from /auth/me. Missing limits (older sessions) fall back to
// permissive — the backend enforces the real ones anyway.
const syntheticAllowed = computed(() => auth.user?.limits?.synthetic_calls ?? true)
const minInterval = computed(() => auth.user?.limits?.min_interval_seconds ?? 60)

const INTERVALS = [
  { value: 60, label: 'Every minute' },
  { value: 300, label: 'Every 5 minutes' },
  { value: 900, label: 'Every 15 minutes' },
]

const intervalOptions = computed(() =>
  INTERVALS.map(opt => ({ ...opt, locked: opt.value < minInterval.value })))

const form = reactive({
  name: props.monitor?.name ?? '',
  url: props.monitor?.url ?? '',
  interval_seconds: props.monitor?.interval_seconds ?? 900,
  auth_header_name: '',
  auth_header_value: '',
  synthetic_tool_name: props.monitor?.synthetic_tool_name ?? '',
  synthetic_tool_args: '',
  synthetic_expect_substring: '',
  is_public: props.monitor?.is_public ?? false,
})

const showAuth = ref(!!props.monitor?.has_auth)
const showSynthetic = ref(!!props.monitor?.synthetic_tool_name)
const argsError = ref('')

const err = (field: string) => props.fieldErrors?.[field]?.[0]

const onSubmit = () => {
  argsError.value = ''

  const payload: MonitorPayload = {
    name: form.name.trim(),
    url: form.url.trim(),
    interval_seconds: form.interval_seconds,
    is_public: form.is_public,
  }

  if (showAuth.value && form.auth_header_name.trim()) {
    payload.auth_header_name = form.auth_header_name.trim()
    payload.auth_header_value = form.auth_header_value
  }

  if (syntheticAllowed.value && showSynthetic.value && form.synthetic_tool_name.trim()) {
    payload.synthetic_tool_name = form.synthetic_tool_name.trim()

    if (form.synthetic_tool_args.trim()) {
      try {
        const parsed = JSON.parse(form.synthetic_tool_args)
        if (parsed === null || typeof parsed !== 'object' || Array.isArray(parsed)) {
          argsError.value = 'Arguments must be a JSON object, e.g. {"query": "test"}.'
          return
        }
        payload.synthetic_tool_args = parsed
      } catch {
        argsError.value = 'This is not valid JSON. Check for missing quotes or commas.'
        return
      }
    }

    if (form.synthetic_expect_substring.trim()) {
      payload.synthetic_expect_substring = form.synthetic_expect_substring
    }
  }

  emit('submit', payload)
}
</script>

<template>
  <form class="space-y-8" @submit.prevent="onSubmit">
    <!-- Basics -->
    <section class="space-y-5">
      <div>
        <label class="mt-label" for="m-name">Name</label>
        <input id="m-name" v-model="form.name" type="text" required class="mt-input" placeholder="My weather server">
        <p v-if="err('name')" class="mt-1.5 text-[12px] text-mt-down">{{ err('name') }}</p>
      </div>

      <div>
        <label class="mt-label" for="m-url">Server URL</label>
        <input
          id="m-url" v-model="form.url" type="url" required
          class="mt-input font-mono text-[13px]" placeholder="https://mcp.example.com/sse"
        >
        <p v-if="err('url')" class="mt-1.5 text-[12px] text-mt-down">{{ err('url') }}</p>
        <p v-else class="mt-hint">The HTTP(S) endpoint of your MCP server.</p>
      </div>

      <div>
        <label class="mt-label" for="m-interval">Check interval</label>
        <select id="m-interval" v-model.number="form.interval_seconds" class="mt-input">
          <option v-for="opt in intervalOptions" :key="opt.value" :value="opt.value" :disabled="opt.locked">
            {{ opt.label }}{{ opt.locked ? ' — Pro' : '' }}
          </option>
        </select>
        <p v-if="err('interval_seconds')" class="mt-1.5 text-[12px] text-mt-down">{{ err('interval_seconds') }}</p>
        <p v-else-if="intervalOptions.some(o => o.locked)" class="mt-hint">
          Faster checks are a Pro feature.
          <NuxtLink to="/app/settings#billing" class="text-mt-up hover:underline">Upgrade</NuxtLink>
        </p>
      </div>
    </section>

    <!-- Authentication -->
    <section class="border-t border-mt-border-soft pt-6">
      <label class="flex items-start gap-3 cursor-pointer select-none">
        <input v-model="showAuth" type="checkbox" class="mt-1 accent-[var(--mt-up)]">
        <span>
          <span class="block text-[14px] font-medium text-mt-text">Server requires authentication</span>
          <span class="block text-[12.5px] text-mt-faint mt-0.5">A header sent with every check, e.g. an API key.</span>
        </span>
      </label>

      <div v-if="showAuth" class="mt-4 grid gap-4 sm:grid-cols-2">
        <div>
          <label class="mt-label" for="m-auth-name">Header name</label>
          <input id="m-auth-name" v-model="form.auth_header_name" type="text" class="mt-input font-mono text-[13px]" placeholder="Authorization">
          <p v-if="err('auth_header_name')" class="mt-1.5 text-[12px] text-mt-down">{{ err('auth_header_name') }}</p>
        </div>
        <div>
          <label class="mt-label" for="m-auth-value">Header value</label>
          <input
            id="m-auth-value" v-model="form.auth_header_value" type="password" autocomplete="off"
            class="mt-input font-mono text-[13px]"
            :placeholder="monitor?.has_auth ? 'Unchanged — enter to replace' : 'Bearer sk-...'"
          >
          <p v-if="err('auth_header_value')" class="mt-1.5 text-[12px] text-mt-down">{{ err('auth_header_value') }}</p>
          <p v-else-if="monitor?.has_auth" class="mt-hint">A value is already stored. Leave the name blank to keep it as is.</p>
        </div>
      </div>
    </section>

    <!-- Synthetic tool call -->
    <section class="border-t border-mt-border-soft pt-6">
      <label class="flex items-start gap-3 select-none" :class="syntheticAllowed ? 'cursor-pointer' : 'cursor-not-allowed opacity-70'">
        <input v-model="showSynthetic" type="checkbox" class="mt-1 accent-[var(--mt-up)]" :disabled="!syntheticAllowed">
        <span>
          <span class="block text-[14px] font-medium text-mt-text">Synthetic tool call <span class="font-mono text-[10px] uppercase tracking-wider text-mt-up ml-1">Pro</span></span>
          <span class="block text-[12.5px] text-mt-faint mt-0.5">Call a real tool on every check and verify its response.</span>
        </span>
      </label>

      <p v-if="!syntheticAllowed" class="mt-2 text-[12.5px] text-mt-muted">
        Available on the Pro plan.
        <NuxtLink to="/app/settings#billing" class="text-mt-up hover:underline font-medium">Upgrade to Pro</NuxtLink>
        to call a real tool on every check.
      </p>

      <div v-if="showSynthetic" class="mt-4 space-y-4">
        <div>
          <label class="mt-label" for="m-tool">Tool name</label>
          <input id="m-tool" v-model="form.synthetic_tool_name" type="text" class="mt-input font-mono text-[13px]" placeholder="get_forecast" :disabled="!syntheticAllowed">
          <p v-if="err('synthetic_tool_name')" class="mt-1.5 text-[12px] text-mt-down">{{ err('synthetic_tool_name') }}</p>
        </div>
        <div>
          <label class="mt-label" for="m-args">Arguments (JSON)</label>
          <textarea
            id="m-args" v-model="form.synthetic_tool_args" rows="4"
            class="mt-input font-mono text-[13px] resize-y" placeholder='{"city": "Berlin"}'
            :disabled="!syntheticAllowed"
          />
          <p v-if="argsError" class="mt-1.5 text-[12px] text-mt-down">{{ argsError }}</p>
          <p v-else-if="err('synthetic_tool_args')" class="mt-1.5 text-[12px] text-mt-down">{{ err('synthetic_tool_args') }}</p>
        </div>
        <div>
          <label class="mt-label" for="m-expect">Expected substring</label>
          <input id="m-expect" v-model="form.synthetic_expect_substring" type="text" class="mt-input font-mono text-[13px]" placeholder="temperature" :disabled="!syntheticAllowed">
          <p v-if="err('synthetic_expect_substring')" class="mt-1.5 text-[12px] text-mt-down">{{ err('synthetic_expect_substring') }}</p>
          <p v-else class="mt-hint">The check fails if the tool's response does not contain this text. Optional.</p>
        </div>
      </div>
    </section>

    <!-- Public status page -->
    <section class="border-t border-mt-border-soft pt-6">
      <label class="flex items-start gap-3 cursor-pointer select-none">
        <input v-model="form.is_public" type="checkbox" class="mt-1 accent-[var(--mt-up)]">
        <span>
          <span class="block text-[14px] font-medium text-mt-text">Public status page</span>
          <span class="block text-[12.5px] text-mt-faint mt-0.5">Anyone with the link can see this monitor's status.</span>
        </span>
      </label>
    </section>

    <div class="border-t border-mt-border-soft pt-6 flex items-center gap-3">
      <button type="submit" class="mt-btn-primary" :disabled="loading">
        {{ loading ? 'Saving…' : submitLabel }}
      </button>
      <NuxtLink to="/app" class="mt-btn-ghost">Cancel</NuxtLink>
    </div>
  </form>
</template>
