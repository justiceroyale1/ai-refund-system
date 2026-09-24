import { useAdminSessionStore } from '~/stores/adminSession'

export default defineNuxtRouteMiddleware(async () => {
  const sessionStore = useAdminSessionStore()

  if (await sessionStore.ensureSession()) {
    return navigateTo('/admin')
  }
})
