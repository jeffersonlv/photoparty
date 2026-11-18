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
                'resources/css/screen.css',
                'resources/js/screen/index.js',
                'resources/js/moderation/index.js',
                'resources/js/guest/index.js',
                'resources/js/home/demo.js',
            ],
            refresh: true,
        }),
    ],
    test: {
        environment: 'node',
        include: ['tests/js/**/*.test.js'],
    },
});
