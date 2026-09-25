import type {
  AdminNotificationBroadcast,
  CustomerNotificationBroadcast,
  NotificationBase,
} from '~/types/notification'

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function hasNotificationFields(value: Record<string, unknown>): boolean {
  return typeof value.id === 'string'
    && typeof value.type === 'string'
    && typeof value.title === 'string'
    && typeof value.message === 'string'
}

export function isCustomerNotificationBroadcast(
  value: unknown,
): value is CustomerNotificationBroadcast {
  return isRecord(value)
    && hasNotificationFields(value)
    && Number.isInteger(value.conversation_id)
    && Number(value.conversation_id) > 0
}

export function isAdminNotificationBroadcast(
  value: unknown,
): value is AdminNotificationBroadcast {
  return isRecord(value)
    && hasNotificationFields(value)
    && Number.isInteger(value.refund_request_id)
    && Number(value.refund_request_id) > 0
    && (typeof value.error_summary === 'string' || value.error_summary === null)
}

export function mergeNotificationPage<T extends NotificationBase>(
  current: T[],
  incoming: T[],
  replace = false,
): T[] {
  const merged = replace ? incoming : [...current, ...incoming]
  const unique = new Map<string, T>()

  for (const notification of merged) {
    if (!unique.has(notification.id)) {
      unique.set(notification.id, notification)
    }
  }

  return [...unique.values()].sort((left, right) => {
    const createdAtComparison = right.created_at.localeCompare(left.created_at)

    return createdAtComparison === 0
      ? right.id.localeCompare(left.id)
      : createdAtComparison
  })
}

export function mergeBroadcastNotification<T extends NotificationBase>(
  notifications: T[],
  notification: T,
): { notifications: T[], inserted: boolean } {
  if (notifications.some(existing => existing.id === notification.id)) {
    return { notifications, inserted: false }
  }

  return {
    notifications: [notification, ...notifications],
    inserted: true,
  }
}
