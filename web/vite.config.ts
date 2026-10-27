import path from 'node:path'
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    port: 5174,
    strictPort: true,
  },
  build: {
    rollupOptions: {
      output: {
        // Libraries change far less often than the app: in their own chunk, a
        // returning visitor keeps them cached across deploys.
        manualChunks: {
          vendor: [
            'react',
            'react-dom',
            'react-router-dom',
            '@tanstack/react-query',
            'i18next',
            'react-i18next',
            'i18next-browser-languagedetector',
            'axios',
          ],
        },
      },
    },
  },
})
