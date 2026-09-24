import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiClientError } from '~/composables/useRefundApi'
import { useAdminSessionStore } from '~/stores/adminSession'

const apiMocks = vi.hoisted(() => ({
  currentAdmin: vi.fn(),
  login: vi.fn(),
  logout: vi.fn(),
}))

vi.mock('~/composables/useAdminApi', () => ({
  useAdminApi: () => apiMocks,
}))

describe('admin session store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  it('recovers an authenticated admin session', async () => {
    apiMocks.currentAdmin.mockResolvedValue({
      id: 1,
      name: 'Talia Mercer',
      email: 'talia@example.test',
    })
    const store = useAdminSessionStore()

    await expect(store.ensureSession()).resolves.toBe(true)

    expect(store.isAuthenticated).toBe(true)
    expect(store.admin?.name).toBe('Talia Mercer')
  })

  it('marks unauthenticated session recovery as guest', async () => {
    apiMocks.currentAdmin.mockRejectedValue(new ApiClientError('Authentication is required.', 401))
    const store = useAdminSessionStore()

    await expect(store.ensureSession()).resolves.toBe(false)

    expect(store.status).toBe('guest')
    expect(store.admin).toBeNull()
  })

  it('shows the backend credential error after a failed login', async () => {
    apiMocks.login.mockRejectedValue(new ApiClientError(
      'The given data was invalid.',
      422,
      'VALIDATION_FAILED',
      { errors: { email: ['The provided credentials do not match our records.'] } },
    ))
    const store = useAdminSessionStore()

    await expect(store.login('wrong@example.test', 'wrong')).resolves.toBe(false)

    expect(store.loginError).toBe('The provided credentials do not match our records.')
    expect(store.status).toBe('guest')
  })

  it('clears local authentication after logout', async () => {
    apiMocks.logout.mockResolvedValue(undefined)
    const store = useAdminSessionStore()
    store.admin = { id: 1, name: 'Talia Mercer', email: 'talia@example.test' }
    store.status = 'authenticated'

    await store.logout()

    expect(apiMocks.logout).toHaveBeenCalledOnce()
    expect(store.isAuthenticated).toBe(false)
    expect(store.admin).toBeNull()
  })

  it('still clears local authentication when remote logout fails', async () => {
    apiMocks.logout.mockRejectedValue(new Error('offline'))
    const store = useAdminSessionStore()
    store.admin = { id: 1, name: 'Talia Mercer', email: 'talia@example.test' }
    store.status = 'authenticated'

    await expect(store.logout()).resolves.toBeUndefined()

    expect(store.isAuthenticated).toBe(false)
    expect(store.isLoggingOut).toBe(false)
  })
})
