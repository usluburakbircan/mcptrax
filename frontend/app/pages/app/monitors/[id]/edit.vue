<script setup lang="ts">
import type { ApiEnvelope, CheckRow, Monitor, MonitorPayload } from '~/types/api'

definePageMeta({ layout: 'app', middleware: 'auth' })
useHead({ title: 'Edit monitor — mcptrax' })

const route = useRoute()
const { api } = useApi()
const toast = useToast()

const id = computed(() => String(route.params.id))

const { data, error } = await useAsyncData(
  () => `monitor-${id.value}`,
  () => api<ApiEnvelope<{ monitor: Monitor, recent_checks: CheckRow[] }>>(`/monitors/${id.value}`),
)

const monitor = computed(() => data.value?.data.monitor ?? null)

const saving = ref(false)
const fieldErrors = ref<Record<string, string[]>>({})
const upgradeMessage = ref('')

const onSubmit = async (payload: MonitorPayload) => {
  saving.value = true
  fieldErrors.value = {}
  upgradeMessage.value = ''
  try {
    await api<ApiEnvelope<{ monitor: Monitor }>>(`/monitors/${id.value}`, {
      method: 'PUT',
      body: payload,
    })
    toast.success('Changes saved.')
    await navigateTo(`/app/monitors/${id.value}`)
  } catch (err: any) {
    if (isUpgradeRequired(err)) {
      upgradeMessage.value = apiErrorMessage(err, 'This change needs the Pro plan.')
      return
    }
    fieldErrors.value = apiFieldErrors(err)
    if (!Object.keys(fieldErrors.value).length) {
      toast.error(apiErrorMessage(err, 'Could not save the changes.'))
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="max-w-xl">
    <NuxtLink :to="`/app/monitors/${id}`" class="font-mono text-[12px] text-mt-faint hover:text-mt-muted transition">← Back to monitor</NuxtLink>

    <div v-if="error" class="mt-card p-8 text-center mt-4">
      <p class="text-[14px] text-mt-muted">This monitor could not be loaded. It may have been deleted.</p>
    </div>

    <template v-else-if="monitor">
      <h1 class="text-xl font-semibold mt-2 mb-1">Edit {{ monitor.name }}</h1>
      <p class="font-mono text-[12px] text-mt-faint mb-8 truncate">{{ monitor.url }}</p>

      <UpgradePrompt v-if="upgradeMessage" :message="upgradeMessage" class="mb-6" />

      <MonitorForm
        :monitor="monitor" submit-label="Save changes"
        :loading="saving" :field-errors="fieldErrors" @submit="onSubmit"
      />
    </template>
  </div>
</template>
