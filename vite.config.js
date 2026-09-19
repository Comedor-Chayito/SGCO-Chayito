import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

const vitePort = Number(process.env.VITE_PORT ?? 5173);

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/css/app.css', 'resources/js/app.js'],
      refresh: true,
    }),
    tailwindcss(),
    vue(),
  ],
  resolve: {
    alias: {
      '@': '/resources/js',
    },
  },
  server: {
    host: '0.0.0.0',
    port: vitePort,
    strictPort: true,
    hmr: {
      host: 'localhost',
      port: vitePort,
    },
    watch: {
      ignored: ['**/storage/framework/views/**'],
    },
  },
});
