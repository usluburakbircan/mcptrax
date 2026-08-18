<script setup lang="ts">
import type { ApiEnvelope, Monitor, MonitorPayload } from '~/types/api'

definePageMeta({ layout: 'app', middleware: 'auth' })
useHead({ title: 'Add monitor — mcptrax' })

const { api } = useApi()
const toast = useToast()

const saving = ref(false)
const fieldErrors = ref<Record<string, string[]>>({})
const upgradeMessage = ref('')

const onSubmit = async (payload: MonitorPayload) => {
  saving.value = true
  fieldErrors.value = {}
  upgradeMessage.value = ''
  try {
    const res = await api<ApiEnvelope<{ monitor: Monitor }>>('/monitors', {
      method: 'POST',
      body: payload,
    })
    toast.success('Monitor added. First check is on its way.')
    await navigateTo(`/app/monitors/${res.data.monitor.id}`)
  } catch (err: any) {
    if (isUpgradeRequired(err)) {
      upgradeMessage.value = apiErrorMessage(err, 'Your plan limit is reached.')
      return
    }
    fieldErrors.value = apiFieldErrors(err)
    if (!Object.keys(fieldErrors.value).length) {
      toast.error(apiErrorMessage(err, 'Could not add the monitor.'))
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="max-w-xl">
    <NuxtLink to="/app" class="font-mono text-[12px] text-mt-faint hover:text-mt-muted transition">← Monitors</NuxtLink>
    <h1 class="text-xl font-semibold mt-2 mb-1">Add monitor</h1>
    <p class="text-[13px] text-mt-faint mb-8">mcptrax checks it from the moment you save.</p>

    <UpgradePrompt v-if="upgradeMessage" :message="upgradeMessage" class="mb-6" />

    <MonitorForm submit-label="Add monitor" :loading="saving" :field-errors="fieldErrors" @submit="onSubmit" />
  </div>
</template>
