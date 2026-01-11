/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          50: 'rgb(var(--primary-50-rgb) / <alpha-value>)',
          100: 'rgb(var(--primary-100-rgb) / <alpha-value>)',
          200: 'rgb(var(--primary-200-rgb) / <alpha-value>)',
          300: 'rgb(var(--primary-300-rgb) / <alpha-value>)',
          400: 'rgb(var(--primary-400-rgb) / <alpha-value>)',
          500: 'rgb(var(--primary-500-rgb) / <alpha-value>)',
          600: 'rgb(var(--primary-600-rgb) / <alpha-value>)',
          700: 'rgb(var(--primary-700-rgb) / <alpha-value>)',
          800: 'rgb(var(--primary-800-rgb) / <alpha-value>)',
          900: 'rgb(var(--primary-900-rgb) / <alpha-value>)',
        },
        secondary: {
          50: 'rgb(var(--secondary-50-rgb) / <alpha-value>)',
          100: 'rgb(var(--secondary-100-rgb) / <alpha-value>)',
          200: 'rgb(var(--secondary-200-rgb) / <alpha-value>)',
          300: 'rgb(var(--secondary-300-rgb) / <alpha-value>)',
          400: 'rgb(var(--secondary-400-rgb) / <alpha-value>)',
          500: 'rgb(var(--secondary-500-rgb) / <alpha-value>)',
          600: 'rgb(var(--secondary-600-rgb) / <alpha-value>)',
          700: 'rgb(var(--secondary-700-rgb) / <alpha-value>)',
          800: 'rgb(var(--secondary-800-rgb) / <alpha-value>)',
          900: 'rgb(var(--secondary-900-rgb) / <alpha-value>)',
        },
      },
      fontFamily: {
        sans: ['Inter', 'sans-serif'],
      },
    },
  },
  plugins: [],
}
