<script setup lang="ts">
import { GUIDES } from '#shared/guides'
import { SITE_NAME, SITE_URL } from '#shared/site'

const props = withDefaults(defineProps<{
  slug: string
  /** Which CTA the article ends with. */
  cta?: 'checker' | 'signup'
}>(), { cta: 'checker' })

const meta = computed(() => GUIDES.find(g => g.slug === props.slug))
const related = computed(() => GUIDES.filter(g => g.slug !== props.slug))

const title = computed(() => meta.value ? `${meta.value.title} — ${SITE_NAME}` : SITE_NAME)
const description = computed(() => meta.value?.description ?? '')

useSeo({
  title,
  description,
  path: `/guides/${props.slug}`,
  type: 'article',
})

if (meta.value) {
  useJsonLd({
    '@context': 'https://schema.org',
    '@type': 'Article',
    'headline': meta.value.title,
    'description': meta.value.description,
    'datePublished': meta.value.datePublished,
    'dateModified': meta.value.dateModified,
    'mainEntityOfPage': `${SITE_URL}/guides/${meta.value.slug}`,
    'author': { '@type': 'Organization', 'name': SITE_NAME, 'url': SITE_URL },
    'publisher': { '@type': 'Organization', 'name': SITE_NAME, 'url': SITE_URL },
  })
}

const dateLabel = computed(() => {
  if (!meta.value) return ''
  const d = new Date(`${meta.value.datePublished}T00:00:00`)
  return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
})
</script>

<template>
  <article class="max-w-site mx-auto px-4 sm:px-6 py-12 sm:py-16">
    <div class="max-w-2xl mx-auto">
      <NuxtLink to="/guides" class="font-mono text-[12px] text-mt-faint hover:text-mt-muted transition">← Guides</NuxtLink>

      <header class="mt-4 mb-10">
        <p class="mt-eyebrow mb-3">Guide</p>
        <h1 class="text-3xl sm:text-[2.4rem] font-semibold leading-[1.15]">{{ meta?.title }}</h1>
        <p class="mt-3 font-mono text-[12px] text-mt-faint">{{ dateLabel }} · {{ meta?.readMinutes }} min read</p>
      </header>

      <div class="mt-prose">
        <slot />
      </div>

      <!-- CTA -->
      <aside class="mt-12 rounded-lg border border-mt-up/30 bg-[var(--mt-up-dim)] p-6">
        <template v-if="cta === 'checker'">
          <h2 class="text-[17px] font-semibold mb-1.5">Check your MCP server right now</h2>
          <p class="text-[13.5px] text-mt-muted mb-4">
            The free checker runs a real initialize handshake and tools/list against your server — no signup, results in seconds.
          </p>
          <NuxtLink to="/#check" class="mt-btn-primary">Run a free check</NuxtLink>
        </template>
        <template v-else>
          <h2 class="text-[17px] font-semibold mb-1.5">Stop finding out from your users</h2>
          <p class="text-[13.5px] text-mt-muted mb-4">
            mcptrax checks your MCP server on a schedule, diffs your tool list on every run, and alerts you the moment anything breaks.
          </p>
          <NuxtLink to="/register" class="mt-btn-primary">Start monitoring free</NuxtLink>
        </template>
      </aside>

      <!-- Related -->
      <nav class="mt-12 border-t border-mt-border-soft pt-8" aria-label="Related guides">
        <p class="mt-eyebrow mb-4">Keep reading</p>
        <ul class="space-y-3">
          <li v-for="g in related" :key="g.slug">
            <NuxtLink :to="`/guides/${g.slug}`" class="group block">
              <span class="text-[14.5px] font-medium text-mt-text group-hover:text-mt-up transition">{{ g.title }}</span>
              <span class="block font-mono text-[11.5px] text-mt-faint mt-0.5">{{ g.readMinutes }} min read</span>
            </NuxtLink>
          </li>
        </ul>
      </nav>
    </div>
  </article>
</template>
