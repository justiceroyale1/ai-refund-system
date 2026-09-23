import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useCustomerStore } from '~/stores/customer'

const apiMocks = vi.hoisted(() => ({
  listDemoCustomers: vi.fn(),
}))
const selectedCustomerCookie = vi.hoisted(() => ({
  value: null as string | null,
}))

mockNuxtImport('useCookie', () => () => selectedCustomerCookie)

vi.mock('~/composables/useRefundApi', () => ({
  useRefundApi: () => apiMocks,
}))

describe('customer store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
    selectedCustomerCookie.value = null
    apiMocks.listDemoCustomers.mockResolvedValue([
      { id: 1, name: 'First Customer', email: 'first@example.test' },
      { id: 2, name: 'Second Customer', email: 'second@example.test' },
    ])
  })

  it('restores a valid customer selection for direct navigation and refreshes', async () => {
    selectedCustomerCookie.value = '2'
    const store = useCustomerStore()

    await store.loadCustomers()

    expect(store.selectedCustomerId).toBe(2)
  })

  it('falls back to the first customer and persists the selection', async () => {
    selectedCustomerCookie.value = '999'
    const store = useCustomerStore()

    await store.loadCustomers()

    expect(store.selectedCustomerId).toBe(1)
    expect(selectedCustomerCookie.value).toBe('1')
  })
})
