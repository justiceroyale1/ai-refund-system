import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

interface ChannelAuthorizationParams {
  socketId: string
  channelName: string
}

interface ChannelAuthorizationData {
  auth: string
  channel_data?: string
  shared_secret?: string
}

type ChannelAuthorizationCallback = (
  error: Error | null,
  data: ChannelAuthorizationData | null,
) => void

type XsrfTokenReader = () => string | null

export type RealtimeConnectionStatus = 'connected'
  | 'connecting'
  | 'disconnected'
  | 'failed'
  | 'reconnecting'

interface RealtimePrivateChannel {
  notification(callback: (payload: unknown) => void): RealtimePrivateChannel
}

export interface RealtimeEchoClient {
  private(channelName: string): RealtimePrivateChannel
  connector: {
    onConnectionChange(callback: (status: RealtimeConnectionStatus) => void): () => void
  }
  leave(channelName: string): void
  disconnect(): void
}

interface RealtimeConnectionOptions {
  apiBase: string
  appKey: string
  host: string
  port: number
  scheme: 'http' | 'https'
  channelName: string
  authHeaders?: Record<string, string>
  onNotification: (payload: unknown) => void
  onReconnect: () => void | Promise<void>
}

interface RealtimeConnectionDependencies {
  createEcho?: (options: Record<string, unknown>) => RealtimeEchoClient
  fetch?: typeof globalThis.fetch
}

export interface NotificationRealtimeConnection {
  disconnect(): void
}

function normalizeBaseUrl(baseUrl: string): string {
  return baseUrl.replace(/\/$/, '')
}

export function channelAuthorizationUrl(apiBase: string): string {
  return `${normalizeBaseUrl(apiBase)}/api/broadcasting/auth`
}

export function csrfCookieUrl(apiBase: string): string {
  return `${normalizeBaseUrl(apiBase)}/sanctum/csrf-cookie`
}

function readXsrfToken(): string | null {
  if (typeof document === 'undefined') {
    return null
  }

  const token = document.cookie
    .split('; ')
    .find(cookie => cookie.startsWith('XSRF-TOKEN='))
    ?.slice('XSRF-TOKEN='.length)

  if (!token) {
    return null
  }

  try {
    return decodeURIComponent(token)
  }
  catch {
    return token
  }
}

export function createChannelAuthorizationHandler(
  apiBase: string,
  headers: Record<string, string> = {},
  fetchImplementation: typeof globalThis.fetch = globalThis.fetch,
  xsrfTokenReader: XsrfTokenReader = readXsrfToken,
) {
  return async (
    params: ChannelAuthorizationParams,
    callback: ChannelAuthorizationCallback,
  ): Promise<void> => {
    try {
      let xsrfToken = xsrfTokenReader()

      if (xsrfToken === null) {
        const csrfResponse = await fetchImplementation(csrfCookieUrl(apiBase), {
          credentials: 'include',
          headers: { Accept: 'application/json' },
        })

        if (!csrfResponse.ok) {
          callback(new Error('Private notification channel authorization failed.'), null)
          return
        }

        xsrfToken = xsrfTokenReader()
      }

      const response = await fetchImplementation(channelAuthorizationUrl(apiBase), {
        method: 'POST',
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          ...headers,
          ...(xsrfToken === null ? {} : { 'X-XSRF-TOKEN': xsrfToken }),
        },
        body: JSON.stringify({
          socket_id: params.socketId,
          channel_name: params.channelName,
        }),
      })

      if (!response.ok) {
        callback(new Error('Private notification channel authorization failed.'), null)
        return
      }

      callback(null, await response.json() as ChannelAuthorizationData)
    }
    catch {
      callback(new Error('Private notification channel authorization failed.'), null)
    }
  }
}

export function createNotificationRealtimeConnection(
  options: RealtimeConnectionOptions,
  dependencies: RealtimeConnectionDependencies = {},
): NotificationRealtimeConnection {
  const forceTls = options.scheme === 'https'
  const enabledTransports: Array<'ws' | 'wss'> = forceTls ? ['wss'] : ['ws']
  const echoOptions = {
    broadcaster: 'reverb' as const,
    key: options.appKey,
    wsHost: options.host,
    wsPort: options.port,
    wssPort: options.port,
    forceTLS: forceTls,
    enabledTransports,
    Pusher,
    channelAuthorization: {
      customHandler: createChannelAuthorizationHandler(
        options.apiBase,
        options.authHeaders,
        dependencies.fetch,
      ),
    },
  }
  const echo = dependencies.createEcho
    ? dependencies.createEcho(echoOptions)
    : new Echo<'reverb'>(echoOptions) as unknown as RealtimeEchoClient

  echo.private(options.channelName).notification(options.onNotification)

  let hasConnected = false
  let connectionWasInterrupted = false
  const stopConnectionListener = echo.connector.onConnectionChange((status) => {
    if (status === 'connected') {
      if (hasConnected && connectionWasInterrupted) {
        void options.onReconnect()
      }

      hasConnected = true
      connectionWasInterrupted = false
      return
    }

    if (hasConnected) {
      connectionWasInterrupted = true
    }
  })

  return {
    disconnect(): void {
      stopConnectionListener()
      echo.leave(options.channelName)
      echo.disconnect()
    },
  }
}
