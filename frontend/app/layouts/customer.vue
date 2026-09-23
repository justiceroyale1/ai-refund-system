<script setup lang="ts">
import { computed } from 'vue'
import { useConversationStore } from '~/stores/conversations'
import { useCustomerStore } from '~/stores/customer'

const route = useRoute()
const customerStore = useCustomerStore()
const conversationStore = useConversationStore()

await useAsyncData('customer-support-bootstrap', async () => {
  await customerStore.loadCustomers()

  if (customerStore.selectedCustomerId !== null && !conversationStore.hasLoadedHistory) {
    await conversationStore.loadHistory()
  }

  return true
})

const activeConversationId = computed(() => {
  const routeId = route.params.id

  return typeof routeId === 'string' ? routeId : null
})

async function selectCustomer(customerId: number): Promise<void> {
  await conversationStore.switchCustomer(customerId)

  const preferredConversation = conversationStore.preferredConversation
  await navigateTo(
    preferredConversation
      ? `/support/conversations/${preferredConversation.id}`
      : '/support',
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
  <div class="flex h-dvh min-h-[36rem] overflow-hidden bg-muted/30 text-foreground">
    <CustomerShellNavigation
      mode="desktop"
      :conversations="conversationStore.history"
      :active-conversation-id="activeConversationId"
      :loading="conversationStore.isLoadingHistory"
      :creating="conversationStore.isCreatingConversation"
      :error="conversationStore.historyError"
      :has-more="conversationStore.hasMoreHistory"
      :route-path="route.fullPath"
      @new-conversation="startConversation"
      @retry="conversationStore.loadHistory()"
      @load-more="conversationStore.loadMoreHistory()"
    />

    <div class="flex min-w-0 flex-1 flex-col">
      <header class="flex h-16 shrink-0 items-center justify-between gap-3 border-b bg-background px-4 sm:px-6">
        <div class="flex min-w-0 items-center gap-3">
          <CustomerShellNavigation
            mode="mobile"
            :conversations="conversationStore.history"
            :active-conversation-id="activeConversationId"
            :loading="conversationStore.isLoadingHistory"
            :creating="conversationStore.isCreatingConversation"
            :error="conversationStore.historyError"
            :has-more="conversationStore.hasMoreHistory"
            :route-path="route.fullPath"
            @new-conversation="startConversation"
            @retry="conversationStore.loadHistory()"
            @load-more="conversationStore.loadMoreHistory()"
          />
          <div class="min-w-0">
            <p class="truncate text-sm font-semibold">
              Customer support
            </p>
            <p class="hidden truncate text-xs text-muted-foreground sm:block">
              Refund help for your recent orders
            </p>
          </div>
        </div>

        <CustomerSwitcher
          :customers="customerStore.customers"
          :selected-customer-id="customerStore.selectedCustomerId"
          :disabled="customerStore.isLoading || conversationStore.isLoadingHistory"
          @change="selectCustomer"
        />
      </header>

      <main class="min-h-0 flex-1 overflow-y-auto">
        <div
          v-if="customerStore.error"
          role="alert"
          class="m-4 rounded-lg border border-destructive/20 bg-destructive/5 p-4 text-sm text-destructive"
        >
          {{ customerStore.error }}
        </div>
        <slot v-else />
      </main>
    </div>
  </div>
</template>
