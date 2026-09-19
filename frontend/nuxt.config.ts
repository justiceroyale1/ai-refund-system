import tailwindcss from '@tailwindcss/vite'

export default defineNuxtConfig({
  compatibilityDate: '2025-07-15',
  css: ['~/assets/css/tailwind.css'],
  devtools: { enabled: true },
  modules: [
    '@nuxt/eslint',
    '@pinia/nuxt',
    'shadcn-nuxt',
    'vue-sonner/nuxt',
  ],
  runtimeConfig: {
    public: {
      apiBase: '',
    },
  },
  shadcn: {
    componentDir: './app/components/ui',
    prefix: '',
  },
  vite: {
    plugins: [tailwindcss()],
  },
})
