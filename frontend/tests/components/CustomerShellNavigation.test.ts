import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import CustomerShellNavigation from '~/components/customer/CustomerShellNavigation.vue'

const baseProps = {
  conversations: [],
  activeConversationId: null,
  loading: false,
  creating: false,
  error: null,
  hasMore: false,
  routePath: '/support',
} as const

describe('responsive customer navigation', () => {
  it('renders the desktop sidebar only at the large breakpoint', async () => {
    const wrapper = await mountSuspended(CustomerShellNavigation, {
      props: {
        ...baseProps,
        mode: 'desktop',
      },
    })

    expect(wrapper.get('[data-testid="desktop-conversation-navigation"]').classes()).toContain('hidden')
    expect(wrapper.get('[data-testid="desktop-conversation-navigation"]').classes()).toContain('lg:flex')
    expect(wrapper.find('[aria-label="Open conversation history"]').exists()).toBe(false)
  })

  it('opens and closes an accessible mobile drawer', async () => {
    const wrapper = await mountSuspended(CustomerShellNavigation, {
      attachTo: document.body,
      props: {
        ...baseProps,
        mode: 'mobile',
      },
    })

    await wrapper.get('[aria-label="Open conversation history"]').trigger('click')

    expect(document.body.querySelector('[role="dialog"]')).not.toBeNull()

    const closeButton = document.body.querySelector<HTMLElement>(
      'section[role="dialog"] [aria-label="Close conversation history"]',
    )
    closeButton?.click()
    await wrapper.vm.$nextTick()

    expect(document.body.querySelector('[role="dialog"]')).toBeNull()
    wrapper.unmount()
  })

  it('closes the mobile drawer after navigation', async () => {
    const wrapper = await mountSuspended(CustomerShellNavigation, {
      attachTo: document.body,
      props: {
        ...baseProps,
        mode: 'mobile',
      },
    })

    await wrapper.get('[aria-label="Open conversation history"]').trigger('click')
    await wrapper.setProps({ routePath: '/support/conversations/4' })

    expect(document.body.querySelector('[role="dialog"]')).toBeNull()
    wrapper.unmount()
  })
})
