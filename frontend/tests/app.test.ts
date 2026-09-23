import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import CustomerNavigation from '~/components/customer/CustomerNavigation.vue'
import { Button } from '~/components/ui/button'

describe('application foundations', () => {
  it('renders the neutral product identity in customer navigation', async () => {
    const wrapper = await mountSuspended(CustomerNavigation, {
      props: {
        conversations: [],
        activeConversationId: null,
        loading: false,
        creating: false,
        error: null,
        hasMore: false,
      },
    })

    expect(wrapper.text()).toContain('AI Refund System')
  })

  it('renders the baseline UI primitive', async () => {
    const wrapper = await mountSuspended(Button, {
      slots: { default: 'Continue' },
    })

    expect(wrapper.get('button').text()).toBe('Continue')
  })
})
