<script setup lang="ts">
import { LayoutDashboard, LoaderCircle, LogOut, ShieldCheck } from '@lucide/vue'
import { onBeforeUnmount, onMounted } from 'vue'
import { toast } from 'vue-sonner'
import { Button } from '~/components/ui/button'
import { isAdminNotificationBroadcast } from '~/lib/notifications'
import {
  createNotificationRealtimeConnection,
  type NotificationRealtimeConnection,
} from '~/lib/realtime'
import { useAdminNotificationStore } from '~/stores/adminNotifications'
import { useAdminSessionStore } from '~/stores/adminSession'

const sessionStore = useAdminSessionStore()
const notificationStore = useAdminNotificationStore()
const config = useRuntimeConfig()
let realtimeConnection: NotificationRealtimeConnection | null = null

await useAsyncData('admin-notification-bootstrap', async () => {
  await notificationStore.load()
  return true
})

function disconnectRealtime(): void {
  realtimeConnection?.disconnect()
  realtimeConnection = null
}

function connectRealtime(adminId: number): void {
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
    channelName: `admins.${adminId}`,
    onNotification(payload): void {
      if (!isAdminNotificationBroadcast(payload) || !notificationStore.receiveBroadcast(payload)) {
        return
      }

      const showToast = payload.type === 'refund_review_required'
        ? toast.warning
        : toast.error

      showToast(payload.title, {
        description: payload.message,
        action: {
          label: 'Review',
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
    await navigateTo(`/admin/refunds/${notification.refund_request_id}`)
  }
}

async function logout(): Promise<void> {
  disconnectRealtime()
  notificationStore.resetScope()
  await sessionStore.logout()
  await navigateTo('/admin/login')
}

onMounted(() => {
  if (sessionStore.admin !== null) {
    connectRealtime(sessionStore.admin.id)
  }
})

onBeforeUnmount(disconnectRealtime)
</script>

<template>
  <div class="min-h-dvh bg-muted/30 text-foreground">
    <header class="border-b bg-background">
      <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
        <NuxtLink to="/admin" class="flex min-w-0 items-center gap-3">
          <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary text-primary-foreground">
            <ShieldCheck class="size-5" aria-hidden="true" />
          </span>
          <span class="min-w-0">
            <span class="block truncate text-sm font-semibold">AI Refund System</span>
            <span class="block truncate text-xs text-muted-foreground">Support administration</span>
          </span>
        </NuxtLink>

        <div class="flex items-center gap-2 sm:gap-4">
          <NuxtLink
            to="/admin"
            class="hidden items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground sm:flex"
          >
            <LayoutDashboard class="size-4" aria-hidden="true" />
            Dashboard
          </NuxtLink>
          <div class="hidden text-right md:block">
            <p class="text-sm font-medium">{{ sessionStore.admin?.name }}</p>
            <p class="text-xs text-muted-foreground">{{ sessionStore.admin?.email }}</p>
          </div>
          <NotificationCenter
            :notifications="notificationStore.notifications"
            :unread-count="notificationStore.unreadCount"
            :loading="notificationStore.isLoading"
            :error="notificationStore.error"
            :has-more="notificationStore.hasMore"
            label="Administrator notifications"
            @select="openNotification"
            @mark-all-read="notificationStore.markAllRead()"
            @retry="notificationStore.load()"
            @load-more="notificationStore.loadMore()"
          />
          <Button
            variant="outline"
            size="sm"
            :disabled="sessionStore.isLoggingOut"
            @click="logout"
          >
            <LoaderCircle v-if="sessionStore.isLoggingOut" class="size-4 animate-spin" aria-hidden="true" />
            <LogOut v-else class="size-4" aria-hidden="true" />
            <span class="hidden sm:inline">Sign out</span>
            <span class="sr-only sm:hidden">Sign out</span>
          </Button>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
      <slot />
    </main>
  </div>
</template>
