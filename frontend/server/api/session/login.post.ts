interface AuthResponse {
  success: boolean
  data: { user: Record<string, unknown>, token: string }
}

/**
 * The token never reaches the client: it is taken from Laravel, written to
 * an httpOnly cookie, and the response carries only the user object.
 */
export default defineEventHandler(async (event) => {
  const body = await readBody(event)

  try {
    const res = await $fetch<AuthResponse>(`${backendBase()}/auth/login`, {
      method: 'POST',
      body,
      headers: { Accept: 'application/json' },
    })

    writeToken(event, res.data.token, useRuntimeConfig().sessionMaxAge)

    return { success: true, data: { user: res.data.user } }
  } catch (err: any) {
    // Pass Laravel's status code and error body through unchanged so
    // field-level error display keeps working.
    throw createError({
      statusCode: err?.response?.status ?? 502,
      data: err?.data ?? { success: false, message: 'Could not reach the server.' },
    })
  }
})
