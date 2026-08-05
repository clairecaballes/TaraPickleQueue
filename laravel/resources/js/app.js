import { createApp } from 'vue';

import App from './App.vue';
import router from './router';
import { pinia } from './stores';
import { useAuthStore } from './stores/auth';
import { initTheme } from './utils/theme';

// Restore the Outdoor Daylight / dark theme before first paint.
initTheme();

const app = createApp(App);

app.use(pinia);
app.use(router);

const auth = useAuthStore();

if (localStorage.getItem('auth_token')) {
    auth.ensureSession();
}

app.mount('#app');
