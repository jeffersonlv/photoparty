import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/fonts.css',
                'resources/css/guest.css',
                'resources/css/admin.css',
                'resources/css/home.css',
                'resources/js/guest/index.js',
            ],
            refresh: true,
        }),
    ],
    test: {
        environment: 'node',
        include: ['tests/js/**/*.test.js'],
    },
});
