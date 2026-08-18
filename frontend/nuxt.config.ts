export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },

  modules: [
    '@pinia/nuxt',
    '@nuxtjs/tailwindcss',
  ],

  css: ['~/assets/css/main.css'],

  // App pages are private: the browser never talks to Laravel directly, so
  // SSR can also authenticate via the httpOnly session cookie.
  routeRules: {
    '/app/**': { headers: { 'X-Robots-Tag': 'noindex, nofollow' } },
  },

  runtimeConfig: {
    // PRIVATE — read only on the Nuxt server. All browser requests go
    // through the /api/mt/* proxy, which injects the Bearer token from the
    // httpOnly cookie. Override with NUXT_API_BASE.
    apiBase: 'http://127.0.0.1:8000/api',
    // Session cookie lifetime in seconds (14 days).
    sessionMaxAge: 60 * 60 * 24 * 14,
  },

  app: {
    head: {
      title: 'mcptrax — MCP server monitoring',
      htmlAttrs: { lang: 'en' },
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        {
          name: 'description',
          content: 'Know when your MCP server breaks — before your users do. Uptime, handshake and tool-drift monitoring for Model Context Protocol servers.',
        },
        { name: 'theme-color', content: '#0a0c0e' },
      ],
      link: [
        { rel: 'icon', type: 'image/svg+xml', href: '/favicon.svg' },
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500;600&display=swap',
        },
      ],
    },
  },
})
