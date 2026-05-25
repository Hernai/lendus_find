/**
 * Implementación native de PlatformStorage.
 *
 * En Capacitor el frontend corre en una WebView que tiene `localStorage`
 * funcional y persistente entre sesiones de la app (los datos viven en el
 * sandbox de la app, no se borran al cerrarla). Eso significa que el código
 * sincrónico legacy (`utils/storage.ts`) y el async `platform.storage`
 * comparten EL MISMO STORAGE. Sin esto, el auth store guarda el token via
 * localStorage pero el interceptor HTTP lo busca en @capacitor/preferences
 * → 401 "You must be logged in".
 *
 * Reutilizamos directamente la implementación web para mantener una sola
 * fuente de verdad. Si en el futuro necesitamos cifrado adicional en native
 * (ej. tokens de larga vida), migrar a `@capacitor/secure-storage-plugin`
 * y mantener la misma interfaz.
 */
export { storageWeb as storageNative } from '../web/storage.web'
