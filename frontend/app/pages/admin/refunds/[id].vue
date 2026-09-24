<script setup lang="ts">
import {
  ArrowLeft,
  Bot,
  CircleAlert,
  ClipboardCheck,
  Clock3,
  LoaderCircle,
  Package,
  ReceiptText,
  RotateCcw,
  UserRound,
} from '@lucide/vue'
import { computed, watch } from 'vue'
import AdminCaseTranscript from '~/components/admin/AdminCaseTranscript.vue'
import AdminReviewPanel from '~/components/admin/AdminReviewPanel.vue'
import SemanticBadge from '~/components/admin/SemanticBadge.vue'
import { Button } from '~/components/ui/button'
import {
  confidenceTone,
  formatBoolean,
  formatExtractedValue,
  semanticTone,
  signalTone,
} from '~/lib/admin'
import { formatCurrencyCents, formatDateTime, formatMachineValue } from '~/lib/formatters'
import { useAdminRefundCaseStore } from '~/stores/adminRefundCase'
import type { RefundReviewDecision } from '~/types/admin'

definePageMeta({
  layout: 'admin',
  middleware: 'admin-auth',
})

useHead({
  title: 'Refund case · AI Refund System',
})

const route = useRoute()
const caseStore = useAdminRefundCaseStore()
const refundRequest = computed(() => caseStore.refundRequest)
const extractedEntries = computed(() => Object.entries(
  refundRequest.value?.latest_ai_analysis?.extracted_data ?? {},
))
const isReviewable = computed(() => {
  const currentCase = refundRequest.value

  return currentCase?.initial_decision === 'escalated'
    && currentCase.decision === 'escalated'
    && currentCase.reviewer === null
})

function routeRefundRequestId(): number | null {
  const rawId = Array.isArray(route.params.id) ? route.params.id[0] : route.params.id

  if (typeof rawId !== 'string' || !/^\d+$/.test(rawId)) {
    return null
  }

  const id = Number(rawId)

  return Number.isSafeInteger(id) && id > 0 ? id : null
}

async function loadCase(): Promise<void> {
  const refundRequestId = routeRefundRequestId()

  if (refundRequestId === null) {
    caseStore.$patch({
      refundRequest: null,
      loadError: 'This refund request could not be found.',
      isLoading: false,
    })

    return
  }

  await caseStore.loadRefundRequest(refundRequestId)
}

async function submitReview(decision: RefundReviewDecision, reviewNote: string): Promise<void> {
  await caseStore.submitReview(decision, reviewNote)
}

await loadCase()

watch(() => route.params.id, loadCase)
</script>

