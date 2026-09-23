<script setup lang="ts">
import { computed } from 'vue'
import {
  formatConversationDate,
  getConversationItemLabel,
  getConversationStatusLabel,
  getConversationStatusTone,
  getConversationTitle,
} from '~/lib/conversations'
import type { RefundConversationSummary } from '~/types/conversation'

const props = defineProps<{
  conversation: RefundConversationSummary
  active: boolean
}>()

const statusToneClasses = {
  blue: 'bg-blue-50 text-blue-700 ring-blue-600/20',
  green: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
  neutral: 'bg-slate-100 text-slate-600 ring-slate-500/20',
  orange: 'bg-amber-50 text-amber-700 ring-amber-600/20',
  red: 'bg-red-50 text-red-700 ring-red-600/20',
}

const statusClasses = computed(() => statusToneClasses[getConversationStatusTone(props.conversation)])
</script>

<template>
  <NuxtLink
    :to="`/support/conversations/${props.conversation.id}`"
    :aria-current="props.active ? 'page' : undefined"
    class="block rounded-lg border p-3 transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring"
    :class="props.active ? 'border-foreground/20 bg-accent shadow-xs' : 'border-transparent'"
  >
    <div class="flex items-start justify-between gap-3">
      <p class="truncate text-sm font-semibold">
        {{ getConversationTitle(props.conversation) }}
      </p>
      <time
        :datetime="props.conversation.updated_at ?? undefined"
        class="shrink-0 text-xs text-muted-foreground"
      >
        {{ formatConversationDate(props.conversation.updated_at) }}
      </time>
    </div>
    <p class="mt-1 truncate text-sm text-muted-foreground">
      {{ getConversationItemLabel(props.conversation) }}
    </p>
    <span
      class="mt-2 inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
      :class="statusClasses"
    >
      {{ getConversationStatusLabel(props.conversation) }}
    </span>
  </NuxtLink>
</template>
