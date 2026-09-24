<script setup lang="ts">
import { computed } from 'vue'
import { formatExactTimestamp, formatMessageTimestamp } from '~/lib/conversations'
import type { MessageSender } from '~/types/conversation'

const props = defineProps<{
  sender: MessageSender
  content: string
  createdAt: string | null
  customerName: string
  deliveryStatus?: 'sending' | 'failed'
}>()

const senderLabel = computed(() => ({
  assistant: 'Refund Assistant',
  customer: props.customerName,
  system: 'Status update',
})[props.sender])

const bubbleClasses = computed(() => ({
  assistant: 'rounded-bl-sm bg-muted text-foreground',
  customer: 'rounded-br-sm bg-primary text-primary-foreground',
  system: 'border bg-background text-foreground shadow-xs',
})[props.sender])
</script>

<template>
  <article
    class="flex"
    :class="props.sender === 'customer'
      ? 'justify-end'
      : props.sender === 'system' ? 'justify-center' : 'justify-start'"
    :aria-label="`${senderLabel} message`"
  >
    <div
      class="max-w-[88%] sm:max-w-[75%]"
      :class="props.sender === 'system' ? 'text-center' : ''"
    >
      <div
        class="mb-1 flex items-center gap-2 px-1 text-xs text-muted-foreground"
        :class="props.sender === 'customer' ? 'justify-end' : props.sender === 'system' ? 'justify-center' : ''"
      >
        <span class="font-medium text-foreground/80">{{ senderLabel }}</span>
        <time
          v-if="props.createdAt"
          :datetime="props.createdAt"
          :title="formatExactTimestamp(props.createdAt)"
        >
          {{ formatMessageTimestamp(props.createdAt) }}
        </time>
        <span v-else>Time unavailable</span>
      </div>

      <div
        class="whitespace-pre-wrap break-words rounded-2xl px-4 py-3 text-sm leading-6"
        :class="bubbleClasses"
      >
        {{ props.content }}
      </div>

      <p
        v-if="props.deliveryStatus"
        class="mt-1 px-1 text-xs"
        :class="props.deliveryStatus === 'failed' ? 'text-destructive' : 'text-muted-foreground'"
        role="status"
      >
        {{ props.deliveryStatus === 'failed' ? 'Not sent' : 'Sending…' }}
      </p>
    </div>
  </article>
</template>
