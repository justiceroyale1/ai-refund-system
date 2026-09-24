import { defineStore } from 'pinia'
import { useAdminApi } from '~/composables/useAdminApi'
import { ApiClientError } from '~/composables/useRefundApi'
import type {
  RefundRequestDetail,
  RefundReviewDecision,
  RefundReviewSubmission,
} from '~/types/admin'

interface ReviewValidationErrors {
  decision?: string
  review_note?: string
}

interface AdminRefundCaseState {
  refundRequest: RefundRequestDetail | null
  isLoading: boolean
  isSubmittingReview: boolean
  loadError: string | null
  reviewError: string | null
  reviewNotice: string | null
  reviewValidationErrors: ReviewValidationErrors
  requestVersion: number
}

function validationErrors(error: ApiClientError): ReviewValidationErrors {
  if (Array.isArray(error.details)) {
    return {}
  }

  const errors = error.details.errors
  if (typeof errors !== 'object' || errors === null || Array.isArray(errors)) {
    return {}
  }

  const firstMessage = (field: 'decision' | 'review_note'): string | undefined => {
    const messages = (errors as Record<string, unknown>)[field]

    return Array.isArray(messages) && typeof messages[0] === 'string'
      ? messages[0]
      : undefined
  }

  return {
    decision: firstMessage('decision'),
    review_note: firstMessage('review_note'),
  }
}

export const useAdminRefundCaseStore = defineStore('admin-refund-case', {
  state: (): AdminRefundCaseState => ({
    refundRequest: null,
    isLoading: false,
    isSubmittingReview: false,
    loadError: null,
    reviewError: null,
    reviewNotice: null,
    reviewValidationErrors: {},
    requestVersion: 0,
  }),

  actions: {
    async loadRefundRequest(refundRequestId: number, preserveCase = false): Promise<boolean> {
      const requestVersion = ++this.requestVersion
      this.isLoading = true
      this.loadError = null

      if (!preserveCase) {
        this.refundRequest = null
        this.reviewError = null
        this.reviewNotice = null
        this.reviewValidationErrors = {}
      }

      try {
        const refundRequest = await useAdminApi().getRefundRequest(refundRequestId)

        if (requestVersion !== this.requestVersion) {
          return false
        }

        this.refundRequest = refundRequest

        return true
      }
      catch (error) {
        if (requestVersion !== this.requestVersion) {
          return false
        }

        this.loadError = error instanceof ApiClientError && error.status === 404
          ? 'This refund request could not be found.'
          : 'We could not load this refund case. Please try again.'

        return false
      }
      finally {
        if (requestVersion === this.requestVersion) {
          this.isLoading = false
        }
      }
    },

    async submitReview(decision: RefundReviewDecision, reviewNote: string): Promise<boolean> {
      if (this.refundRequest === null) {
        return false
      }

      const refundRequestId = this.refundRequest.id
      const submission: RefundReviewSubmission = {
        decision,
        review_note: reviewNote.trim() || null,
      }

      this.isSubmittingReview = true
      this.reviewError = null
      this.reviewNotice = null
      this.reviewValidationErrors = {}

      try {
        this.refundRequest = await useAdminApi().reviewRefundRequest(refundRequestId, submission)
        this.reviewNotice = decision === 'approved'
          ? 'The refund request was approved.'
          : 'The refund request was denied.'

        return true
      }
      catch (error) {
        if (error instanceof ApiClientError && error.status === 409) {
          const refreshed = await this.loadRefundRequest(refundRequestId, true)
          this.reviewError = refreshed
            ? 'This case was already reviewed. The latest case details are now shown.'
            : 'This case was already reviewed, but the latest details could not be loaded. Please try again.'

          return false
        }

        if (error instanceof ApiClientError && error.status === 422) {
          this.reviewValidationErrors = validationErrors(error)
          this.reviewError = 'Please correct the review details and try again.'

          return false
        }

        this.reviewError = 'We could not submit this review. Please try again.'

        return false
      }
      finally {
        this.isSubmittingReview = false
      }
    },

    clearReviewFeedback(): void {
      this.reviewError = null
      this.reviewNotice = null
      this.reviewValidationErrors = {}
    },
  },
})
