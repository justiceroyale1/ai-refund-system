import type { PaginationLinks, PaginationMeta } from '~/types/api'

export interface NotificationBase {
  id: string
  type: string
  title: string
  message: string
  read_at: string | null
  created_at: string
}

export interface CustomerNotification extends NotificationBase {
  conversation_id: number
}

export interface AdminNotification extends NotificationBase {
  refund_request_id: number
  error_summary: string | null
}

export interface NotificationPage<T extends NotificationBase> {
  data: T[]
  links: PaginationLinks
  meta: PaginationMeta & {
    unread_count: number
  }
}

export interface NotificationResponse<T extends NotificationBase> {
  data: T
  meta: {
    unread_count: number
  }
}

export interface NotificationUnreadResponse {
  data: {
    unread_count: number
  }
}

export interface CustomerNotificationBroadcast {
  id: string
  type: string
  title: string
  message: string
  conversation_id: number
}

export interface AdminNotificationBroadcast {
  id: string
  type: string
  title: string
  message: string
  refund_request_id: number
  error_summary: string | null
}
