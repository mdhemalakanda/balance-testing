import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
  plugins: [react()],
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
    global: 'globalThis',
  },
  build: {
    lib: {
      entry: path.resolve(__dirname, 'src/main.jsx'),
      name: 'MyPlugin',
      fileName: (format) => `balance-testing.${format}.js`,
      formats: ['es', 'umd'],
    },
  },
  server: {
    cors: true,
    host: true,
  },
});
