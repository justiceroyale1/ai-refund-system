<script setup lang="ts">
import type { ConversationAction } from '~/types/conversation'
import { Button } from '~/components/ui/button'

const props = defineProps<{
  actions: ConversationAction[]
  disabled?: boolean
}>()

defineEmits<{
  select: [action: ConversationAction]
}>()
</script>

<template>
  <div
    v-if="props.actions.length > 0"
    class="flex flex-wrap gap-2"
    role="group"
    aria-label="Suggested replies"
  >
    <Button
      v-for="action in props.actions"
      :key="`${action.type}-${action.value}`"
      type="button"
      size="sm"
      variant="outline"
      :disabled="props.disabled"
      @click="$emit('select', action)"
    >
      {{ action.label }}
    </Button>
  </div>
</template>
