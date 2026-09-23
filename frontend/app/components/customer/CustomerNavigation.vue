<script setup lang="ts">
import { LifeBuoy, LoaderCircle, Plus } from '@lucide/vue'
import ConversationHistory from './ConversationHistory.vue'
import { Button } from '~/components/ui/button'
import type { RefundConversationSummary } from '~/types/conversation'

const props = defineProps<{
  conversations: RefundConversationSummary[]
  activeConversationId: string | null
  loading: boolean
  creating: boolean
  error: string | null
  hasMore: boolean
}>()

defineEmits<{
  newConversation: []
  retry: []
  loadMore: []
}>()
</script>

<template>
  <div class="flex min-h-0 flex-1 flex-col">
    <div class="space-y-4 p-4">
      <NuxtLink to="/support" class="flex items-center gap-2 font-semibold tracking-tight">
        <span class="grid size-8 place-items-center rounded-lg bg-primary text-primary-foreground">
          <LifeBuoy class="size-4" aria-hidden="true" />
        </span>
        AI Refund System
      </NuxtLink>

      <Button
        class="w-full"
        :disabled="props.creating"
        @click="$emit('newConversation')"
      >
        <LoaderCircle v-if="props.creating" class="size-4 animate-spin" aria-hidden="true" />
        <Plus v-else class="size-4" aria-hidden="true" />
        {{ props.creating ? 'Starting request' : 'New refund request' }}
      </Button>
    </div>

    <div class="flex items-center justify-between px-5 pb-2">
      <h2 class="text-xs font-semibold uppercase tracking-[0.16em] text-muted-foreground">
        Conversations
      </h2>
      <span class="text-xs tabular-nums text-muted-foreground">
        {{ props.conversations.length }}
      </span>
    </div>

    <ConversationHistory
      :conversations="props.conversations"
      :active-conversation-id="props.activeConversationId"
      :loading="props.loading"
      :error="props.error"
      :has-more="props.hasMore"
      @retry="$emit('retry')"
      @load-more="$emit('loadMore')"
    />
  </div>
</template>
