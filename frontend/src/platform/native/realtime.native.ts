/**
 * En Capacitor, el frontend corre dentro de un WebView que soporta WebSocket
 * nativo. Por eso la implementación de realtime es exactamente la misma que
 * la web — solo cambia el host (debe ser absoluto, no `localhost`).
 *
 * Re-exportamos el adapter web para mantener una sola implementación.
 */
export { realtimeWeb as realtimeNative } from '../web/realtime.web'
