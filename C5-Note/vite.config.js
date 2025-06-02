import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vitejs.dev/config/
export default defineConfig({
  base:"/",
  plugins: [react()],
  
  server: {
    proxy: {
      '/backend': {
        target: 'http://localhost:8000', // PHP server
        changeOrigin: true
      }
    }
  }
})
