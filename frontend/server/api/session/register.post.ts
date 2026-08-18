interface AuthResponse {
  success: boolean
  data: { user: Record<string, unknown>, token: string }
}

export default defineEventHandler(async (event) => {
  const body = await readBody(event)

  try {
    const res = await $fetch<AuthResponse>(`${backendBase()}/auth/register`, {
      method: 'POST',
      body,
      headers: { Accept: 'application/json' },
    })

    writeToken(event, res.data.token, useRuntimeConfig().sessionMaxAge)
    setResponseStatus(event, 201)

    return { success: true, data: { user: res.data.user } }
  } catch (err: any) {
    throw createError({
      statusCode: err?.response?.status ?? 502,
      data: err?.data ?? { success: false, message: 'Could not reach the server.' },
    })
  }
})
