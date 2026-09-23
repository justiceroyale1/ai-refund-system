<script setup lang="ts">
import { AlertCircle, ArrowLeft, CircleDashed, LoaderCircle, Package } from '@lucide/vue'
import { computed } from 'vue'
import { Button } from '~/components/ui/button'
import {
  formatConversationDateTime,
  getConversationItemLabel,
  getConversationStatusLabel,
  getConversationStatusTone,
  getConversationTitle,
} from '~/lib/conversations'
import { useConversationStore } from '~/stores/conversations'
import { useCustomerStore } from '~/stores/customer'

definePageMeta({
  layout: 'customer',
})

const route = useRoute()
const customerStore = useCustomerStore()
const conversationStore = useConversationStore()
const conversationId = computed(() => String(route.params.id))

await customerStore.loadCustomers()

if (customerStore.selectedCustomerId !== null && !conversationStore.hasLoadedHistory) {
  await conversationStore.loadHistory()
}

await useAsyncData(
  () => `customer-conversation-${customerStore.selectedCustomerId}-${conversationId.value}`,
  async () => {
    await conversationStore.loadConversation(conversationId.value)
    return true
  },
  { watch: [conversationId] },
)

const conversation = computed(() => conversationStore.currentConversation)
const toneClasses = computed(() => {
  if (!conversation.value) {
    return ''
  }

  return {
    blue: 'bg-blue-50 text-blue-700 ring-blue-600/20',
    green: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    neutral: 'bg-slate-100 text-slate-600 ring-slate-500/20',
    orange: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    red: 'bg-red-50 text-red-700 ring-red-600/20',
  }[getConversationStatusTone(conversation.value)]
})

useHead(() => ({
  title: conversation.value
    ? `${getConversationTitle(conversation.value)} · Customer support`
    : 'Conversation · Customer support',
}))
</script>

<template>
  <section class="mx-auto flex min-h-full w-full max-w-5xl flex-col p-4 sm:p-6 lg:p-8">
    <div v-if="conversationStore.isLoadingConversation" class="grid flex-1 place-items-center">
      <div class="text-center">
        <LoaderCircle class="mx-auto size-6 animate-spin text-muted-foreground" aria-hidden="true" />
        <p class="mt-3 text-sm text-muted-foreground">
          Loading conversation…
        </p>
      </div>
    </div>

    <div
      v-else-if="conversationStore.conversationError"
      class="grid flex-1 place-items-center"
    >
      <div class="max-w-md text-center">
        <span class="mx-auto grid size-12 place-items-center rounded-full bg-muted">
          <AlertCircle class="size-6 text-muted-foreground" aria-hidden="true" />
        </span>
        <h1 class="mt-4 text-xl font-semibold">
          {{ conversationStore.conversationNotFound ? 'Conversation not found' : 'Unable to load conversation' }}
        </h1>
        <p class="mt-2 text-sm leading-6 text-muted-foreground">
          {{ conversationStore.conversationError }}
        </p>
        <Button class="mt-5" variant="outline" as-child>
          <NuxtLink to="/support">
            <ArrowLeft class="size-4" aria-hidden="true" />
            Back to support
          </NuxtLink>
        </Button>
      </div>
    </div>

    <template v-else-if="conversation">
      <div class="rounded-xl border bg-card shadow-xs">
        <div class="flex flex-col gap-4 border-b p-5 sm:flex-row sm:items-start sm:justify-between sm:p-6">
          <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">
              Refund conversation
            </p>
            <h1 class="mt-2 truncate text-2xl font-semibold tracking-tight">
              {{ getConversationTitle(conversation) }}
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
              Updated {{ formatConversationDateTime(conversation.updated_at) }}
            </p>
          </div>
          <span
            class="inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset"
            :class="toneClasses"
          >
            {{ getConversationStatusLabel(conversation) }}
          </span>
        </div>

        <dl class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
          <div class="rounded-lg bg-muted/60 p-4">
            <dt class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
              <Package class="size-4" aria-hidden="true" />
              Item
            </dt>
            <dd class="mt-2 text-sm font-medium">
              {{ getConversationItemLabel(conversation) }}
            </dd>
          </div>
          <div class="rounded-lg bg-muted/60 p-4">
            <dt class="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
              <CircleDashed class="size-4" aria-hidden="true" />
              Request status
            </dt>
            <dd class="mt-2 text-sm font-medium">
              {{ getConversationStatusLabel(conversation) }}
            </dd>
          </div>
        </dl>
      </div>

      <div class="mt-4 grid flex-1 place-items-center rounded-xl border border-dashed bg-background p-8 text-center">
        <div class="max-w-sm">
          <h2 class="font-semibold">
            Conversation ready
          </h2>
          <p class="mt-2 text-sm leading-6 text-muted-foreground">
            Continue the guided refund conversation here. Messaging is added in the next customer-support step.
          </p>
        </div>
      </div>
    </template>
  </section>
</template>
