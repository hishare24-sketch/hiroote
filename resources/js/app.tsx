import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const env = import.meta.env as Record<string, string | undefined>;
const appName = env.VITE_APP_NAME ?? 'Hiroote AI';

interface PageModule {
    default: React.ComponentType;
}

void createInertiaApp({
    title: (title) => (title === '' ? appName : `${title} — ${appName}`),
    resolve: (name) =>
        resolvePageComponent<PageModule>(
            `./Pages/${name}.tsx`,
            import.meta.glob<PageModule>('./Pages/**/*.tsx'),
        ).then((module) => module.default),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: 'oklch(0.58 0.18 250)',
    },
});
