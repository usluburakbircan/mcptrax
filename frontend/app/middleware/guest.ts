export default defineNuxtRouteMiddleware(async () => {
  const auth = useAuthStore()
  await auth.initialize()

  if (auth.isAuthenticated) {
    return navigateTo('/app')
  }
})
