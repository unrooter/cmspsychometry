/** @type {import('tailwindcss').Config} */
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
    'hover:shadow-md'
  ],
  theme: {
    extend: {}
  },
  plugins: []
};
