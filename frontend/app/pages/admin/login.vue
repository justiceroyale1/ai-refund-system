<script setup lang="ts">
import { LoaderCircle, LockKeyhole, ShieldCheck } from '@lucide/vue'
import { ref } from 'vue'
import { Button } from '~/components/ui/button'
import { useAdminSessionStore } from '~/stores/adminSession'

definePageMeta({
  layout: false,
  middleware: 'admin-guest',
})

useHead({
  title: 'Admin sign in · AI Refund System',
})

const route = useRoute()
const sessionStore = useAdminSessionStore()
const email = ref('')
const password = ref('')

async function submit(): Promise<void> {
  if (!await sessionStore.login(email.value, password.value)) {
    return
  }

  const redirect = typeof route.query.redirect === 'string'
    && route.query.redirect.startsWith('/admin')
    && !route.query.redirect.startsWith('/admin/login')
    ? route.query.redirect
    : '/admin'

  await navigateTo(redirect)
}
</script>

<template>
  <main class="grid min-h-dvh place-items-center bg-muted/30 p-4 sm:p-6">
    <section class="w-full max-w-md rounded-2xl border bg-card p-6 shadow-sm sm:p-8">
      <span class="grid size-12 place-items-center rounded-xl bg-primary text-primary-foreground">
        <ShieldCheck class="size-6" aria-hidden="true" />
      </span>
      <p class="mt-5 text-sm font-medium text-muted-foreground">
        AI Refund System
      </p>
      <h1 class="mt-1 text-2xl font-semibold tracking-tight">
        Sign in to support admin
      </h1>
      <p class="mt-2 text-sm leading-6 text-muted-foreground">
        Use your administrator account to review refund activity.
      </p>

      <form class="mt-7 space-y-5" @submit.prevent="submit">
        <div class="space-y-2">
          <label for="email" class="text-sm font-medium">Email address</label>
          <input
            id="email"
            v-model="email"
            name="email"
            type="email"
            autocomplete="email"
            required
            class="h-10 w-full rounded-md border bg-background px-3 text-sm shadow-xs outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
          >
        </div>
        <div class="space-y-2">
          <label for="password" class="text-sm font-medium">Password</label>
          <input
            id="password"
            v-model="password"
            name="password"
            type="password"
            autocomplete="current-password"
            required
            class="h-10 w-full rounded-md border bg-background px-3 text-sm shadow-xs outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
          >
        </div>

        <div
          v-if="sessionStore.loginError"
          role="alert"
          class="rounded-lg border border-destructive/20 bg-destructive/5 p-3 text-sm text-destructive"
        >
          {{ sessionStore.loginError }}
        </div>

        <Button class="w-full" type="submit" :disabled="sessionStore.isLoggingIn">
          <LoaderCircle v-if="sessionStore.isLoggingIn" class="size-4 animate-spin" aria-hidden="true" />
          <LockKeyhole v-else class="size-4" aria-hidden="true" />
          {{ sessionStore.isLoggingIn ? 'Signing in…' : 'Sign in' }}
        </Button>
      </form>
    </section>
  </main>
</template>
