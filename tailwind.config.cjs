/** @type {import('tailwindcss').Config} */
const colors = require('tailwindcss/colors');

module.exports = {
  content: [
    './addons/cms/view/default/**/*.html'
  ],
  safelist: [
    'grid',
    'grid-cols-2',
    'sm:grid-cols-3',
    'lg:grid-cols-4',
    'gap-4',
    'lg:grid-cols-12',
    'lg:col-span-8',
    'lg:col-span-4',
    'aspect-[4/3]',
    'aspect-[4/1]',
    'object-cover',
    'group',
    'group-hover:scale-105',
    'shadow-sm',
    'hover:shadow-md',

    // xunsearch.html runtime classList
    'text-rose-600',
    'not-italic',
    'font-semibold',
    'min-w-9',
    'rounded-lg',
    'shadow-lg',
    'z-30',
    'cursor-pointer',
    'hover:bg-gray-50',
    'bg-black',
    'border-black',
    'opacity-60'
  ],
  theme: {
    extend: {
      colors: {
        sand: {
          50: '#f9fafb',
          100: '#f3f4f6',
          200: '#f0dfc5',
          300: '#e5cba6',
          400: '#d7b17e',
          500: '#c99558',
          600: '#b77f45',
          700: '#9a6538',
          800: '#7d532f',
          900: '#654428'
        },
        emerald: {
          50: '#f3f3f3',
          100: '#e7e7e7',
          200: '#d1d1d1',
          300: '#b0b0b0',
          400: '#888888',
          500: '#666666',
          600: '#4d4d4d',
          700: '#333333',
          800: '#1f1f1f',
          900: '#111111',
          950: '#0a0a0a'
        },
        gray: {
          ...colors.gray
        },
        slate: colors.slate,
        zinc: colors.zinc,
        stone: colors.stone,
        red: colors.red,
        rose: colors.rose,
        amber: colors.amber,
        indigo: colors.indigo,
        purple: colors.purple,
        white: colors.white,
        black: colors.black
      },
      fontFamily: {
        display: ['ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Inter', 'Noto Sans SC', 'Noto Sans JP', 'sans-serif'],
        sans: ['ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Inter', 'Noto Sans SC', 'Noto Sans JP', 'sans-serif']
      },
      borderRadius: {
        sm: '0.5rem',
        md: '0.75rem',
        lg: '1rem',
        xl: '1.25rem',
        '2xl': '1.5rem'
      },
      boxShadow: {
        sm: '0 1px 2px rgba(17, 24, 39, 0.06), 0 1px 1px rgba(17, 24, 39, 0.04)',
        md: '0 12px 28px rgba(17, 24, 39, 0.10), 0 2px 6px rgba(17, 24, 39, 0.05)',
        soft: '0 18px 48px rgba(17, 24, 39, 0.10)'
      }
    }
  },
  plugins: []
};
