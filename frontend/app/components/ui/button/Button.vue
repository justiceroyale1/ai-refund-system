<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import type { PrimitiveProps } from 'reka-ui'
import type { ButtonVariants } from '.'
import { Primitive } from 'reka-ui'
import { buttonVariants } from '.'
import { cn } from '~/lib/utils'

interface Props extends PrimitiveProps {
  class?: HTMLAttributes['class']
  size?: ButtonVariants['size']
  variant?: ButtonVariants['variant']
}

const props = withDefaults(defineProps<Props>(), {
  as: 'button',
  class: undefined,
  size: 'default',
  variant: 'default',
})
</script>

<template>
  <Primitive
    data-slot="button"
    :as="props.as"
    :as-child="props.asChild"
    :class="cn(buttonVariants({ size: props.size, variant: props.variant }), props.class)"
  >
    <slot />
  </Primitive>
</template>
