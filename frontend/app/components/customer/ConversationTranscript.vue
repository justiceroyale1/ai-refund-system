<script setup lang="ts">
import { nextTick, onMounted, ref, watch } from 'vue'
import ConversationMessageBubble from './ConversationMessageBubble.vue'
import type {
  ConversationMessage,
  OptimisticConversationMessage,
} from '~/types/conversation'

const props = defineProps<{
  messages: ConversationMessage[]
  optimisticMessage: OptimisticConversationMessage | null
  customerName: string
}>()

const transcript = ref<HTMLElement | null>(null)

async function scrollToLatest(): Promise<void> {
  await nextTick()

  if (transcript.value) {
    transcript.value.scrollTop = transcript.value.scrollHeight
  }
}

onMounted(scrollToLatest)
watch(
  () => [props.messages.length, props.optimisticMessage?.status],
  scrollToLatest,
)
</script>

<template>
  <div
    ref="transcript"
    class="min-h-0 flex-1 overflow-y-auto px-4 py-6 sm:px-6"
    aria-label="Conversation transcript"
    aria-live="polite"
  >
    <div class="mx-auto max-w-3xl space-y-5">
      <ConversationMessageBubble
        v-for="message in props.messages"
        :key="message.id"
        :sender="message.sender"
        :content="message.content"
        :created-at="message.created_at"
        :customer-name="props.customerName"
      />

      <ConversationMessageBubble
        v-if="props.optimisticMessage"
        sender="customer"
        :content="props.optimisticMessage.content"
        :created-at="props.optimisticMessage.createdAt"
        :customer-name="props.customerName"
        :delivery-status="props.optimisticMessage.status"
      />
    </div>
  </div>
</template>
