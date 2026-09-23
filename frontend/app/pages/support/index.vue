<script setup lang="ts">
import { LifeBuoy, LoaderCircle, Plus } from '@lucide/vue'
import { Button } from '~/components/ui/button'
import { useConversationStore } from '~/stores/conversations'
import { useCustomerStore } from '~/stores/customer'

definePageMeta({
  layout: 'customer',
})

useHead({
  title: 'Customer support · AI Refund System',
})

const customerStore = useCustomerStore()
const conversationStore = useConversationStore()

await customerStore.loadCustomers()

if (customerStore.selectedCustomerId !== null && !conversationStore.hasLoadedHistory) {
  await conversationStore.loadHistory()
}

if (conversationStore.preferredConversation) {
  await navigateTo(
    `/support/conversations/${conversationStore.preferredConversation.id}`,
    { replace: true },
  )
}

async function startConversation(): Promise<void> {
  const conversation = await conversationStore.createConversation()

  if (conversation) {
    await navigateTo(`/support/conversations/${conversation.id}`)
  }
}
</script>

<template>
  <section class="grid min-h-full place-items-center p-6">
    <div v-if="conversationStore.isLoadingHistory" class="text-center">
      <LoaderCircle class="mx-auto size-6 animate-spin text-muted-foreground" aria-hidden="true" />
      <p class="mt-3 text-sm text-muted-foreground">
        Loading your conversations…
      </p>
    </div>

    <div v-else class="max-w-md text-center">
      <span class="mx-auto grid size-14 place-items-center rounded-2xl bg-primary text-primary-foreground shadow-sm">
        <LifeBuoy class="size-7" aria-hidden="true" />
      </span>
      <h1 class="mt-5 text-2xl font-semibold tracking-tight">
        How can we help?
      </h1>
      <p class="mt-2 text-pretty text-sm leading-6 text-muted-foreground">
        Start a refund request and we’ll help identify the order, item, and next step.
      </p>
      <Button
        class="mt-6"
        :disabled="conversationStore.isCreatingConversation || customerStore.selectedCustomerId === null"
        @click="startConversation"
      >
        <LoaderCircle
          v-if="conversationStore.isCreatingConversation"
          class="size-4 animate-spin"
          aria-hidden="true"
        />
        <Plus v-else class="size-4" aria-hidden="true" />
        {{ conversationStore.isCreatingConversation ? 'Starting request' : 'Start a refund request' }}
      </Button>
      <p v-if="conversationStore.conversationError" role="alert" class="mt-4 text-sm text-destructive">
        {{ conversationStore.conversationError }}
      </p>
    </div>
  </section>
</template>
