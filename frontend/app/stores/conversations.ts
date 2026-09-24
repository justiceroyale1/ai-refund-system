import { defineStore } from 'pinia'
import { ApiClientError, useRefundApi } from '~/composables/useRefundApi'
import { selectPreferredConversation } from '~/lib/conversations'
import { useCustomerStore } from '~/stores/customer'
import type {
  ConversationMessageSubmission,
  OptimisticConversationMessage,
  RefundConversation,
  RefundConversationSummary,
} from '~/types/conversation'

export interface ConversationSubmissionResult {
  conversation: RefundConversation
  redirectConversationId: number | null
}

type MessageErrorKind = 'validation' | 'resolved' | 'retryable' | 'generic'

interface ConversationState {
  history: RefundConversationSummary[]
  currentConversation: RefundConversation | null
  currentPage: number
  lastPage: number
  total: number
  scopeVersion: number
  isLoadingHistory: boolean
  isLoadingConversation: boolean
  isCreatingConversation: boolean
  isSubmittingMessage: boolean
  hasLoadedHistory: boolean
  historyError: string | null
  conversationError: string | null
  conversationNotFound: boolean
  messageError: string | null
  messageErrorKind: MessageErrorKind | null
  retrySubmission: ConversationMessageSubmission | null
  optimisticMessage: OptimisticConversationMessage | null
}

function firstValidationMessage(details: unknown): string | null {
  if (typeof details !== 'object' || details === null || Array.isArray(details)) {
    return null
  }

  const errors = (details as Record<string, unknown>).errors

  if (typeof errors !== 'object' || errors === null || Array.isArray(errors)) {
    return null
  }

  for (const messages of Object.values(errors)) {
    if (Array.isArray(messages) && typeof messages[0] === 'string') {
      return messages[0]
    }
  }

  return null
}

