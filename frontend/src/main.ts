import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import { bindRouter, platform } from './platform'
import { onAuthEvent } from './services/auth-events'

// Configure Monaco Editor web workers
import * as monaco from 'monaco-editor'
import editorWorker from 'monaco-editor/esm/vs/editor/editor.worker?worker'
import jsonWorker from 'monaco-editor/esm/vs/language/json/json.worker?worker'
import cssWorker from 'monaco-editor/esm/vs/language/css/css.worker?worker'
import htmlWorker from 'monaco-editor/esm/vs/language/html/html.worker?worker'
import tsWorker from 'monaco-editor/esm/vs/language/typescript/ts.worker?worker'

self.MonacoEnvironment = {
  getWorker(_: any, label: string) {
    if (label === 'json') {
      return new jsonWorker()
    }
    if (label === 'css' || label === 'scss' || label === 'less') {
      return new cssWorker()
    }
    if (label === 'html' || label === 'handlebars' || label === 'razor') {
      return new htmlWorker()
    }
    if (label === 'typescript' || label === 'javascript') {
      return new tsWorker()
    }
    return new editorWorker()
  }
}

bindRouter(router)

// Cuando la capa HTTP detecte 401 fuera de endpoints de auth, redirigir al login
// vía router (en vez de window.location.href).
onAuthEvent('auth:unauthorized', () => {
  if (router.currentRoute.value.path !== '/auth') {
    router.replace('/auth')
  }
})

// Solicitar permiso de geolocalización al arrancar (fire-and-forget).
// La posición se cachea 60s y se adjunta en cada request HTTP como
// X-Geo-Lat / X-Geo-Lng para auditoría de seguridad.
//
// IMPORTANTE: en el navegador web, `navigator.permissions.query` solo
// CONSULTA el estado pero no muestra el prompt. El prompt nativo del browser
// solo aparece cuando se invoca `getCurrentPosition()`. Por eso llamamos
// directamente `getCurrent()` cuando el estado es 'prompt' o 'granted' — eso
// dispara el diálogo en la primera carga. Si el usuario ya denegó, no se
// muestra de nuevo (browser lo recuerda).
;(async () => {
  try {
    if (!platform.geolocation.isSupported()) return
    const perm = await platform.geolocation.requestPermission()
    if (perm === 'denied' || perm === 'unsupported') return
    // 'granted' o 'prompt' — disparar la lectura para que el browser
    // muestre el prompt si aún no lo ha hecho.
    await platform.geolocation.getCurrent({ cacheMs: 60_000, timeoutMs: 5_000 })
  } catch {
    // best-effort
  }
})()

const app = createApp(App)

app.use(createPinia())
app.use(router)

app.mount('#app')
