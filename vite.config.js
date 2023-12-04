import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    content: [
        // ...
        './vendor/bezhansalleh/filament-language-switch/resources/views/language-switch.blade.php',
    ],
});
