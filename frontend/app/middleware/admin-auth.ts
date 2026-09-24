import { useAdminSessionStore } from '~/stores/adminSession'

export default defineNuxtRouteMiddleware(async (to) => {
  const sessionStore = useAdminSessionStore()

  if (await sessionStore.ensureSession()) {
    return
  }

  return navigateTo({
    path: '/admin/login',
    query: { redirect: to.fullPath },
  })
})
