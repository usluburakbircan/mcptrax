/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './app/**/*.{vue,ts,js}',
    './nuxt.config.{ts,js}',
  ],
  theme: {
    extend: {
      colors: {
        mt: {
          bg: 'var(--mt-bg)',
          raised: 'rgb(var(--mt-raised-rgb) / <alpha-value>)',
          soft: 'rgb(var(--mt-soft-rgb) / <alpha-value>)',
          border: 'var(--mt-border)',
          'border-soft': 'var(--mt-border-soft)',
          text: 'var(--mt-text)',
          muted: 'var(--mt-muted)',
          faint: 'var(--mt-faint)',
          up: 'rgb(var(--mt-up-rgb) / <alpha-value>)',
          down: 'rgb(var(--mt-down-rgb) / <alpha-value>)',
          warn: 'rgb(var(--mt-warn-rgb) / <alpha-value>)',
        },
      },
      fontFamily: {
        display: ['"Space Grotesk"', 'system-ui', 'sans-serif'],
        body: ['Inter', 'system-ui', 'sans-serif'],
        mono: ['"IBM Plex Mono"', 'ui-monospace', 'SFMono-Regular', 'monospace'],
      },
      maxWidth: {
        site: '72rem',
      },
    },
  },
  plugins: [],
}
