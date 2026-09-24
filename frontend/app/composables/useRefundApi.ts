import { toValue, type MaybeRefOrGetter } from 'vue'
import type { ApiEnvelope, ApiErrorPayload, PaginatedResponse } from '~/types/api'
import type {
  ConversationMessageSubmission,
  RefundConversation,
  RefundConversationSummary,
} from '~/types/conversation'
import type { DemoCustomer } from '~/types/customer'

type ApiMethod = 'GET' | 'POST'

interface ApiRequestOptions {
  method?: ApiMethod
  query?: Record<string, number | string>
  headers?: Record<string, string>
  body?: unknown
}

interface ApiTransport {
  <T>(request: string, options?: ApiRequestOptions): Promise<T>
}

interface FetchLikeError {
  status?: number
  statusCode?: number
  data?: ApiErrorPayload
}

export class ApiClientError extends Error {
  constructor(
    message: string,
    public readonly status: number | null = null,
    public readonly code: string | null = null,
    public readonly details: ApiErrorPayload['error']['details'] = [],
  ) {
    super(message)
    this.name = 'ApiClientError'
  }
}

function normalizeBaseUrl(baseUrl: string): string {
  return baseUrl.replace(/\/$/, '')
}

function normalizeApiError(error: unknown): ApiClientError {
  if (error instanceof ApiClientError) {
    return error
  }

  if (typeof error === 'object' && error !== null) {
    const fetchError = error as FetchLikeError
    const payload = fetchError.data?.error

    return new ApiClientError(
      payload?.message ?? 'The request could not be completed.',
      fetchError.statusCode ?? fetchError.status ?? null,
      payload?.code ?? null,
      payload?.details ?? [],
    )
  }

  return new ApiClientError('The request could not be completed.')
}

export function createRefundApiClient(
  transport: ApiTransport,
  baseUrl: string,
  selectedCustomerId: MaybeRefOrGetter<number | null>,
) {
  const request = async <T>(path: string, options?: ApiRequestOptions): Promise<T> => {
    try {
      return await transport<T>(`${normalizeBaseUrl(baseUrl)}${path}`, options)
    }
    catch (error) {
      throw normalizeApiError(error)
    }
  }

  const customerRequest = async <T>(path: string, options?: ApiRequestOptions): Promise<T> => {
    const customerId = toValue(selectedCustomerId)

    if (customerId === null) {
      throw new ApiClientError('Select a demo customer before continuing.')
    }

    return await request<T>(path, {
      ...options,
      headers: {
        ...options?.headers,
        'X-Demo-Customer-Id': String(customerId),
      },
    })
  }

  return {
    listDemoCustomers: async (): Promise<DemoCustomer[]> => {
      const response = await request<ApiEnvelope<DemoCustomer[]>>('/api/demo/customers')

      return response.data
    },

    listConversations: (page = 1): Promise<PaginatedResponse<RefundConversationSummary>> => {
      return customerRequest('/api/customer/conversations', {
        query: { page },
      })
    },

    getConversation: async (conversationId: number | string): Promise<RefundConversation> => {
      const response = await customerRequest<ApiEnvelope<RefundConversation>>(
        `/api/customer/conversations/${conversationId}`,
      )

      return response.data
    },

    createConversation: async (): Promise<RefundConversation> => {
      const response = await customerRequest<ApiEnvelope<RefundConversation>>(
        '/api/customer/conversations',
        { method: 'POST' },
      )

      return response.data
    },

    submitMessage: async (
      conversationId: number | string,
      submission: ConversationMessageSubmission,
    ): Promise<RefundConversation> => {
      const response = await customerRequest<ApiEnvelope<RefundConversation>>(
        `/api/customer/conversations/${conversationId}/messages`,
        {
          method: 'POST',
          body: submission,
        },
      )

      return response.data
    },
  }
}

export function useRefundApi(selectedCustomerId: MaybeRefOrGetter<number | null>) {
  const config = useRuntimeConfig()
  const apiBase = import.meta.server
    ? config.apiBase || config.public.apiBase
    : config.public.apiBase

  return createRefundApiClient(
    $fetch as ApiTransport,
    apiBase,
    selectedCustomerId,
  )
}
