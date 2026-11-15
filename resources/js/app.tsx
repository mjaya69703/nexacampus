import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

import '@fontsource-variable/inter';
import '@fontsource/source-serif-4/600.css';
import '@fontsource/source-serif-4/700.css';
import '../css/react-base.css';

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.tsx', { eager: true });
        const page = pages[`./pages/${name}.tsx`] as { default: any } | undefined;

        if (!page) throw new Error(`Inertia page not found: ${name}`);
        return page.default;
    },
    setup({ el, App, props }) {
        if (!el) return;
        createRoot(el).render(<App {...props} />);
    },
    progress: { color: '#79e6b2' },
});
