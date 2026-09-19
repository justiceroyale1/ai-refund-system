import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import { Button } from '~/components/ui/button'
import IndexPage from '~/pages/index.vue'

describe('starter application', () => {
  it('renders the neutral product identity', async () => {
    const wrapper = await mountSuspended(IndexPage)

    expect(wrapper.get('h1').text()).toBe('AI Refund System')
  })

  it('renders the baseline UI primitive', async () => {
    const wrapper = await mountSuspended(Button, {
      slots: { default: 'Continue' },
    })

    expect(wrapper.get('button').text()).toBe('Continue')
  })
})
