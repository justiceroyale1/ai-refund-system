<script setup lang="ts">
import type { Component } from 'vue'

defineProps<{
  label: string
  value: number | null
  icon: Component
  tone: 'blue' | 'green' | 'orange' | 'red'
}>()
</script>

<template>
  <article class="rounded-xl border bg-card p-5 shadow-xs">
    <div class="flex items-center justify-between gap-4">
      <div>
        <p class="text-sm font-medium text-muted-foreground">
          {{ label }}
        </p>
        <p class="mt-2 text-3xl font-semibold tracking-tight" :aria-label="`${label}: ${value ?? 'Loading'}`">
          {{ value ?? '—' }}
        </p>
      </div>
      <span
        class="grid size-11 place-items-center rounded-xl"
        :class="{
          'bg-blue-50 text-blue-700': tone === 'blue',
          'bg-emerald-50 text-emerald-700': tone === 'green',
          'bg-amber-50 text-amber-700': tone === 'orange',
          'bg-red-50 text-red-700': tone === 'red',
        }"
      >
        <component :is="icon" class="size-5" aria-hidden="true" />
      </span>
    </div>
  </article>
</template>
