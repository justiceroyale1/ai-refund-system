<script setup lang="ts">
import { AlertCircle, ArrowRight, Inbox, LoaderCircle } from '@lucide/vue'
import { Button } from '~/components/ui/button'
import { formatCurrencyCents, formatDateTime, formatMachineValue } from '~/lib/formatters'
import type { RefundRequestSummary } from '~/types/admin'

defineProps<{
  requests: RefundRequestSummary[]
  loading: boolean
  error: string | null
}>()

defineEmits<{
  retry: []
}>()
</script>

<template>
  <div
    v-if="loading"
    data-testid="refund-requests-loading"
    class="grid min-h-64 place-items-center rounded-xl border bg-card"
  >
    <div class="text-center">
      <LoaderCircle class="mx-auto size-6 animate-spin text-muted-foreground" aria-hidden="true" />
      <p class="mt-3 text-sm text-muted-foreground">
        Loading refund requests…
      </p>
    </div>
  </div>

  <div
    v-else-if="error"
    data-testid="refund-requests-error"
    class="grid min-h-64 place-items-center rounded-xl border bg-card p-6"
  >
    <div class="max-w-sm text-center">
      <AlertCircle class="mx-auto size-7 text-destructive" aria-hidden="true" />
      <p role="alert" class="mt-3 text-sm text-muted-foreground">
        {{ error }}
      </p>
      <Button class="mt-4" variant="outline" size="sm" @click="$emit('retry')">
        Try again
      </Button>
    </div>
  </div>

  <div
    v-else-if="requests.length === 0"
    data-testid="refund-requests-empty"
    class="grid min-h-64 place-items-center rounded-xl border bg-card p-6"
  >
    <div class="max-w-sm text-center">
      <Inbox class="mx-auto size-8 text-muted-foreground" aria-hidden="true" />
      <h2 class="mt-3 font-semibold">
        No refund requests found
      </h2>
      <p class="mt-1 text-sm text-muted-foreground">
        Try clearing or changing the current filters.
      </p>
    </div>
  </div>

  <div v-else class="overflow-hidden rounded-xl border bg-card shadow-xs">
    <div class="hidden overflow-x-auto md:block">
      <table class="w-full text-left text-sm">
        <thead class="border-b bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
          <tr>
            <th scope="col" class="px-5 py-3 font-medium">Customer</th>
            <th scope="col" class="px-5 py-3 font-medium">Order</th>
            <th scope="col" class="px-5 py-3 font-medium">Amount</th>
            <th scope="col" class="px-5 py-3 font-medium">Decision</th>
            <th scope="col" class="px-5 py-3 font-medium">Execution</th>
            <th scope="col" class="px-5 py-3"><span class="sr-only">Open request</span></th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="request in requests" :key="request.id" class="hover:bg-muted/30">
            <td class="px-5 py-4">
              <p class="font-medium">{{ request.customer?.name ?? 'Unknown customer' }}</p>
              <p class="mt-0.5 text-xs text-muted-foreground">{{ request.customer?.email }}</p>
            </td>
            <td class="px-5 py-4">
              <p class="font-medium">{{ request.order?.reference ?? 'Unknown order' }}</p>
              <p class="mt-0.5 text-xs text-muted-foreground">{{ request.order_item?.name }}</p>
            </td>
            <td class="px-5 py-4 font-medium">{{ formatCurrencyCents(request.amount_cents) }}</td>
            <td class="px-5 py-4"><AdminSemanticBadge :value="request.decision" /></td>
            <td class="px-5 py-4"><AdminSemanticBadge :value="request.execution_status" /></td>
            <td class="px-5 py-4 text-right">
              <NuxtLink
                :to="`/admin/refunds/${request.id}`"
                class="inline-flex items-center gap-1 font-medium text-foreground hover:underline"
                :aria-label="`Open refund request ${request.id}`"
              >
                View <ArrowRight class="size-4" aria-hidden="true" />
              </NuxtLink>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="divide-y md:hidden">
      <NuxtLink
        v-for="request in requests"
        :key="request.id"
        :to="`/admin/refunds/${request.id}`"
        class="block space-y-3 p-4 hover:bg-muted/30"
      >
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="font-medium">{{ request.customer?.name ?? 'Unknown customer' }}</p>
            <p class="mt-0.5 text-xs text-muted-foreground">
              {{ request.order?.reference ?? 'Unknown order' }} · {{ formatMachineValue(request.reason) }}
            </p>
          </div>
          <ArrowRight class="mt-1 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <AdminSemanticBadge :value="request.decision" />
          <AdminSemanticBadge :value="request.execution_status" />
        </div>
        <div class="flex items-center justify-between text-xs text-muted-foreground">
          <span>{{ formatDateTime(request.created_at) }}</span>
          <span class="font-medium text-foreground">{{ formatCurrencyCents(request.amount_cents) }}</span>
        </div>
      </NuxtLink>
    </div>
  </div>
</template>
