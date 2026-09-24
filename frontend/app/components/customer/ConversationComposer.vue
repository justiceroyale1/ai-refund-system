<script setup lang="ts">
import { LoaderCircle, Send } from '@lucide/vue'
import { computed } from 'vue'
import { Button } from '~/components/ui/button'

const props = defineProps<{
  modelValue: string
  disabled?: boolean
  submitting?: boolean
  resolved?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
  'submit': [content: string]
}>()

const remainingCharacters = computed(() => 5000 - props.modelValue.length)
const cannotSubmit = computed(() => props.disabled
  || props.submitting
  || props.resolved
  || props.modelValue.trim().length === 0)

function submit(): void {
  if (!cannotSubmit.value) {
    emit('submit', props.modelValue)
  }
}

function handleKeydown(event: KeyboardEvent): void {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault()
    submit()
  }
}

function updateValue(event: Event): void {
  emit('update:modelValue', (event.target as HTMLTextAreaElement).value)
}
</script>

<template>
  <form class="space-y-2" aria-label="Send a message" @submit.prevent="submit">
    <label for="conversation-message" class="sr-only">Message</label>
    <div class="flex items-end gap-2 rounded-xl border bg-background p-2 shadow-xs focus-within:ring-2 focus-within:ring-ring/40">
      <textarea
        id="conversation-message"
        :value="props.modelValue"
        rows="2"
        maxlength="5000"
        :disabled="props.disabled || props.submitting || props.resolved"
        :placeholder="props.resolved ? 'This conversation is resolved' : 'Describe how we can help…'"
        class="max-h-40 min-h-12 flex-1 resize-none bg-transparent px-2 py-2 text-sm outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-60"
        @input="updateValue"
        @keydown="handleKeydown"
      />
      <Button
        type="submit"
        size="icon"
        :disabled="cannotSubmit"
        :aria-label="props.submitting ? 'Sending message' : 'Send message'"
      >
        <LoaderCircle v-if="props.submitting" class="size-4 animate-spin" aria-hidden="true" />
        <Send v-else class="size-4" aria-hidden="true" />
      </Button>
    </div>
    <p v-if="remainingCharacters <= 500" class="text-right text-xs text-muted-foreground">
      {{ remainingCharacters }} characters remaining
    </p>
  </form>
</template>
