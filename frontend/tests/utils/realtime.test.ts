import { describe, expect, it, vi } from 'vitest'
import {
  channelAuthorizationUrl,
  csrfCookieUrl,
  createChannelAuthorizationHandler,
  createNotificationRealtimeConnection,
  type RealtimeConnectionStatus,
  type RealtimeEchoClient,
} from '~/lib/realtime'

describe('notification realtime connection', () => {
  it('authorizes the exact private channel with credentials and scoped headers', async () => {
    const fetchMock = vi.fn()
      .mockResolvedValueOnce({ ok: true })
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({ auth: 'signed-channel' }),
      })
    const readXsrfToken = vi.fn()
      .mockReturnValueOnce(null)
      .mockReturnValueOnce('csrf token')
    const callback = vi.fn()
    const authorize = createChannelAuthorizationHandler(
      'http://localhost:8000/',
      { 'X-Demo-Customer-Id': '42' },
      fetchMock,
      readXsrfToken,
    )

    await authorize({
      socketId: '1234.5678',
      channelName: 'private-customers.42',
    }, callback)

    expect(channelAuthorizationUrl('http://localhost:8000/')).toBe(
      'http://localhost:8000/api/broadcasting/auth',
    )
    expect(csrfCookieUrl('http://localhost:8000/')).toBe(
      'http://localhost:8000/sanctum/csrf-cookie',
    )
    expect(fetchMock).toHaveBeenCalledWith(
      'http://localhost:8000/sanctum/csrf-cookie',
      {
        credentials: 'include',
        headers: { Accept: 'application/json' },
      },
    )
    expect(fetchMock).toHaveBeenCalledWith(
      'http://localhost:8000/api/broadcasting/auth',
      expect.objectContaining({
        method: 'POST',
        credentials: 'include',
        headers: expect.objectContaining({
          'X-Demo-Customer-Id': '42',
          'X-XSRF-TOKEN': 'csrf token',
        }),
        body: JSON.stringify({
          socket_id: '1234.5678',
          channel_name: 'private-customers.42',
        }),
      }),
    )
    expect(callback).toHaveBeenCalledWith(null, { auth: 'signed-channel' })
  })

  it('subscribes to the requested channel, reconciles after reconnect, and cleans up', () => {
    let connectionListener: ((status: RealtimeConnectionStatus) => void) | null = null
    const notificationListener = vi.fn()
    const stopConnectionListener = vi.fn()
    const leave = vi.fn()
    const disconnect = vi.fn()
    const privateChannel = vi.fn().mockReturnValue({
      notification: notificationListener,
    })
    const createEcho = vi.fn().mockReturnValue({
      private: privateChannel,
      connector: {
        onConnectionChange: vi.fn((listener) => {
          connectionListener = listener
          return stopConnectionListener
        }),
      },
      leave,
      disconnect,
    } satisfies RealtimeEchoClient)
    const onReconnect = vi.fn()
    const onNotification = vi.fn()
    const connection = createNotificationRealtimeConnection({
      apiBase: 'http://localhost:8000',
      appKey: 'local-key',
      host: 'localhost',
      port: 8080,
      scheme: 'http',
      channelName: 'admins.7',
      onNotification,
      onReconnect,
    }, { createEcho })

    expect(privateChannel).toHaveBeenCalledWith('admins.7')
    expect(notificationListener).toHaveBeenCalledWith(onNotification)

    connectionListener?.('connected')
    connectionListener?.('disconnected')
    connectionListener?.('connected')

    expect(onReconnect).toHaveBeenCalledOnce()

    connection.disconnect()

    expect(stopConnectionListener).toHaveBeenCalledOnce()
    expect(leave).toHaveBeenCalledWith('admins.7')
    expect(disconnect).toHaveBeenCalledOnce()
  })
})
