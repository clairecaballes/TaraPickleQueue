import { createApp } from 'vue';

import App from './App.vue';
import router from './router';
import { pinia } from './stores';
import { useAuthStore } from './stores/auth';

const app = createApp(App);

app.use(pinia);
app.use(router);

const auth = useAuthStore();

if (localStorage.getItem('auth_token')) {
    auth.ensureSession().then(() => {
        if (auth.isAdmin && router.currentRoute.value.path === '/play') {
            router.replace('/admin');
        }
    });
}

app.mount('#app');
