<script setup lang="ts">
import {
  CheckCircle2,
  CircleAlert,
  Clock3,
  LoaderCircle,
  Search,
  ShieldAlert,
  XCircle,
} from '@lucide/vue'
import { computed, ref, watch } from 'vue'
import { Button } from '~/components/ui/button'
import {
  refundDecisionOptions,
  refundExecutionStatusOptions,
  refundRequestFiltersFromQuery,
  refundRequestQuery,
} from '~/lib/admin'
import { useAdminDashboardStore } from '~/stores/adminDashboard'
import type { RefundDecision, RefundExecutionStatus, RefundRequestFilters } from '~/types/admin'

definePageMeta({
  layout: 'admin',
  middleware: 'admin-auth',
})

useHead({
  title: 'Admin dashboard · AI Refund System',
})

const route = useRoute()
const dashboardStore = useAdminDashboardStore()
const filters = computed(() => refundRequestFiltersFromQuery(route.query))
const decision = ref<RefundDecision | ''>(filters.value.decision ?? '')
const executionStatus = ref<RefundExecutionStatus | ''>(filters.value.executionStatus ?? '')
const search = ref(filters.value.search)

const metricCards = computed(() => [
  {
    label: 'Approved requests',
    value: dashboardStore.metrics?.approved_request_count ?? null,
    icon: CheckCircle2,
    tone: 'green' as const,
  },
  {
    label: 'Denied requests',
    value: dashboardStore.metrics?.denied_request_count ?? null,
    icon: XCircle,
    tone: 'red' as const,
  },
  {
    label: 'Escalated requests',
    value: dashboardStore.metrics?.escalated_request_count ?? null,
    icon: ShieldAlert,
    tone: 'orange' as const,
  },
  {
    label: 'Pending refunds',
    value: dashboardStore.metrics?.pending_refund_count ?? null,
    icon: Clock3,
    tone: 'orange' as const,
  },
  {
    label: 'Failed refunds',
    value: dashboardStore.metrics?.failed_refund_count ?? null,
    icon: CircleAlert,
    tone: 'red' as const,
  },
])

await Promise.all([
  dashboardStore.loadMetrics(),
  dashboardStore.loadRefundRequests(filters.value),
])

watch(
  () => route.fullPath,
  async () => {
    decision.value = filters.value.decision ?? ''
    executionStatus.value = filters.value.executionStatus ?? ''
    search.value = filters.value.search
    await dashboardStore.loadRefundRequests(filters.value)
  },
)

async function navigateWithFilters(nextFilters: RefundRequestFilters): Promise<void> {
  await navigateTo({
    path: '/admin',
    query: refundRequestQuery(nextFilters),
  })
}

async function applyFilters(): Promise<void> {
  await navigateWithFilters({
    decision: decision.value || null,
    executionStatus: executionStatus.value || null,
    search: search.value.trim().slice(0, 255),
    page: 1,
  })
}

async function clearFilters(): Promise<void> {
  decision.value = ''
  executionStatus.value = ''
  search.value = ''
  await navigateWithFilters({
    decision: null,
    executionStatus: null,
    search: '',
    page: 1,
  })
}

async function goToPage(page: number): Promise<void> {
  await navigateWithFilters({ ...filters.value, page })
}
</script>

<template>
  <section>
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <p class="text-sm font-medium text-muted-foreground">
          Support overview
        </p>
        <h1 class="mt-1 text-3xl font-semibold tracking-tight">
          Refund dashboard
        </h1>
      </div>
      <p class="text-sm text-muted-foreground">
        {{ dashboardStore.total }} {{ dashboardStore.total === 1 ? 'request' : 'requests' }} found
      </p>
    </div>

    <div
      v-if="dashboardStore.metricsError"
      class="mt-6 flex flex-col gap-3 rounded-xl border border-destructive/20 bg-destructive/5 p-4 sm:flex-row sm:items-center sm:justify-between"
    >
      <p role="alert" class="text-sm text-destructive">
        {{ dashboardStore.metricsError }}
      </p>
      <Button size="sm" variant="outline" @click="dashboardStore.loadMetrics()">
        Retry metrics
      </Button>
    </div>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
      <AdminMetricCard
        v-for="metric in metricCards"
        :key="metric.label"
        v-bind="metric"
      />
    </div>

    <div class="mt-8">
      <div>
        <h2 class="text-xl font-semibold tracking-tight">
          Recent refund requests
        </h2>
        <p class="mt-1 text-sm text-muted-foreground">
          Search customer or order details, then narrow by decision or execution status.
        </p>
      </div>

      <form
        class="mt-5 grid gap-3 rounded-xl border bg-card p-4 shadow-xs lg:grid-cols-[minmax(16rem,1fr)_12rem_12rem_auto]"
        aria-label="Refund request filters"
        @submit.prevent="applyFilters"
      >
        <div class="relative">
          <label for="refund-search" class="sr-only">Search refund requests</label>
          <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
          <input
            id="refund-search"
            v-model="search"
            type="search"
            maxlength="255"
            placeholder="Customer, email, or order"
            class="h-10 w-full rounded-md border bg-background pl-9 pr-3 text-sm shadow-xs outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
          >
        </div>

        <div>
          <label for="decision-filter" class="sr-only">Decision</label>
          <select
            id="decision-filter"
            v-model="decision"
            class="h-10 w-full rounded-md border bg-background px-3 text-sm shadow-xs outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
          >
            <option value="">All decisions</option>
            <option v-for="option in refundDecisionOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>

        <div>
          <label for="execution-filter" class="sr-only">Execution status</label>
          <select
            id="execution-filter"
            v-model="executionStatus"
            class="h-10 w-full rounded-md border bg-background px-3 text-sm shadow-xs outline-none focus:border-ring focus:ring-3 focus:ring-ring/20"
          >
            <option value="">All execution statuses</option>
            <option v-for="option in refundExecutionStatusOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>

        <div class="flex gap-2">
          <Button type="submit" class="flex-1 lg:flex-none" :disabled="dashboardStore.isLoadingRequests">
            <LoaderCircle v-if="dashboardStore.isLoadingRequests" class="size-4 animate-spin" aria-hidden="true" />
            <Search v-else class="size-4" aria-hidden="true" />
            Apply
          </Button>
          <Button type="button" variant="outline" @click="clearFilters">
            Clear
          </Button>
        </div>
      </form>

      <div class="mt-4">
        <AdminRefundRequestList
          :requests="dashboardStore.refundRequests"
          :loading="dashboardStore.isLoadingRequests"
          :error="dashboardStore.requestsError"
          @retry="dashboardStore.loadRefundRequests(filters)"
        />
      </div>

      <nav
        v-if="!dashboardStore.isLoadingRequests && !dashboardStore.requestsError && dashboardStore.total > 0"
        class="mt-4 flex items-center justify-between gap-4"
        aria-label="Refund request pagination"
      >
        <p class="text-sm text-muted-foreground">
          Page {{ dashboardStore.currentPage }} of {{ dashboardStore.lastPage }}
        </p>
        <div class="flex gap-2">
          <Button
            variant="outline"
            size="sm"
            :disabled="dashboardStore.currentPage <= 1"
            @click="goToPage(dashboardStore.currentPage - 1)"
          >
            Previous
          </Button>
          <Button
            variant="outline"
            size="sm"
            :disabled="dashboardStore.currentPage >= dashboardStore.lastPage"
            @click="goToPage(dashboardStore.currentPage + 1)"
          >
            Next
          </Button>
        </div>
      </nav>
    </div>
  </section>
</template>
