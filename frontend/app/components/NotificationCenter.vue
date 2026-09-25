<script setup lang="ts">
import { Bell, CheckCheck, LoaderCircle } from '@lucide/vue'
import { formatDateTime } from '~/lib/formatters'
import type { AdminNotification, CustomerNotification } from '~/types/notification'

type AppNotification = AdminNotification | CustomerNotification

const props = defineProps<{
  notifications: AppNotification[]
  unreadCount: number
  loading: boolean
  error: string | null
  hasMore: boolean
  label: string
}>()

const emit = defineEmits<{
  select: [notificationId: string]
  markAllRead: []
  retry: []
  loadMore: []
}>()
</script>

<template>
  <details class="group relative">
    <summary
      class="relative grid size-10 cursor-pointer list-none place-items-center rounded-md border bg-background text-muted-foreground shadow-xs transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring [&::-webkit-details-marker]:hidden"
      :aria-label="`${props.label}: ${props.unreadCount} unread`"
    >
      <Bell class="size-4" aria-hidden="true" />
      <span
        v-if="props.unreadCount > 0"
        class="absolute -right-1.5 -top-1.5 min-w-5 rounded-full bg-destructive px-1.5 py-0.5 text-center text-[0.625rem] font-bold leading-none text-white"
        aria-hidden="true"
      >
        {{ props.unreadCount > 99 ? '99+' : props.unreadCount }}
      </span>
    </summary>

    <section
      class="absolute right-0 z-40 mt-2 flex max-h-[min(32rem,calc(100dvh-5rem))] w-[min(24rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-xl border bg-popover text-popover-foreground shadow-xl"
      :aria-label="props.label"
    >
      <div class="flex items-center justify-between gap-3 border-b px-4 py-3">
        <div>
          <h2 class="text-sm font-semibold">Notifications</h2>
          <p class="text-xs text-muted-foreground">
            {{ props.unreadCount }} unread
          </p>
        </div>
        <button
          v-if="props.unreadCount > 0"
          type="button"
          class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          @click="emit('markAllRead')"
        >
          <CheckCheck class="size-3.5" aria-hidden="true" />
          Mark all read
        </button>
      </div>

      <div v-if="props.loading && props.notifications.length === 0" class="grid min-h-40 place-items-center p-6">
        <div class="text-center text-sm text-muted-foreground">
          <LoaderCircle class="mx-auto size-5 animate-spin" aria-hidden="true" />
          <p class="mt-2">Loading notifications…</p>
        </div>
      </div>

      <div v-else-if="props.error && props.notifications.length === 0" class="p-5 text-center">
        <p role="alert" class="text-sm text-destructive">
          {{ props.error }}
        </p>
        <button
          type="button"
          class="mt-3 rounded-md border px-3 py-1.5 text-xs font-medium hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          @click="emit('retry')"
        >
          Try again
        </button>
      </div>

      <p v-else-if="props.notifications.length === 0" class="p-6 text-center text-sm text-muted-foreground">
        No notifications yet.
      </p>

      <div v-else class="min-h-0 overflow-y-auto">
        <button
          v-for="notification in props.notifications"
          :key="notification.id"
          type="button"
          class="relative block w-full border-b px-4 py-3 text-left transition-colors last:border-b-0 hover:bg-accent focus-visible:z-10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"
          :class="notification.read_at === null ? 'bg-primary/5' : 'bg-background'"
          @click="emit('select', notification.id)"
        >
          <span
            v-if="notification.read_at === null"
            class="absolute left-1.5 top-5 size-1.5 rounded-full bg-primary"
            aria-label="Unread"
          />
          <span class="block text-sm font-medium">{{ notification.title }}</span>
          <span class="mt-1 block text-xs leading-5 text-muted-foreground">{{ notification.message }}</span>
          <time :datetime="notification.created_at" class="mt-1.5 block text-[0.6875rem] text-muted-foreground">
            {{ formatDateTime(notification.created_at) }}
          </time>
        </button>
      </div>

      <button
        v-if="props.hasMore"
        type="button"
        class="border-t px-4 py-2.5 text-xs font-medium text-muted-foreground hover:bg-accent hover:text-accent-foreground disabled:opacity-60"
        :disabled="props.loading"
        @click="emit('loadMore')"
      >
        {{ props.loading ? 'Loading…' : 'Load older notifications' }}
      </button>
    </section>
  </details>
</template>
