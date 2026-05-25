import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import type { PlatformRealtime, RealtimeChannel, RealtimeOptions } from '../types'
import { logger } from '@/utils/logger'

const log = logger.child('Realtime')

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type EchoInstance = Echo<any>

let echoInstance: EchoInstance | null = null

// Pusher debe estar en window para laravel-echo en browser. La asignación se
// hace una sola vez al primer `connect()` para evitar leaks en SSR/tests.
function ensurePusherGlobal(): void {
  if (typeof window === 'undefined') return
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const w = window as any
  if (!w.Pusher) w.Pusher = Pusher
}

function buildAuthorizer(token: string, tenantSlug?: string) {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  return (channel: any) => ({
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    authorize: (socketId: string, callback: (err: any, data?: any) => void) => {
      const headers: Record<string, string> = {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
      }
      if (tenantSlug) headers['X-Tenant-ID'] = tenantSlug

      fetch(`${import.meta.env.VITE_API_URL}/broadcasting/auth`, {
        method: 'POST',
        headers,
        body: JSON.stringify({ socket_id: socketId, channel_name: channel.name }),
      })
        .then((response) => {
          if (!response.ok) throw new Error('Authorization failed')
          return response.json()
        })
        .then((data) => callback(null, data))
        .catch((error) => callback(error, null))
    },
  })
}

export const realtimeWeb: PlatformRealtime = {
  connect(token: string, opts: RealtimeOptions = {}): void {
    if (echoInstance) return

    ensurePusherGlobal()

    echoInstance = new Echo({
      broadcaster: 'reverb',
      key: import.meta.env.VITE_REVERB_APP_KEY,
      wsHost: import.meta.env.VITE_REVERB_HOST,
      wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
      wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
      forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
      enabledTransports: ['ws', 'wss'],
      authorizer: buildAuthorizer(token, opts.tenantSlug),
    })

    log.info('Realtime connected', { tenant: opts.tenantSlug })
  },

  disconnect(): void {
    if (echoInstance) {
      echoInstance.disconnect()
      echoInstance = null
      log.info('Realtime disconnected')
    }
  },

  isConnected(): boolean {
    return echoInstance !== null
  },

  privateChannel(name: string): RealtimeChannel {
    if (!echoInstance) throw new Error('Realtime no conectado — invocar connect() primero')
    const ch = echoInstance.private(name)
    return wrapChannel(ch, () => echoInstance?.leave(name))
  },

  presenceChannel(name: string): RealtimeChannel {
    if (!echoInstance) throw new Error('Realtime no conectado — invocar connect() primero')
    const ch = echoInstance.join(name)
    return wrapChannel(ch, () => echoInstance?.leave(name))
  },
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
function wrapChannel(ch: any, leaveFn: () => void): RealtimeChannel {
  const wrapper: RealtimeChannel = {
    listen(event: string, cb: (data: unknown) => void): RealtimeChannel {
      ch.listen(event, cb)
      return wrapper
    },
    stopListening(event: string): RealtimeChannel {
      ch.stopListening(event)
      return wrapper
    },
    leave(): void {
      leaveFn()
    },
  }
  return wrapper
}
