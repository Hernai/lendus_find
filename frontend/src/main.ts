import './assets/main.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import { initCsrf } from './services/api'

// Initialize CSRF cookie for Sanctum before mounting app
initCsrf()

const app = createApp(App)

app.use(createPinia())
app.use(router)

app.mount('#app')
