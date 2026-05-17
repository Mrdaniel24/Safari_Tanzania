import { defineConfig } from 'vite'

export default defineConfig({
  build: {
    outDir: 'assets/js',
    emptyOutDir: false,
    lib: {
      entry: './src/main.js',
      formats: ['iife'],
      name: 'ST',
      fileName: () => 'animations'
    }
  }
})
