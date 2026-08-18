import type { MaybeRefOrGetter } from 'vue'
import { SITE_NAME, SITE_URL } from '#shared/site'

interface SeoInput {
  title: MaybeRefOrGetter<string>
  description: MaybeRefOrGetter<string>
  /** Path starting with '/'; the canonical URL is built from SITE_URL. */
  path: string
  type?: 'website' | 'article'
}

/**
 * One call per public page: title, meta description, canonical, Open Graph
 * and Twitter card tags. Text-only cards — no OG image yet.
 */
export function useSeo(input: SeoInput) {
  const url = `${SITE_URL}${input.path}`

  useHead({
    title: computed(() => toValue(input.title)),
    link: [{ rel: 'canonical', href: url }],
  })

  useSeoMeta({
    description: () => toValue(input.description),
    ogTitle: () => toValue(input.title),
    ogDescription: () => toValue(input.description),
    ogUrl: url,
    ogType: input.type ?? 'website',
    ogSiteName: SITE_NAME,
    twitterCard: 'summary',
    twitterTitle: () => toValue(input.title),
    twitterDescription: () => toValue(input.description),
  })
}

/** Injects a JSON-LD structured data block into the page head. */
export function useJsonLd(data: Record<string, unknown>) {
  useHead({
    script: [{
      type: 'application/ld+json',
      innerHTML: JSON.stringify(data),
    }],
  })
}
