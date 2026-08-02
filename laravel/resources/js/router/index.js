import { createRouter, createWebHistory } from 'vue-router';

import { TOKEN_KEY } from '../api/http';
import { useAuthStore } from '../stores/auth';

/**
 * TaraPickle routing.
 *
 *  - "/" and "/play" are fully open-access — anyone can run queues.
 *  - "/login", "/register" are guest-only.
 *  - "/queue", "/admin", "/admin/analytics" require an account; the admin
 *    routes additionally require users.is_admin (mirrored server-side by the
 *    can:manage-court gate on the API).
 */
const routes = [
    {
        path: '/',
        name: 'landing',
        component: () => import('../views/LandingView.vue'),
    },
    {
        path: '/play',
        name: 'play',
        component: () => import('../views/TaraPickleDashboard.vue'),
    },
    {
        path: '/login',
        name: 'login',
        component: () => import('../views/LoginView.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/register',
        name: 'register',
        component: () => import('../views/RegisterView.vue'),
        meta: { guestOnly: true },
    },
    {
        path: '/queue',
        name: 'queue',
        component: () => import('../views/QueueDashboard.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/admin',
        name: 'admin',
        component: () => import('../views/AdminAnalytics.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
        path: '/admin/analytics',
        name: 'admin.analytics',
        component: () => import('../views/AdminAnalytics.vue'),
        meta: { requiresAuth: true, requiresAdmin: true },
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/',
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (to.meta.requiresAuth || to.meta.requiresAdmin) {
        const hasToken = Boolean(localStorage.getItem(TOKEN_KEY));

        if (!hasToken) {
            return { name: 'login', query: { redirect: to.fullPath } };
        }

        if (!auth.initialized) {
            await auth.ensureSession();
        }

        if (!auth.isAuthenticated) {
            return { name: 'login', query: { redirect: to.fullPath } };
        }

        if (to.meta.requiresAdmin && !auth.isAdmin) {
            return { name: 'play' };
        }

        if (to.name === 'landing' && auth.isAuthenticated) {
            return { name: 'play' };
        }
    }

    if (to.meta.guestOnly) {
        if (localStorage.getItem(TOKEN_KEY) && !auth.initialized) {
            await auth.ensureSession();
        }

        if (auth.isAuthenticated) {
            return { name: 'play' };
        }
    }

    return true;
});

export default router;
