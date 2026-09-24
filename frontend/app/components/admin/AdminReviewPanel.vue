<script setup lang="ts">
import { CheckCircle2, LoaderCircle, ShieldCheck, XCircle } from '@lucide/vue'
import { ref } from 'vue'
import { Button } from '~/components/ui/button'
import type { RefundReviewDecision } from '~/types/admin'

const props = defineProps<{
  submitting: boolean
  error: string | null
  validationErrors: {
    decision?: string
    review_note?: string
  }
}>()

const emit = defineEmits<{
  submit: [decision: RefundReviewDecision, reviewNote: string]
  cancel: []
}>()

const selectedDecision = ref<RefundReviewDecision | null>(null)
const reviewNote = ref('')

function chooseDecision(decision: RefundReviewDecision): void {
  selectedDecision.value = decision
}

function cancelReview(): void {
  selectedDecision.value = null
  emit('cancel')
}

function submitReview(): void {
  if (selectedDecision.value === null || props.submitting) {
    return
  }

  emit('submit', selectedDecision.value, reviewNote.value)
}
</script>

<template>
  <section class="rounded-xl border border-amber-200 bg-amber-50/60 p-5" aria-labelledby="review-heading">
    <div class="flex gap-3">
      <span class="grid size-10 shrink-0 place-items-center rounded-full bg-amber-100 text-amber-700">
        <ShieldCheck class="size-5" aria-hidden="true" />
      </span>
      <div>
        <h2 id="review-heading" class="font-semibold">
          Human review required
        </h2>
        <p class="mt-1 text-sm text-muted-foreground">
          Review the complete case before making this one-time decision.
        </p>
      </div>
    </div>

    <div v-if="selectedDecision === null" class="mt-5 flex flex-col gap-3 sm:flex-row">
      <Button
        type="button"
        class="bg-emerald-700 hover:bg-emerald-700/90 sm:flex-1"
        :disabled="submitting"
        @click="chooseDecision('approved')"
      >
        <CheckCircle2 class="size-4" aria-hidden="true" />
        Approve request
      </Button>
      <Button
        type="button"
        variant="destructive"
        class="sm:flex-1"
        :disabled="submitting"
        @click="chooseDecision('denied')"
      >
        <XCircle class="size-4" aria-hidden="true" />
        Deny request
      </Button>
    </div>

    <form v-else class="mt-5" @submit.prevent="submitReview">
      <div class="rounded-lg border bg-background p-4">
        <p class="font-medium">
          Confirm {{ selectedDecision === 'approved' ? 'approval' : 'denial' }}
        </p>
        <p class="mt-1 text-sm text-muted-foreground">
          This decision is final. A second review attempt will be rejected.
        </p>

        <label for="review-note" class="mt-4 block text-sm font-medium">
          Internal review note <span class="font-normal text-muted-foreground">(optional)</span>
        </label>
        <textarea
          id="review-note"
          v-model="reviewNote"
          rows="4"
          maxlength="4000"
          :disabled="submitting"
          class="mt-2 w-full resize-y rounded-md border bg-background px-3 py-2 text-sm shadow-xs outline-none focus:border-ring focus:ring-3 focus:ring-ring/20 disabled:cursor-not-allowed disabled:opacity-60"
          :aria-invalid="Boolean(validationErrors.review_note)"
          :aria-describedby="validationErrors.review_note ? 'review-note-error' : undefined"
        />
        <div class="mt-1 flex items-start justify-between gap-3 text-xs">
          <p v-if="validationErrors.review_note" id="review-note-error" class="text-destructive">
            {{ validationErrors.review_note }}
          </p>
          <span v-else />
          <span class="text-muted-foreground">{{ reviewNote.length }}/4000</span>
        </div>

        <p v-if="validationErrors.decision" class="mt-3 text-sm text-destructive">
          {{ validationErrors.decision }}
        </p>
        <p v-if="error" role="alert" class="mt-3 text-sm text-destructive">
          {{ error }}
        </p>

        <div class="mt-4 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <Button type="button" variant="outline" :disabled="submitting" @click="cancelReview">
            Cancel
          </Button>
          <Button
            type="submit"
            :variant="selectedDecision === 'denied' ? 'destructive' : 'default'"
            :class="selectedDecision === 'approved' ? 'bg-emerald-700 hover:bg-emerald-700/90' : ''"
            :disabled="submitting"
          >
            <LoaderCircle v-if="submitting" class="size-4 animate-spin" aria-hidden="true" />
            <CheckCircle2 v-else-if="selectedDecision === 'approved'" class="size-4" aria-hidden="true" />
            <XCircle v-else class="size-4" aria-hidden="true" />
            {{ submitting ? 'Submitting…' : `Confirm ${selectedDecision === 'approved' ? 'approval' : 'denial'}` }}
          </Button>
        </div>
      </div>
    </form>

    <p v-if="error && selectedDecision === null" role="alert" class="mt-4 text-sm text-destructive">
      {{ error }}
    </p>
  </section>
</template>
