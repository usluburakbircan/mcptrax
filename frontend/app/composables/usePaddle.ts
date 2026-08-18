import type { ApiEnvelope, BillingConfig } from '~/types/api'

declare global {
  interface Window {
    Paddle?: any
  }
}

export type BillingCycle = 'monthly' | 'yearly'

// Paddle.js is loaded lazily, only when someone actually starts a checkout —
// visitors who never touch pricing never load the script.
let scriptPromise: Promise<void> | null = null
// Paddle.Initialize must run once per token; re-running it throws.
let initializedToken: string | null = null

const opening = ref(false)

function loadPaddleJs(): Promise<void> {
  if (window.Paddle) return Promise.resolve()
  if (!scriptPromise) {
    scriptPromise = new Promise((resolve, reject) => {
      const s = document.createElement('script')
      s.src = 'https://cdn.paddle.com/paddle/v2/paddle.js'
      s.async = true
      s.onload = () => resolve()
      s.onerror = () => {
        // Allow a retry on the next click instead of caching the failure.
        scriptPromise = null
        reject(new Error('Failed to load Paddle.js'))
      }
      document.head.appendChild(s)
    })
  }
  return scriptPromise
}

/**
 * Webhook processing takes a moment after payment: poll /auth/me until the
 * plan flips to pro, then the whole UI (limits, badges) updates via the
 * auth store.
 */
async function pollForActivation() {
  const auth = useAuthStore()
  const toast = useToast()

  for (let attempt = 0; attempt < 10; attempt++) {
    await new Promise(resolve => setTimeout(resolve, 3000))
    try {
      await auth.fetchMe()
    } catch {
      continue
    }
    if (auth.user?.plan === 'pro') {
      toast.success('Pro is active. Enjoy the 1-minute checks.')
      return
    }
  }
}

function onPaddleEvent(event: any) {
  if (event?.name === 'checkout.completed') {
    useToast().success('Payment received — your plan activates within a minute.')
    void pollForActivation()
  }
}

export const usePaddle = () => {
  const { api } = useApi()
  const auth = useAuthStore()
  const toast = useToast()

  const openCheckout = async (cycle: BillingCycle) => {
    if (opening.value) return
    opening.value = true
    try {
      const res = await api<ApiEnvelope<BillingConfig>>('/billing/config')
      const config = res.data

      await loadPaddleJs()

      window.Paddle.Environment.set(config.environment === 'live' ? 'production' : 'sandbox')
      if (initializedToken !== config.client_token) {
        window.Paddle.Initialize({
          token: config.client_token,
          eventCallback: onPaddleEvent,
        })
        initializedToken = config.client_token
      }

      window.Paddle.Checkout.open({
        items: [{
          priceId: cycle === 'yearly' ? config.prices.pro_yearly : config.prices.pro_monthly,
          quantity: 1,
        }],
        customData: { user_id: String(config.user_id) },
        ...(auth.user?.email ? { customer: { email: auth.user.email } } : {}),
        settings: { theme: 'dark' },
      })
    } catch (err: any) {
      toast.error(apiErrorMessage(err, 'Could not start checkout. Try again in a moment.'))
    } finally {
      opening.value = false
    }
  }

  return { openCheckout, opening }
}
