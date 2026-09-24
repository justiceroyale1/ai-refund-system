import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import adminAuthMiddleware from '~/middleware/admin-auth'
import adminGuestMiddleware from '~/middleware/admin-guest'
import { useAdminSessionStore } from '~/stores/adminSession'

const navigateToMock = vi.hoisted(() => vi.fn())
const apiMocks = vi.hoisted(() => ({
  currentAdmin: vi.fn(),
}))

mockNuxtImport('navigateTo', () => navigateToMock)

vi.mock('~/composables/useAdminApi', () => ({
  useAdminApi: () => apiMocks,
}))

describe('admin route middleware', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('redirects an unauthenticated admin route with its intended destination', async () => {
    apiMocks.currentAdmin.mockRejectedValue(new Error('unauthenticated'))

    await adminAuthMiddleware(
      { fullPath: '/admin?decision=escalated' } as never,
      {} as never,
    )

    expect(navigateToMock).toHaveBeenCalledWith({
      path: '/admin/login',
      query: { redirect: '/admin?decision=escalated' },
    })
  })

  it('allows an authenticated administrator through', async () => {
    const sessionStore = useAdminSessionStore()
    sessionStore.admin = { id: 1, name: 'Talia Mercer', email: 'talia@example.test' }
    sessionStore.status = 'authenticated'

    await adminAuthMiddleware({ fullPath: '/admin' } as never, {} as never)

    expect(navigateToMock).not.toHaveBeenCalled()
  })

  it('keeps authenticated administrators out of the login page', async () => {
    const sessionStore = useAdminSessionStore()
    sessionStore.admin = { id: 1, name: 'Talia Mercer', email: 'talia@example.test' }
    sessionStore.status = 'authenticated'

    await adminGuestMiddleware({} as never, {} as never)

    expect(navigateToMock).toHaveBeenCalledWith('/admin')
  })
})
