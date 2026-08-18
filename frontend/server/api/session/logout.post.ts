export default defineEventHandler(async (event) => {
  const token = readToken(event)

  if (token) {
    // Revoke the server-side token too; deleting the cookie alone does not
    // invalidate it.
    await $fetch(`${backendBase()}/auth/logout`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
    }).catch(() => { /* token may already be invalid */ })
  }

  clearToken(event)

  return { success: true }
})
