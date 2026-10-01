import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            colors: {
                cput: {
                    navy: {
                        DEFAULT: '#002B49',
                        light: '#003D6B',
                        dark: '#001A2E',
                    },
                    blue: {
                        DEFAULT: '#0072CE',
                        light: '#3395D9',
                        dark: '#005AA3',
                    },
                    cyan: {
                        DEFAULT: '#00A3E0',
                        light: '#33B8E8',
                        dark: '#0082B3',
                    },
                    slate: {
                        DEFAULT: '#F8FAFC',
                        dark: '#E2E8F0',
                    },
                    emergency: {
                        DEFAULT: '#DC2626',
                        light: '#EF4444',
                        dark: '#B91C1C',
                    },
                },
                triage: {
                    emergency: '#DC2626',
                    urgent: '#F59E0B',
                    routine: '#10B981',
                },
            },
            fontFamily: {
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            boxShadow: {
                'cput': '0 4px 6px -1px rgba(0, 43, 73, 0.1), 0 2px 4px -1px rgba(0, 43, 73, 0.06)',
                'cput-lg': '0 10px 15px -3px rgba(0, 43, 73, 0.1), 0 4px 6px -2px rgba(0, 43, 73, 0.05)',
            },
        },
    },
    plugins: [
        forms,
    ],
};