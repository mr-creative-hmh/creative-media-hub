import path from 'node:path';
import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig, lazyPlugins } from 'vite-plus';

const sharedIgnorePatterns = [
    '.agents/**',
    '.claude/**',
    '.codex/**',
    '.cursor/**',
    '.github/**',
    'bootstrap/ssr/**',
    'composer.json',
    'dist/**',
    'graphify-out/**',
    'node_modules/**',
    'public/**',
    'resources/js/actions/**',
    'resources/js/components/ui/*',
    'resources/js/routes/**',
    'resources/js/wayfinder/**',
    'resources/views/mail/*',
    'scratch/**',
    'storage/**',
    'tailwind.config.js',
    'vendor/**',
];

export default defineConfig({
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
            command: 'php -d memory_limit=512M artisan wayfinder:generate',
        }),
    ]),
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.codex/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/graphify-out/**',
                '**/scratch/**',
                '**/vendor/**',
            ],
        },
    },
    lint: {
        ignorePatterns: sharedIgnorePatterns,
        options: {
            denyWarnings: true,
            typeAware: true,
        },
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        singleQuote: true,
        semi: true,
        singleAttributePerLine: false,
        htmlWhitespaceSensitivity: 'css',
        ignorePatterns: sharedIgnorePatterns,
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            entryPoint: 'resources/css/app.css',
        },
    },
});
