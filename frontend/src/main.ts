import { createPinia } from 'pinia'
import { createApp } from 'vue'
import { initializeApi } from './api'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import './style.css'

async function bootstrap() {
  const app = createApp(App)
  app.use(createPinia())
  app.use(router)

  await initializeApi()
  await useAuthStore().restore()

  app.mount('#app')
}

bootstrap()
