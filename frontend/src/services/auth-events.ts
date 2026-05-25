/**
 * Bus de eventos de autenticación.
 *
 * Permite que la capa HTTP comunique al router que el token caducó (401)
 * sin importar `vue-router` ni `window.location`. El router se suscribe en
 * `main.ts` y decide a dónde navegar.
 */

export type AuthEvent = 'auth:unauthorized' | 'auth:logout'

type Listener = () => void

const listeners = new Map<AuthEvent, Set<Listener>>()

export function onAuthEvent(event: AuthEvent, cb: Listener): () => void {
  let bucket = listeners.get(event)
  if (!bucket) {
    bucket = new Set()
    listeners.set(event, bucket)
  }
  bucket.add(cb)
  return () => bucket?.delete(cb)
}

export function emitAuthEvent(event: AuthEvent): void {
  const bucket = listeners.get(event)
  if (!bucket) return
  bucket.forEach((cb) => {
    try {
      cb()
    } catch {
      // No queremos que un listener defectuoso impida que los demás se ejecuten.
    }
  })
}
