import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import NotificationCenter from '~/components/NotificationCenter.vue'

describe('notification center', () => {
  it('announces unread state and emits the selected durable notification destination key', async () => {
    const wrapper = mount(NotificationCenter, {
      props: {
        notifications: [{
          id: 'notification-1',
          type: 'refund_processed',
          title: 'Refund processed',
          message: 'Your refund has been processed successfully.',
          conversation_id: 42,
          read_at: null,
          created_at: '2026-09-25T09:00:00.000Z',
        }],
        unreadCount: 1,
        loading: false,
        error: null,
        hasMore: false,
        label: 'Customer notifications',
      },
    })

    expect(wrapper.get('summary').attributes('aria-label')).toBe('Customer notifications: 1 unread')
    expect(wrapper.text()).toContain('Refund processed')

    const notificationButton = wrapper.findAll('section button')
      .find(button => button.text().includes('Refund processed'))
    await notificationButton?.trigger('click')

    expect(wrapper.emitted('select')).toEqual([['notification-1']])
  })
})
