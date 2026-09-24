<script setup lang="ts">
import ConversationMessageBubble from '~/components/customer/ConversationMessageBubble.vue'
import type { AdminConversationMessage } from '~/types/admin'

defineProps<{
  messages: AdminConversationMessage[]
  customerName: string
}>()
</script>

<template>
  <div
    v-if="messages.length > 0"
    class="space-y-5 rounded-xl border bg-muted/20 p-4 sm:p-6"
    aria-label="Conversation transcript"
  >
    <ConversationMessageBubble
      v-for="message in messages"
      :key="message.id"
      :sender="message.sender"
      :content="message.content"
      :created-at="message.created_at"
      :customer-name="customerName"
    />
  </div>

  <div
    v-else
    class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground"
    data-testid="case-transcript-empty"
  >
    No conversation messages are available for this case.
  </div>
</template>
