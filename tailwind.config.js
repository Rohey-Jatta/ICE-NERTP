import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{jsx,js}',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                'iec-pink': {
                    DEFAULT: '#E91E8C',
                    50:  '#FFF0F8',
                    100: '#FFD6EE',
                    200: '#FFB3E0',
                    300: '#FF80C8',
                    400: '#F040A4',
                    500: '#E91E8C',
                    600: '#C4186F',
                    700: '#9E1258',
                    800: '#790D43',
                    900: '#54082E',
                    dark:  '#C4186F',
                    light: '#F040A4',
                },
                'iec-navy': {
                    DEFAULT: '#0D1B2A',
                    50:  '#EDF3F8',
                    100: '#C5D8E8',
                    200: '#9DBDD8',
                    300: '#75A1C8',
                    400: '#4D86B8',
                    500: '#1E3A5F',
                    600: '#1A2B3C',
                    700: '#0D1B2A',
                    800: '#081320',
                    900: '#040A15',
                    light: '#1A2B3C',
                },
            },
            boxShadow: {
                'iec-sm': '0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04)',
                'iec-md': '0 4px 6px rgba(0,0,0,.07), 0 2px 4px rgba(0,0,0,.05)',
                'iec-lg': '0 10px 15px rgba(0,0,0,.07), 0 4px 6px rgba(0,0,0,.04)',
                'iec-xl': '0 20px 25px rgba(0,0,0,.08), 0 8px 10px rgba(0,0,0,.04)',
            },
        },
    },

    plugins: [],
};