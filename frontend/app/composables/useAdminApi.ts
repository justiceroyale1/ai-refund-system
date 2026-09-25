import type { MaybeRefOrGetter } from 'vue'
import { toValue } from 'vue'
import {
  normalizeApiError,
  type ApiRequestOptions,
  type ApiTransport,
} from '~/composables/useRefundApi'
import type {
  AdminDashboardMetrics,
  AdminUser,
  RefundRequestDetail,
  RefundRequestFilters,
  RefundRequestPage,
  RefundReviewSubmission,
} from '~/types/admin'
import type { ApiEnvelope } from '~/types/api'
import type {
  AdminNotification,
  NotificationPage,
  NotificationResponse,
  NotificationUnreadResponse,
} from '~/types/notification'

interface AdminCredentials {
  email: string
  password: string
}

function normalizeBaseUrl(baseUrl: string): string {
  return baseUrl.replace(/\/$/, '')
}

function decodeXsrfToken(token: string | null): string | null {
  if (token === null) {
    return null
  }

  try {
    return decodeURIComponent(token)
  }
  catch {
    return token
  }
}

export function createAdminApiClient(
  transport: ApiTransport,
  baseUrl: string,
  xsrfToken: MaybeRefOrGetter<string | null>,
  refreshXsrfToken: () => void = () => {},
) {
  const request = async <T>(path: string, options?: ApiRequestOptions): Promise<T> => {
    try {
      return await transport<T>(`${normalizeBaseUrl(baseUrl)}${path}`, {
        ...options,
        credentials: 'include',
      })
    }
    catch (error) {
      throw normalizeApiError(error)
    }
  }

  const csrfHeaders = (): Record<string, string> => {
    const token = decodeXsrfToken(toValue(xsrfToken))

    return token === null ? {} : { 'X-XSRF-TOKEN': token }
  }

  return {
    async login(credentials: AdminCredentials): Promise<AdminUser> {
      await request<unknown>('/sanctum/csrf-cookie')
      refreshXsrfToken()
      const response = await request<ApiEnvelope<AdminUser>>('/api/admin/login', {
        method: 'POST',
        headers: csrfHeaders(),
        body: credentials,
      })

      return response.data
    },

    async logout(): Promise<void> {
      await request<unknown>('/api/admin/logout', {
        method: 'POST',
        headers: csrfHeaders(),
      })
    },

    async currentAdmin(): Promise<AdminUser> {
      const response = await request<ApiEnvelope<AdminUser>>('/api/admin/me')

      return response.data
    },

    async dashboard(): Promise<AdminDashboardMetrics> {
      const response = await request<ApiEnvelope<AdminDashboardMetrics>>('/api/admin/dashboard')

      return response.data
    },

    listRefundRequests(filters: RefundRequestFilters): Promise<RefundRequestPage> {
      return request('/api/admin/refund-requests', {
        query: {
          ...(filters.decision ? { decision: filters.decision } : {}),
          ...(filters.executionStatus ? { execution_status: filters.executionStatus } : {}),
          ...(filters.search ? { search: filters.search } : {}),
          page: filters.page,
        },
      })
    },

    async getRefundRequest(refundRequestId: number): Promise<RefundRequestDetail> {
      const response = await request<ApiEnvelope<RefundRequestDetail>>(
        `/api/admin/refund-requests/${refundRequestId}`,
      )

      return response.data
    },

    async reviewRefundRequest(
      refundRequestId: number,
      submission: RefundReviewSubmission,
    ): Promise<RefundRequestDetail> {
      const response = await request<ApiEnvelope<RefundRequestDetail>>(
        `/api/admin/refund-requests/${refundRequestId}/review`,
        {
          method: 'POST',
          headers: csrfHeaders(),
          body: submission,
        },
      )

      return response.data
    },

    listNotifications(page = 1): Promise<NotificationPage<AdminNotification>> {
      return request('/api/admin/notifications', {
        query: { page },
      })
    },

    markNotificationRead(
      notificationId: string,
    ): Promise<NotificationResponse<AdminNotification>> {
      return request(`/api/admin/notifications/${notificationId}/read`, {
        method: 'PATCH',
        headers: csrfHeaders(),
      })
    },

    markAllNotificationsRead(): Promise<NotificationUnreadResponse> {
      return request('/api/admin/notifications/read-all', {
        method: 'PATCH',
        headers: csrfHeaders(),
      })
    },
  }
}

export function useAdminApi() {
  const config = useRuntimeConfig()
  const baseUrl = import.meta.server
    ? config.apiBase || config.public.apiBase
    : config.public.apiBase
  const xsrfToken = useCookie<string | null>('XSRF-TOKEN', { default: () => null })
  const transport = import.meta.server ? useRequestFetch() : $fetch

  return createAdminApiClient(
    transport as ApiTransport,
    baseUrl,
    () => xsrfToken.value,
    () => refreshCookie('XSRF-TOKEN'),
  )
}
