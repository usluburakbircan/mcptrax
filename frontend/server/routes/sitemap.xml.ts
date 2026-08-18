import { SITE_URL } from '#shared/site'
import { GUIDES } from '#shared/guides'

/**
 * Static sitemap: the landing page and the guide section. App pages are
 * behind auth (and X-Robots-Tag: noindex); status pages are per-user and
 * unlisted by design.
 */
export default defineEventHandler((event) => {
  const today = new Date().toISOString().slice(0, 10)

  const urls: Array<{ loc: string, lastmod: string }> = [
    { loc: `${SITE_URL}/`, lastmod: today },
    { loc: `${SITE_URL}/guides`, lastmod: today },
    ...GUIDES.map(g => ({ loc: `${SITE_URL}/guides/${g.slug}`, lastmod: g.dateModified })),
  ]

  const body = [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
    ...urls.map(u => `  <url><loc>${u.loc}</loc><lastmod>${u.lastmod}</lastmod></url>`),
    '</urlset>',
  ].join('\n')

  setHeader(event, 'content-type', 'application/xml; charset=utf-8')
  return body
})
