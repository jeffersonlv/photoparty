/** @type {import('tailwindcss').Config} */
// Identidade "cabine de fotos": violeta de marca, tinta aubergine e o amarelo do flash.
// "indigo" aponta para a escala da marca para que classes antigas (inclusive no JS da moderação) sigam a identidade.
const brand = {
    50: '#f3f1ff',
    100: '#e9e5ff',
    200: '#d6cfff',
    300: '#b9acff',
    400: '#9684fa',
    500: '#7761f2',
    600: '#5b45e8',
    700: '#4a35cf',
    800: '#3c2ca6',
    900: '#2e2380',
    950: '#1d1650',
};

export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
    ],
    theme: {
        extend: {
            colors: {
                brand,
                indigo: brand,
                ink: { DEFAULT: '#181330', soft: '#2b2550' },
                flash: { DEFAULT: '#ffd23f', soft: '#fff3c2', deep: '#8a6400' },
                canvas: '#f7f5fc',
                line: '#e6e2f2',
                muted: '#5a5575',
            },
            fontFamily: {
                sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'sans-serif'],
                display: ['Anton', 'Impact', 'Haettenschweiler', 'Arial Narrow Bold', 'sans-serif'],
            },
            minHeight: { touch: '2.75rem' },
            minWidth: { touch: '2.75rem' },
            boxShadow: {
                card: '0 1px 2px rgba(24, 19, 48, .05), 0 4px 14px rgba(24, 19, 48, .04)',
                bar: '0 -6px 24px rgba(24, 19, 48, .14)',
            },
        },
    },
    plugins: [],
};
