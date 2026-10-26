import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

// The dashboard talks to the Laravel API and the realtime service through a
// proxy, so the browser sees a single origin and no CORS configuration is needed.
const api = {
  '/api': { target: process.env.API_URL ?? 'http://127.0.0.1:8000', changeOrigin: true },
  '/socket.io': { target: process.env.REALTIME_URL ?? 'http://127.0.0.1:3000', ws: true },
};

export default defineConfig({
  plugins: [react()],
  server: { port: 5173, proxy: api },
  preview: { port: 4173, proxy: api },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/test/setup.ts'],
    include: ['src/**/*.test.{ts,tsx}'],
  },
});
