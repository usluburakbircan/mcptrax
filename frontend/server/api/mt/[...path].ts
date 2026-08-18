/**
 * The single gateway to Laravel. The browser only ever calls
 * /api/mt/... on its own origin; the Authorization header is added here,
 * read from the httpOnly session cookie.
 *
 * Side benefits: no CORS needed, the backend address never leaks to the
 * client, and SSR requests are authenticated too (the cookie reaches the
 * Nuxt server).
 */
const HOP_BY_HOP = new Set([
  'connection', 'keep-alive', 'transfer-encoding', 'upgrade',
  'proxy-authenticate', 'proxy-authorization', 'te', 'trailer',
  // The backend's cookies must not reach the browser — we own the session.
  'set-cookie',
  // The body is re-encoded, so length/encoding headers are not copied.
  'content-length', 'content-encoding',
])

export default defineEventHandler(async (event) => {
  const path = getRouterParam(event, 'path', { decode: true }) ?? ''

  // `..` segments would escape the /api prefix on the backend — every route
  // outside it would become reachable with the user's Authorization header
  // attached. Checked AFTER decoding so %2E%2E%2F is caught too.
  if (path.split('/').includes('..')) {
    throw createError({ statusCode: 400, statusMessage: 'Invalid path' })
  }

  const target = `${backendBase()}/${path}${getRequestURL(event).search}`
  const method = event.method

  const headers: Record<string, string> = {
    ...authHeaders(event),
    accept: getHeader(event, 'accept') ?? 'application/json',
  }

  const contentType = getHeader(event, 'content-type')
  if (contentType) headers['content-type'] = contentType

  // The client IP must reach Laravel: without it, throttling (the public
  // /check endpoint is rate limited) would bucket every visitor as
  // 127.0.0.1. In production nginx OVERWRITES this header with $remote_addr,
  // so a spoofed browser value never gets here.
  const forwardedFor = getHeader(event, 'x-forwarded-for')
  if (forwardedFor) headers['x-forwarded-for'] = forwardedFor

  const body = method === 'GET' || method === 'HEAD'
    ? undefined
    : await readRawBody(event, false)

  // Native fetch: it does not throw on 4xx/5xx, so Laravel's 401/422/429
  // responses reach the frontend with their status and body intact.
  let upstream: Response
  try {
    upstream = await fetch(target, {
      method,
      headers,
      body: body as BodyInit | undefined,
      redirect: 'manual',
    })
  } catch {
    // Backend unreachable (down, wrong NUXT_API_BASE, network). A clean 502
    // instead of an unhandled 500 with a stack trace in the logs.
    throw createError({
      statusCode: 502,
      data: { success: false, message: 'Could not reach the server.' },
    })
  }

  const responseHeaders = new Headers()
  upstream.headers.forEach((value, key) => {
    if (!HOP_BY_HOP.has(key.toLowerCase())) responseHeaders.set(key, value)
  })

  return sendWebResponse(event, new Response(upstream.body, {
    status: upstream.status,
    statusText: upstream.statusText,
    headers: responseHeaders,
  }))
})
