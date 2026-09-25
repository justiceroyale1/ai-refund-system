<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted } from 'vue'
import { toast } from 'vue-sonner'
import { isCustomerNotificationBroadcast } from '~/lib/notifications'
import {
  createNotificationRealtimeConnection,
  type NotificationRealtimeConnection,
} from '~/lib/realtime'
import { useConversationStore } from '~/stores/conversations'
import { useCustomerNotificationStore } from '~/stores/customerNotifications'
import { useCustomerStore } from '~/stores/customer'

const route = useRoute()
const customerStore = useCustomerStore()
const conversationStore = useConversationStore()
const notificationStore = useCustomerNotificationStore()
const config = useRuntimeConfig()
let realtimeConnection: NotificationRealtimeConnection | null = null

await useAsyncData('customer-support-bootstrap', async () => {
  await customerStore.loadCustomers()

  if (customerStore.selectedCustomerId !== null && !conversationStore.hasLoadedHistory) {
    await conversationStore.loadHistory()
  }

  if (customerStore.selectedCustomerId !== null) {
    await notificationStore.load()
  }

  return true
})

const activeConversationId = computed(() => {
  const routeId = route.params.id

  return typeof routeId === 'string' ? routeId : null
})

async function selectCustomer(customerId: number): Promise<void> {
  disconnectRealtime()
  notificationStore.resetScope(customerId)
  await conversationStore.switchCustomer(customerId)
  await notificationStore.load()
  connectRealtime(customerId)

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

function disconnectRealtime(): void {
  realtimeConnection?.disconnect()
  realtimeConnection = null
}

function connectRealtime(customerId: number): void {
  if (!import.meta.client) {
    return
  }

  const port = Number(config.public.reverbPort)

  if (!config.public.reverbAppKey || !config.public.reverbHost || !Number.isInteger(port)) {
    return
  }

  disconnectRealtime()
  realtimeConnection = createNotificationRealtimeConnection({
    apiBase: config.public.apiBase,
    appKey: config.public.reverbAppKey,
    host: config.public.reverbHost,
    port,
    scheme: config.public.reverbScheme === 'https' ? 'https' : 'http',
    channelName: `customers.${customerId}`,
    authHeaders: {
      'X-Demo-Customer-Id': String(customerId),
    },
    onNotification(payload): void {
      if (!isCustomerNotificationBroadcast(payload) || !notificationStore.receiveBroadcast(payload)) {
        return
      }

      toast.info(payload.title, {
        description: payload.message,
        action: {
          label: 'View',
          onClick: () => {
            const notification = notificationStore.notifications.find(item => item.id === payload.id)

            if (notification) {
              void openNotification(notification.id)
            }
          },
        },
      })
    },
    onReconnect: () => notificationStore.load(),
  })
}

async function openNotification(notificationId: string): Promise<void> {
  const notification = notificationStore.notifications.find(item => item.id === notificationId)

  if (!notification) {
    return
  }

  try {
    await notificationStore.markRead(notificationId)
  }
  finally {
    await navigateTo(`/support/conversations/${notification.conversation_id}`)
  }
}

onMounted(() => {
  if (customerStore.selectedCustomerId !== null) {
    connectRealtime(customerStore.selectedCustomerId)
  }
})

onBeforeUnmount(disconnectRealtime)
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

        <div class="flex min-w-0 items-center gap-2">
          <NotificationCenter
            :notifications="notificationStore.notifications"
            :unread-count="notificationStore.unreadCount"
            :loading="notificationStore.isLoading"
            :error="notificationStore.error"
            :has-more="notificationStore.hasMore"
            label="Customer notifications"
            @select="openNotification"
            @mark-all-read="notificationStore.markAllRead()"
            @retry="notificationStore.load()"
            @load-more="notificationStore.loadMore()"
          />
          <CustomerSwitcher
            :customers="customerStore.customers"
            :selected-customer-id="customerStore.selectedCustomerId"
            :disabled="customerStore.isLoading || conversationStore.isLoadingHistory"
            @change="selectCustomer"
          />
        </div>
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
