<script setup lang="ts">
import { Menu, X } from '@lucide/vue'
import { ref, watch } from 'vue'
import CustomerNavigation from './CustomerNavigation.vue'
import { Button } from '~/components/ui/button'
import type { RefundConversationSummary } from '~/types/conversation'

const props = defineProps<{
  mode: 'desktop' | 'mobile'
  conversations: RefundConversationSummary[]
  activeConversationId: string | null
  loading: boolean
  creating: boolean
  error: string | null
  hasMore: boolean
  routePath: string
}>()

const emit = defineEmits<{
  newConversation: []
  retry: []
  loadMore: []
}>()

const mobileNavigationOpen = ref(false)

watch(() => props.routePath, () => {
  mobileNavigationOpen.value = false
})
</script>

<template>
  <Button
    v-if="props.mode === 'mobile'"
    class="lg:hidden"
    size="icon"
    variant="outline"
    aria-label="Open conversation history"
    @click="mobileNavigationOpen = true"
  >
    <Menu class="size-4" aria-hidden="true" />
  </Button>

  <aside
    v-if="props.mode === 'desktop'"
    data-testid="desktop-conversation-navigation"
    class="hidden min-h-0 w-80 shrink-0 border-r bg-card lg:flex"
  >
    <CustomerNavigation
      :conversations="props.conversations"
      :active-conversation-id="props.activeConversationId"
      :loading="props.loading"
      :creating="props.creating"
      :error="props.error"
      :has-more="props.hasMore"
      @new-conversation="emit('newConversation')"
      @retry="emit('retry')"
      @load-more="emit('loadMore')"
    />
  </aside>

  <Teleport v-if="props.mode === 'mobile'" to="body">
    <div v-if="mobileNavigationOpen" class="fixed inset-0 z-50 lg:hidden">
      <button
        class="absolute inset-0 bg-black/45"
        aria-label="Close conversation history"
        @click="mobileNavigationOpen = false"
      />
      <section
        role="dialog"
        aria-modal="true"
        aria-label="Conversation history"
        class="relative flex h-full w-[min(88vw,22rem)] flex-col bg-card shadow-xl"
      >
        <Button
          class="absolute right-3 top-3 z-10"
          size="icon"
          variant="ghost"
          aria-label="Close conversation history"
          @click="mobileNavigationOpen = false"
        >
          <X class="size-4" aria-hidden="true" />
        </Button>
        <CustomerNavigation
          :conversations="props.conversations"
          :active-conversation-id="props.activeConversationId"
          :loading="props.loading"
          :creating="props.creating"
          :error="props.error"
          :has-more="props.hasMore"
          @new-conversation="emit('newConversation')"
          @retry="emit('retry')"
          @load-more="emit('loadMore')"
        />
      </section>
    </div>
  </Teleport>
</template>
