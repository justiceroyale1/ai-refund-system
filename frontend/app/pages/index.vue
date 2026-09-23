<script setup lang="ts">
await navigateTo('/support', { replace: true })

useHead({
  title: 'AI Refund System',
})
</script>

<template>
  <main class="grid min-h-screen place-items-center bg-background p-6 text-foreground">
    <p class="text-sm text-muted-foreground">
      Opening customer support…
    </p>
  </main>
</template>
