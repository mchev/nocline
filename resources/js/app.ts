import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SetupLayout from '@/layouts/SetupLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { t } from '@/lib/i18n';
import { opsPollingTransport } from '@/lib/opsLiveTransport';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    live: {
        transport: opsPollingTransport(10_000),
        throttle: 2_000,
        pauseWhenHidden: true,
    },
    layout: (name) => {
        switch (true) {
            case name === 'wallboard/Show':
            case name === 'status/Show':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('setup/'):
                return SetupLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    withApp: (app) => {
        app.config.globalProperties.$t = t;
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#F4512A',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
