import type { NitroFetchRequest, NitroFetchOptions } from 'nitropack'

/**
 * Every request goes to our own origin's /api/mt proxy; the Nuxt server adds
 * the Authorization header from the httpOnly cookie. No token ever appears
 * in this file — it cannot be read, stored, or sent from the browser.
 */
type ApiOptions = NitroFetchOptions<NitroFetchRequest> & {
  /**
   * Redirect to /login automatically on 401?
   *
   * Must be `false` for requests made FROM INSIDE route middleware
   * (auth.initialize), otherwise two navigations end up waiting on each
   * other and the click appears to do nothing.
   */
  redirectOn401?: boolean
}

export const useApi = () => {
  const api = async <T = unknown>(
    request: NitroFetchRequest,
    options: ApiOptions = {},
  ): Promise<T> => {
    const { redirectOn401 = true, ...fetchOptions } = options

    try {
      return await $fetch<T>(request, {
        baseURL: '/api/mt',
        headers: {
          Accept: 'application/json',
          // During SSR, carry the browser's cookies onto the server request.
          ...(import.meta.server ? useRequestHeaders(['cookie']) : {}),
          ...(options.headers as Record<string, string> | undefined),
        },
        ...fetchOptions,
      }) as T
    } catch (err: any) {
      const status = err?.response?.status ?? err?.statusCode

      if (status === 401) {
        useAuthStore().clear()

        if (import.meta.client && redirectOn401) {
          const currentPath = useRouter().currentRoute.value.path
          if (currentPath !== '/login' && currentPath !== '/register') {
            // Deliberately NOT awaited — see the reference deadlock note:
            // awaiting a navigation from inside another pending navigation
            // makes both wait on each other.
            void navigateTo('/login')
          }
        }
      } else if (import.meta.client) {
        const toast = useToast()

        if (status === 429) {
          toast.error(apiErrorMessage(err, 'Too many requests. Please wait a moment.'))
        } else if (status >= 500) {
          toast.error('Something went wrong. Please try again.')
        } else if (status === undefined) {
          toast.error('Connection error. Please try again.')
        }
      }

      throw err
    }
  }

  return { api }
}

/** Extracts a readable message from Laravel's {message, errors} envelope. */
export const apiErrorMessage = (err: any, fallback = 'Something went wrong.'): string => {
  return err?.data?.data?.message || err?.data?.message || err?.message || fallback
}

export const apiFieldErrors = (err: any): Record<string, string[]> => {
  return err?.data?.data?.errors ?? err?.data?.errors ?? {}
}

/**
 * The backend returns 422 {success:false, message, upgrade_required:true}
 * when an action needs the Pro plan. These are surfaced as an upgrade
 * prompt, never as a generic error.
 */
export const isUpgradeRequired = (err: any): boolean => {
  return err?.data?.upgrade_required === true || err?.data?.data?.upgrade_required === true
}
