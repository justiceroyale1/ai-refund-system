import { defineStore } from 'pinia'
import { useRefundApi } from '~/composables/useRefundApi'
import type { DemoCustomer } from '~/types/customer'

interface CustomerState {
  customers: DemoCustomer[]
  selectedCustomerId: number | null
  isLoading: boolean
  hasLoaded: boolean
  error: string | null
}

export const useCustomerStore = defineStore('customer', {
  state: (): CustomerState => ({
    customers: [],
    selectedCustomerId: null,
    isLoading: false,
    hasLoaded: false,
    error: null,
  }),

  getters: {
    selectedCustomer(state): DemoCustomer | null {
      return state.customers.find(customer => customer.id === state.selectedCustomerId) ?? null
    },
  },

  actions: {
    async loadCustomers(): Promise<void> {
      if (this.isLoading || this.hasLoaded) {
        return
      }

      this.isLoading = true
      this.error = null

      try {
        const api = useRefundApi(null)
        const selectedCustomerCookie = useCookie<string | null>('demo-customer-id', {
          default: () => null,
          sameSite: 'lax',
        })
        this.customers = await api.listDemoCustomers()
        const persistedCustomerId = selectedCustomerCookie.value === null
          ? null
          : Number(selectedCustomerCookie.value)

        const selectionIsValid = this.customers.some(
          customer => customer.id === this.selectedCustomerId,
        )
        const persistedSelectionIsValid = this.customers.some(
          customer => customer.id === persistedCustomerId,
        )

        if (!selectionIsValid) {
          this.selectedCustomerId = persistedSelectionIsValid
            ? persistedCustomerId
            : this.customers[0]?.id ?? null
        }

        selectedCustomerCookie.value = this.selectedCustomerId === null
          ? null
          : String(this.selectedCustomerId)

        this.hasLoaded = true
      }
      catch {
        this.error = 'We could not load the demo customers. Please try again.'
      }
      finally {
        this.isLoading = false
      }
    },

    selectCustomer(customerId: number): void {
      if (!this.customers.some(customer => customer.id === customerId)) {
        return
      }

      this.selectedCustomerId = customerId
      useCookie<string | null>('demo-customer-id', {
        default: () => null,
        sameSite: 'lax',
      }).value = String(customerId)
    },
  },
})