export const useConversationStore = defineStore('conversations', {
  state: (): ConversationState => ({
    history: [],
    currentConversation: null,
    currentPage: 0,
    lastPage: 1,
    total: 0,
    scopeVersion: 0,
    isLoadingHistory: false,
    isLoadingConversation: false,
    isCreatingConversation: false,
    isSubmittingMessage: false,
    hasLoadedHistory: false,
    historyError: null,
    conversationError: null,
    conversationNotFound: false,
    messageError: null,
    messageErrorKind: null,
    retrySubmission: null,
    optimisticMessage: null,
  }),

  getters: {
    preferredConversation(state): RefundConversationSummary | null {
      return selectPreferredConversation(state.history)
    },

    hasMoreHistory(state): boolean {
      return state.currentPage < state.lastPage
    },
  },

  actions: {
    resetCustomerScope(): void {
      this.history = []
      this.currentConversation = null
      this.currentPage = 0
      this.lastPage = 1
      this.total = 0
      this.scopeVersion += 1
      this.isLoadingHistory = false
      this.isLoadingConversation = false
      this.isCreatingConversation = false
      this.isSubmittingMessage = false
      this.hasLoadedHistory = false
      this.historyError = null
      this.conversationError = null
      this.conversationNotFound = false
      this.clearMessageSubmission()
    },

    clearMessageSubmission(): void {
      this.messageError = null
      this.messageErrorKind = null
      this.retrySubmission = null
      this.optimisticMessage = null
    },

    async switchCustomer(customerId: number): Promise<void> {
      const customerStore = useCustomerStore()

      if (customerStore.selectedCustomerId === customerId && this.hasLoadedHistory) {
        return
      }

      this.resetCustomerScope()
      customerStore.selectCustomer(customerId)
      await this.loadHistory()
    },

    async loadHistory(page = 1): Promise<void> {
      const customerStore = useCustomerStore()

      if (customerStore.selectedCustomerId === null || this.isLoadingHistory) {
        return
      }

      const requestScope = this.scopeVersion
      const api = useRefundApi(() => customerStore.selectedCustomerId)
      this.isLoadingHistory = true
      this.historyError = null

      try {
        const response = await api.listConversations(page)

        if (requestScope !== this.scopeVersion) {
          return
        }

        this.history = page === 1
          ? response.data
          : [...this.history, ...response.data]
        this.currentPage = response.meta.current_page
        this.lastPage = response.meta.last_page
        this.total = response.meta.total
        this.hasLoadedHistory = true
      }
      catch {
        if (requestScope === this.scopeVersion) {
          this.historyError = 'We could not load your conversation history. Please try again.'
        }
      }
      finally {
        if (requestScope === this.scopeVersion) {
          this.isLoadingHistory = false
        }
      }
    },

    async loadMoreHistory(): Promise<void> {
      if (!this.hasMoreHistory) {
        return
      }

      await this.loadHistory(this.currentPage + 1)
    },

    async loadConversation(conversationId: number | string): Promise<void> {
      const customerStore = useCustomerStore()
      const requestScope = this.scopeVersion
      const api = useRefundApi(() => customerStore.selectedCustomerId)
      this.currentConversation = null
      this.isLoadingConversation = true
      this.conversationError = null
      this.conversationNotFound = false
      this.clearMessageSubmission()

      try {
        const conversation = await api.getConversation(conversationId)

        if (requestScope === this.scopeVersion) {
          this.currentConversation = conversation
        }
      }
      catch (error) {
        if (requestScope !== this.scopeVersion) {
          return
        }

        if (error instanceof ApiClientError && error.status === 404) {
          this.conversationNotFound = true
          this.conversationError = 'This conversation could not be found.'
        }
        else {
          this.conversationError = 'We could not load this conversation. Please try again.'
        }
      }
      finally {
        if (requestScope === this.scopeVersion) {
          this.isLoadingConversation = false
        }
      }
    },

    async createConversation(): Promise<RefundConversation | null> {
      const customerStore = useCustomerStore()
      const requestScope = this.scopeVersion
      const api = useRefundApi(() => customerStore.selectedCustomerId)
      this.isCreatingConversation = true
      this.conversationError = null

      try {
        const conversation = await api.createConversation()

        if (requestScope !== this.scopeVersion) {
          return null
        }

        this.currentConversation = conversation
        this.history = [conversation, ...this.history]
        this.total += 1

        return conversation
      }
      catch {
        if (requestScope === this.scopeVersion) {
          this.conversationError = 'We could not start a new refund request. Please try again.'
        }

        return null
      }
      finally {
        if (requestScope === this.scopeVersion) {
          this.isCreatingConversation = false
        }
      }
    },

    async submitMessage(
      conversationId: number | string,
      submission: ConversationMessageSubmission,
      isRetry = false,
    ): Promise<ConversationSubmissionResult | null> {
      if (this.isSubmittingMessage) {
        return null
      }

      if (this.currentConversation?.status === 'resolved') {
        this.messageError = 'This refund conversation has already been resolved.'
        this.messageErrorKind = 'resolved'

        return null
      }

      const customerStore = useCustomerStore()
      const requestScope = this.scopeVersion
      const api = useRefundApi(() => customerStore.selectedCustomerId)
      const previousOptimisticMessage = this.optimisticMessage
      this.isSubmittingMessage = true
      this.messageError = null
      this.messageErrorKind = null
      this.retrySubmission = null
      this.optimisticMessage = {
        clientMessageId: submission.client_message_id,
        content: submission.content,
        createdAt: isRetry && previousOptimisticMessage?.clientMessageId === submission.client_message_id
          ? previousOptimisticMessage.createdAt
          : new Date().toISOString(),
        status: 'sending',
      }

      try {
        const conversation = await api.submitMessage(conversationId, submission)

        if (requestScope !== this.scopeVersion) {
          return null
        }

        this.currentConversation = conversation
        this.upsertHistory(conversation)
        this.optimisticMessage = null

        const redirectConversationId = submission.selection?.type === 'open_existing_conversation'
          && typeof submission.selection.value === 'number'
          ? submission.selection.value
          : null

        return { conversation, redirectConversationId }
      }
      catch (error) {
        if (requestScope !== this.scopeVersion) {
          return null
        }

        if (error instanceof ApiClientError && error.status === 422) {
          this.messageError = firstValidationMessage(error.details) ?? error.message
          this.messageErrorKind = 'validation'
          this.optimisticMessage = null
        }
        else if (error instanceof ApiClientError && error.status === 409) {
          this.messageError = error.message
          this.messageErrorKind = 'resolved'
          this.optimisticMessage = null

          try {
            const conversation = await api.getConversation(conversationId)

            if (requestScope === this.scopeVersion) {
              this.currentConversation = conversation
              this.upsertHistory(conversation)
            }
          }
          catch {
            // Keep the conflict response visible when the refresh cannot be completed.
          }
        }
        else {
          this.messageError = error instanceof ApiClientError
            ? error.message
            : 'We could not send your message. Please try again.'
          this.messageErrorKind = error instanceof ApiClientError && error.status === 503
            ? 'retryable'
            : 'generic'
          this.retrySubmission = submission
          this.optimisticMessage = {
            ...this.optimisticMessage,
            clientMessageId: submission.client_message_id,
            content: submission.content,
            createdAt: this.optimisticMessage?.createdAt ?? new Date().toISOString(),
            status: 'failed',
          }
        }

        return null
      }
      finally {
        if (requestScope === this.scopeVersion) {
          this.isSubmittingMessage = false
        }
      }
    },

    async retryMessage(conversationId: number | string): Promise<ConversationSubmissionResult | null> {
      if (this.retrySubmission === null) {
        return null
      }

      return await this.submitMessage(conversationId, this.retrySubmission, true)
    },

    upsertHistory(conversation: RefundConversation): void {
      this.history = [
        conversation,
        ...this.history.filter(item => item.id !== conversation.id),
      ]
    },
  },
})
