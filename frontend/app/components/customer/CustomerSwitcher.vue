<script setup lang="ts">
import { UserRound } from '@lucide/vue'
import type { DemoCustomer } from '~/types/customer'

const props = defineProps<{
  customers: DemoCustomer[]
  selectedCustomerId: number | null
  disabled?: boolean
}>()

const emit = defineEmits<{
  change: [customerId: number]
}>()

function handleChange(event: Event): void {
  const customerId = Number((event.target as HTMLSelectElement).value)

  if (Number.isInteger(customerId) && customerId > 0) {
    emit('change', customerId)
  }
}
</script>

<template>
  <label class="flex min-w-0 items-center gap-2">
    <span class="sr-only">Demo customer</span>
    <UserRound class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
    <select
      :value="props.selectedCustomerId ?? ''"
      :disabled="props.disabled"
      class="min-w-0 max-w-64 rounded-md border bg-background px-3 py-2 text-sm font-medium shadow-xs focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-60"
      aria-label="Demo customer"
      @change="handleChange"
    >
      <option v-if="props.customers.length === 0" value="" disabled>
        No customers available
      </option>
      <option
        v-for="customer in props.customers"
        :key="customer.id"
        :value="customer.id"
      >
        {{ customer.name }}
      </option>
    </select>
  </label>
</template>
