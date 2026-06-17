import { defineConfig } from 'vite'
import laravel, { refreshPaths } from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/filament/admin/theme.css'],
            refresh: [
                ...refreshPaths,
                'app/Filament/**',
                'app/Providers/Filament/**',
            ],
        }),
        tailwindcss(),
    ],
})
