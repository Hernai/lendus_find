import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type EchoInstance = Echo<any>

declare global {
  interface Window {
    Pusher: typeof Pusher
    Echo: EchoInstance
  }
}

// Hacer Pusher disponible globalmente (requerido por Laravel Echo)
window.Pusher = Pusher

let echoInstance: EchoInstance | null = null

export function initializeEcho(token: string): EchoInstance {
  if (echoInstance) {
    return echoInstance
  }

  echoInstance = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel: any) => {
      return {
        authorize: (socketId: string, callback: Function) => {
          fetch(`${import.meta.env.VITE_API_URL}/broadcasting/auth`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Authorization': `Bearer ${token}`,
              'Accept': 'application/json',
            },
            body: JSON.stringify({
              socket_id: socketId,
              channel_name: channel.name,
            }),
          })
            .then((response) => {
              if (!response.ok) {
                throw new Error('Authorization failed')
              }
              return response.json()
            })
            .then((data) => {
              callback(null, data)
            })
            .catch((error) => {
              callback(error, null)
            })
        },
      }
    },
  })

  window.Echo = echoInstance

  return echoInstance
}

export function disconnectEcho(): void {
  if (echoInstance) {
    echoInstance.disconnect()
    echoInstance = null
  }
}

export function getEcho(): EchoInstance | null {
  return echoInstance
}
