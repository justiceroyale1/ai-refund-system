import { defineStore } from 'pinia'
import { useAdminApi } from '~/composables/useAdminApi'
import type {
  AdminDashboardMetrics,
  RefundRequestFilters,
  RefundRequestSummary,
} from '~/types/admin'

interface AdminDashboardState {
  metrics: AdminDashboardMetrics | null
  refundRequests: RefundRequestSummary[]
  currentPage: number
  lastPage: number
  total: number
  isLoadingMetrics: boolean
  isLoadingRequests: boolean
  metricsError: string | null
  requestsError: string | null
  requestVersion: number
}

export const useAdminDashboardStore = defineStore('admin-dashboard', {
  state: (): AdminDashboardState => ({
    metrics: null,
    refundRequests: [],
    currentPage: 1,
    lastPage: 1,
    total: 0,
    isLoadingMetrics: false,
    isLoadingRequests: false,
    metricsError: null,
    requestsError: null,
    requestVersion: 0,
  }),

  actions: {
    async loadMetrics(): Promise<void> {
      this.isLoadingMetrics = true
      this.metricsError = null

      try {
        this.metrics = await useAdminApi().dashboard()
      }
      catch {
        this.metricsError = 'We could not load dashboard metrics. Please try again.'
      }
      finally {
        this.isLoadingMetrics = false
      }
    },

    async loadRefundRequests(filters: RefundRequestFilters): Promise<void> {
      const requestVersion = ++this.requestVersion
      this.isLoadingRequests = true
      this.requestsError = null

      try {
        const response = await useAdminApi().listRefundRequests(filters)

        if (requestVersion !== this.requestVersion) {
          return
        }

        this.refundRequests = response.data
        this.currentPage = response.meta.current_page
        this.lastPage = response.meta.last_page
        this.total = response.meta.total
      }
      catch {
        if (requestVersion === this.requestVersion) {
          this.refundRequests = []
          this.requestsError = 'We could not load refund requests. Please try again.'
        }
      }
      finally {
        if (requestVersion === this.requestVersion) {
          this.isLoadingRequests = false
        }
      }
    },
  },
})
