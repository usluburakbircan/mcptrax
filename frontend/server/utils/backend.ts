import type { H3Event } from 'h3'

export const TOKEN_COOKIE = 'mt_session'

/**
 * The Laravel backend address lives in PRIVATE runtime config: the token is
 * injected by the proxy, so the browser never talks to Laravel directly.
 */
export function backendBase(): string {
  const base = useRuntimeConfig().apiBase
  return String(base).replace(/\/+$/, '')
}

export function readToken(event: H3Event): string | null {
  return getCookie(event, TOKEN_COOKIE) ?? null
}

export function writeToken(event: H3Event, token: string, maxAgeSeconds: number) {
  setCookie(event, TOKEN_COOKIE, token, {
    // The real win: the token cannot be read from JS, so an XSS cannot
    // exfiltrate it.
    httpOnly: true,
    secure: !import.meta.dev,
    sameSite: 'lax',
    path: '/',
    maxAge: maxAgeSeconds,
  })
}

export function clearToken(event: H3Event) {
  deleteCookie(event, TOKEN_COOKIE, { path: '/' })
}

export function authHeaders(event: H3Event): Record<string, string> {
  const token = readToken(event)
  return token ? { Authorization: `Bearer ${token}` } : {}
}
