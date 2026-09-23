<script setup lang="ts">
import { Inbox, LoaderCircle } from '@lucide/vue'
import ConversationHistoryItem from './ConversationHistoryItem.vue'
import type { RefundConversationSummary } from '~/types/conversation'
import { Button } from '~/components/ui/button'

const props = defineProps<{
  conversations: RefundConversationSummary[]
  activeConversationId: string | null
  loading: boolean
  error: string | null
  hasMore: boolean
}>()

defineEmits<{
  retry: []
  loadMore: []
}>()
</script>

<template>
  <div class="min-h-0 flex-1 overflow-y-auto px-3 pb-4">
    <div v-if="props.loading && props.conversations.length === 0" class="grid place-items-center py-12">
      <LoaderCircle class="size-5 animate-spin text-muted-foreground" aria-hidden="true" />
      <span class="sr-only">Loading conversations</span>
    </div>

    <div v-else-if="props.error && props.conversations.length === 0" class="px-2 py-8 text-center">
      <p class="text-sm text-muted-foreground">
        {{ props.error }}
      </p>
      <Button class="mt-4" size="sm" variant="outline" @click="$emit('retry')">
        Try again
      </Button>
    </div>

    <div v-else-if="props.conversations.length === 0" class="px-4 py-10 text-center">
      <span class="mx-auto grid size-10 place-items-center rounded-full bg-muted">
        <Inbox class="size-5 text-muted-foreground" aria-hidden="true" />
      </span>
      <p class="mt-3 text-sm font-medium">
        No conversations yet
      </p>
      <p class="mt-1 text-xs leading-5 text-muted-foreground">
        Start a request when you need help with an order.
      </p>
    </div>

    <nav v-else aria-label="Conversation history" class="space-y-1">
      <ConversationHistoryItem
        v-for="conversation in props.conversations"
        :key="conversation.id"
        :conversation="conversation"
        :active="String(conversation.id) === props.activeConversationId"
      />

      <Button
        v-if="props.hasMore"
        class="mt-3 w-full"
        size="sm"
        variant="ghost"
        :disabled="props.loading"
        @click="$emit('loadMore')"
      >
        <LoaderCircle v-if="props.loading" class="size-4 animate-spin" aria-hidden="true" />
        {{ props.loading ? 'Loading' : 'Load older conversations' }}
      </Button>
    </nav>
  </div>
</template>