<template>
  <section>
    <NuxtLink
      to="/admin"
      class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground"
    >
      <ArrowLeft class="size-4" aria-hidden="true" />
      Back to dashboard
    </NuxtLink>

    <div
      v-if="caseStore.isLoading && !refundRequest"
      class="mt-8 grid min-h-72 place-items-center rounded-xl border bg-card"
      data-testid="refund-case-loading"
    >
      <div class="text-center text-sm text-muted-foreground">
        <LoaderCircle class="mx-auto mb-3 size-6 animate-spin" aria-hidden="true" />
        Loading refund case…
      </div>
    </div>

    <div
      v-else-if="caseStore.loadError && !refundRequest"
      class="mt-8 rounded-xl border border-destructive/20 bg-destructive/5 p-6 text-center"
    >
      <CircleAlert class="mx-auto size-7 text-destructive" aria-hidden="true" />
      <p role="alert" class="mt-3 font-medium text-destructive">
        {{ caseStore.loadError }}
      </p>
      <Button class="mt-4" variant="outline" @click="loadCase">
        <RotateCcw class="size-4" aria-hidden="true" />
        Try again
      </Button>
    </div>

    <template v-else-if="refundRequest">
      <header class="mt-5 flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p class="text-sm font-medium text-muted-foreground">
            Refund case #{{ refundRequest.id }}
          </p>
          <h1 class="mt-1 text-3xl font-semibold tracking-tight">
            {{ refundRequest.order?.reference ?? 'Order unavailable' }}
          </h1>
          <p class="mt-2 text-sm text-muted-foreground">
            Opened {{ formatDateTime(refundRequest.created_at) }}
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <SemanticBadge :value="refundRequest.initial_decision" :label="`Initial: ${formatMachineValue(refundRequest.initial_decision)}`" />
          <SemanticBadge :value="refundRequest.decision" :label="`Current: ${refundRequest.decision ? formatMachineValue(refundRequest.decision) : 'Not available'}`" />
          <SemanticBadge
            v-if="refundRequest.refund"
            :value="refundRequest.refund.status"
            :label="`Execution: ${formatMachineValue(refundRequest.refund.status)}`"
          />
        </div>
      </header>

      <div
        v-if="caseStore.reviewNotice"
        role="status"
        class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"
      >
        {{ caseStore.reviewNotice }}
      </div>

      <div
        v-if="caseStore.reviewError && !isReviewable"
        role="alert"
        class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800"
      >
        {{ caseStore.reviewError }}
      </div>

      <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-6">
          <section class="rounded-xl border bg-card p-5" aria-labelledby="case-overview-heading">
            <div class="flex items-center gap-2">
              <ReceiptText class="size-5 text-muted-foreground" aria-hidden="true" />
              <h2 id="case-overview-heading" class="text-lg font-semibold">
                Case overview
              </h2>
            </div>

            <dl class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
              <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Customer</dt>
                <dd class="mt-1 font-medium">{{ refundRequest.customer?.name ?? 'Not available' }}</dd>
                <dd class="text-sm text-muted-foreground">{{ refundRequest.customer?.email ?? 'Not available' }}</dd>
              </div>
              <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Order</dt>
                <dd class="mt-1 font-medium">{{ refundRequest.order?.reference ?? 'Not available' }}</dd>
                <dd class="mt-1"><SemanticBadge :value="refundRequest.order?.status ?? null" /></dd>
              </div>
              <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Order item</dt>
                <dd class="mt-1 font-medium">{{ refundRequest.order_item?.name ?? 'Not available' }}</dd>
                <dd class="text-sm text-muted-foreground">{{ refundRequest.order_item?.sku ?? 'SKU unavailable' }}</dd>
              </div>
              <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Refund amount</dt>
                <dd class="mt-1 font-medium">{{ formatCurrencyCents(refundRequest.amount_cents) }}</dd>
              </div>
              <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Reason</dt>
                <dd class="mt-1 font-medium">{{ formatMachineValue(refundRequest.reason) }}</dd>
              </div>
              <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Final sale</dt>
                <dd class="mt-1 font-medium">{{ refundRequest.order_item ? formatBoolean(refundRequest.order_item.final_sale) : 'Not available' }}</dd>
              </div>
            </dl>

            <div class="mt-5 border-t pt-5">
              <h3 class="text-sm font-medium">Customer-provided details</h3>
              <p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-muted-foreground">
                {{ refundRequest.reason_details ?? 'No additional reason details were provided.' }}
              </p>
            </div>
          </section>

          <section aria-labelledby="transcript-heading">
            <div class="mb-3 flex items-center gap-2">
              <UserRound class="size-5 text-muted-foreground" aria-hidden="true" />
              <h2 id="transcript-heading" class="text-lg font-semibold">Conversation transcript</h2>
            </div>
            <AdminCaseTranscript
              :messages="refundRequest.conversation?.messages ?? []"
              :customer-name="refundRequest.customer?.name ?? 'Customer'"
            />
          </section>

          <section class="rounded-xl border bg-card p-5" aria-labelledby="ai-heading">
            <div class="flex items-center gap-2">
              <Bot class="size-5 text-muted-foreground" aria-hidden="true" />
              <h2 id="ai-heading" class="text-lg font-semibold">AI analysis</h2>
            </div>

            <template v-if="refundRequest.latest_ai_analysis">
              <dl class="mt-5 grid gap-4 sm:grid-cols-3">
                <div>
                  <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Confidence</dt>
                  <dd class="mt-2">
                    <SemanticBadge
                      :value="null"
                      :label="`${refundRequest.latest_ai_analysis.confidence}%`"
                      :tone="confidenceTone(refundRequest.latest_ai_analysis.confidence)"
                    />
                  </dd>
                </div>
                <div>
                  <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Prompt injection detected</dt>
                  <dd class="mt-2">
                    <SemanticBadge
                      :value="null"
                      :label="formatBoolean(refundRequest.latest_ai_analysis.prompt_injection_detected)"
                      :tone="signalTone(refundRequest.latest_ai_analysis.prompt_injection_detected)"
                    />
                  </dd>
                </div>
                <div>
                  <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Conflicting information</dt>
                  <dd class="mt-2">
                    <SemanticBadge
                      :value="null"
                      :label="formatBoolean(refundRequest.latest_ai_analysis.conflicting_information)"
                      :tone="signalTone(refundRequest.latest_ai_analysis.conflicting_information)"
                    />
                  </dd>
                </div>
              </dl>

              <div class="mt-5 border-t pt-5">
                <h3 class="text-sm font-medium">Extracted information</h3>
                <dl v-if="extractedEntries.length" class="mt-3 grid gap-3 sm:grid-cols-2">
                  <div v-for="[key, value] in extractedEntries" :key="key" class="rounded-lg bg-muted/50 p-3">
                    <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ formatMachineValue(key) }}</dt>
                    <dd class="mt-1 text-sm">{{ formatExtractedValue(value) }}</dd>
                  </div>
                </dl>
                <p v-else class="mt-2 text-sm text-muted-foreground">No extracted fields are available.</p>
              </div>
            </template>
            <p v-else class="mt-4 text-sm text-muted-foreground">No AI analysis is available for this case.</p>
          </section>

          <section class="rounded-xl border bg-card p-5" aria-labelledby="policy-heading">
            <div class="flex items-center gap-2">
              <ClipboardCheck class="size-5 text-muted-foreground" aria-hidden="true" />
              <h2 id="policy-heading" class="text-lg font-semibold">Policy checks</h2>
            </div>
            <ul v-if="refundRequest.policy_checks.length" class="mt-4 divide-y">
              <li v-for="check in refundRequest.policy_checks" :key="check.code" class="flex flex-col gap-2 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-start sm:justify-between">
                <div>
                  <p class="font-medium">{{ formatMachineValue(check.code) }}</p>
                  <p class="mt-1 text-sm text-muted-foreground">{{ check.message }}</p>
                </div>
                <SemanticBadge :value="check.result" :tone="semanticTone(check.result)" />
              </li>
            </ul>
            <p v-else class="mt-4 text-sm text-muted-foreground">No policy checks are available.</p>
          </section>

          <section class="rounded-xl border bg-card p-5" aria-labelledby="audit-heading">
            <div class="flex items-center gap-2">
              <Clock3 class="size-5 text-muted-foreground" aria-hidden="true" />
              <h2 id="audit-heading" class="text-lg font-semibold">Audit timeline</h2>
            </div>
            <ol v-if="refundRequest.audit_timeline.length" class="mt-5 space-y-4">
              <li v-for="entry in refundRequest.audit_timeline" :key="`${entry.subject_type}-${entry.id}`" class="relative border-l pl-5">
                <span class="absolute -left-1.5 top-1.5 size-3 rounded-full border-2 border-background bg-slate-400" aria-hidden="true" />
                <p class="font-medium">{{ formatMachineValue(entry.event) }}</p>
                <p class="mt-1 text-sm text-muted-foreground">
                  {{ formatMachineValue(entry.actor_type) }} · {{ formatMachineValue(entry.subject_type) }} · {{ formatDateTime(entry.created_at) }}
                </p>
              </li>
            </ol>
            <p v-else class="mt-4 text-sm text-muted-foreground">No audit events are available.</p>
          </section>
        </div>

        <aside class="space-y-6">
          <AdminReviewPanel
            v-if="isReviewable"
            :submitting="caseStore.isSubmittingReview"
            :error="caseStore.reviewError"
            :validation-errors="caseStore.reviewValidationErrors"
            @submit="submitReview"
            @cancel="caseStore.clearReviewFeedback"
          />

          <section class="rounded-xl border bg-card p-5" aria-labelledby="decision-heading">
            <h2 id="decision-heading" class="text-lg font-semibold">Decision</h2>
            <dl class="mt-4 space-y-4 text-sm">
              <div class="flex items-start justify-between gap-3">
                <dt class="text-muted-foreground">Initial decision</dt>
                <dd><SemanticBadge :value="refundRequest.initial_decision" /></dd>
              </div>
              <div class="flex items-start justify-between gap-3">
                <dt class="text-muted-foreground">Current decision</dt>
                <dd><SemanticBadge :value="refundRequest.decision" /></dd>
              </div>
              <div class="flex items-start justify-between gap-3">
                <dt class="text-muted-foreground">Decision source</dt>
                <dd class="text-right font-medium">{{ formatMachineValue(refundRequest.decision_source) }}</dd>
              </div>
              <div class="flex items-start justify-between gap-3">
                <dt class="text-muted-foreground">Decision reason</dt>
                <dd class="max-w-44 text-right font-medium">{{ formatMachineValue(refundRequest.decision_code) }}</dd>
              </div>
              <div class="flex items-start justify-between gap-3">
                <dt class="text-muted-foreground">Decided</dt>
                <dd class="text-right font-medium">{{ refundRequest.decided_at ? formatDateTime(refundRequest.decided_at) : 'Awaiting review' }}</dd>
              </div>
            </dl>

            <div v-if="refundRequest.reviewer || refundRequest.review_note" class="mt-5 border-t pt-5">
              <h3 class="text-sm font-medium">Human review</h3>
              <p class="mt-2 text-sm">{{ refundRequest.reviewer?.name ?? 'Reviewer unavailable' }}</p>
              <p v-if="refundRequest.reviewer" class="text-xs text-muted-foreground">{{ refundRequest.reviewer.email }}</p>
              <p class="mt-3 whitespace-pre-wrap rounded-lg bg-muted/50 p-3 text-sm text-muted-foreground">
                {{ refundRequest.review_note ?? 'No internal review note was added.' }}
              </p>
            </div>
          </section>

          <section class="rounded-xl border bg-card p-5" aria-labelledby="execution-heading">
            <div class="flex items-center gap-2">
              <Package class="size-5 text-muted-foreground" aria-hidden="true" />
              <h2 id="execution-heading" class="text-lg font-semibold">Refund execution</h2>
            </div>

            <dl v-if="refundRequest.refund" class="mt-4 space-y-4 text-sm">
              <div class="flex items-start justify-between gap-3">
                <dt class="text-muted-foreground">Status</dt>
                <dd><SemanticBadge :value="refundRequest.refund.status" /></dd>
              </div>
              <div class="flex items-start justify-between gap-3">
                <dt class="text-muted-foreground">Amount</dt>
                <dd class="font-medium">{{ formatCurrencyCents(refundRequest.refund.amount_cents) }}</dd>
              </div>
              <div class="flex items-start justify-between gap-3">
                <dt class="text-muted-foreground">Processor</dt>
                <dd class="font-medium">{{ formatMachineValue(refundRequest.refund.processor) }}</dd>
              </div>
              <div class="flex items-start justify-between gap-3">
                <dt class="text-muted-foreground">Attempts</dt>
                <dd class="font-medium">{{ refundRequest.refund.attempts }}</dd>
              </div>
              <div v-if="refundRequest.refund.processor_reference" class="flex items-start justify-between gap-3">
                <dt class="text-muted-foreground">Processor reference</dt>
                <dd class="max-w-44 break-all text-right font-medium">{{ refundRequest.refund.processor_reference }}</dd>
              </div>
              <div v-if="refundRequest.refund.processed_at" class="flex items-start justify-between gap-3">
                <dt class="text-muted-foreground">Processed</dt>
                <dd class="text-right font-medium">{{ formatDateTime(refundRequest.refund.processed_at) }}</dd>
              </div>
            </dl>
            <p v-else class="mt-4 text-sm text-muted-foreground">No refund has been created for this case.</p>

            <div
              v-if="refundRequest.refund?.last_error"
              class="mt-5 rounded-lg border border-red-200 bg-red-50 p-3"
              data-testid="processor-error"
            >
              <p class="text-sm font-medium text-red-800">Processor error (read-only)</p>
              <p class="mt-1 whitespace-pre-wrap text-sm text-red-700">{{ refundRequest.refund.last_error }}</p>
              <p class="mt-2 text-xs text-red-700/80">No retry or reset action is available.</p>
            </div>
          </section>
        </aside>
      </div>
    </template>
  </section>
</template>
