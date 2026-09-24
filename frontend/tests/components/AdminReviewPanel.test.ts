import { mountSuspended } from '@nuxt/test-utils/runtime'
import { describe, expect, it } from 'vitest'
import AdminReviewPanel from '~/components/admin/AdminReviewPanel.vue'

function mountPanel() {
  return mountSuspended(AdminReviewPanel, {
    props: {
      submitting: false,
      error: null,
      validationErrors: {},
    },
  })
}

describe('admin review panel', () => {
  it('requires approval confirmation and submits the optional note', async () => {
    const wrapper = await mountPanel()

    await wrapper.get('button').trigger('click')
    expect(wrapper.text()).toContain('Confirm approval')

    await wrapper.get('textarea').setValue('Approved after checking the complete case.')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('submit')).toEqual([[
      'approved',
      'Approved after checking the complete case.',
    ]])
  })

  it('requires denial confirmation and allows a blank note', async () => {
    const wrapper = await mountPanel()
    const denyButton = wrapper.findAll('button').find(button => button.text().includes('Deny request'))

    await denyButton?.trigger('click')
    expect(wrapper.text()).toContain('Confirm denial')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('submit')).toEqual([['denied', '']])
  })

  it('renders backend validation and disables confirmation during submission', async () => {
    const wrapper = await mountPanel()
    await wrapper.get('button').trigger('click')
    await wrapper.setProps({
      submitting: true,
      error: 'Please correct the review details and try again.',
      validationErrors: { review_note: 'The review note is too long.' },
    })

    expect(wrapper.text()).toContain('The review note is too long.')
    expect(wrapper.text()).toContain('Please correct the review details and try again.')
    expect(wrapper.get('textarea').attributes('disabled')).toBeDefined()
    expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined()
  })
})
