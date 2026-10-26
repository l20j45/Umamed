import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

// Paleta "papel y pino": verdes apagados de consultorio, neutros cálidos.
// Se reasignan también emerald/blue/indigo para que las pantallas existentes
// queden dentro de la misma familia sin reescribirlas.
const pino = {
    50: '#F1F5F2',
    100: '#DEE8E1',
    200: '#BFD1C5',
    300: '#98B3A4',
    400: '#729480',
    500: '#527660',
    600: '#3E5C4A',
    700: '#2F4739',
    800: '#243830',
    900: '#192A24',
};

const arcilla = {
    50: '#F9F0EC',
    100: '#F1DCD4',
    200: '#E3B9A9',
    300: '#D0937D',
    400: '#B96F55',
    500: '#A2553C',
    600: '#86432F',
    700: '#6B3525',
    800: '#522A1E',
    900: '#3C2017',
};

/** @type {import('tailwindcss').Config} */
export default {
    // El modo oscuro queda opt-in: el consultorio trabaja siempre en claro.
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['Lora', 'Georgia', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                ink: '#1C1B18',
                paper: '#F7F5F0',
                sand: '#E4DED2',
                pine: pino,
                arcilla,
                emerald: pino,
                blue: pino,
                indigo: pino,
                sky: pino,
                purple: arcilla,
                gray: {
                    50: '#FAF8F4',
                    100: '#F3F0E8',
                    200: '#E6E1D5',
                    300: '#D2CBBA',
                    400: '#A9A192',
                    500: '#7E7666',
                    600: '#5C5548',
                    700: '#433D33',
                    800: '#2C2823',
                    900: '#1C1B18',
                },
            },
            borderRadius: {
                DEFAULT: '2px',
                sm: '2px',
                md: '3px',
                lg: '4px',
                xl: '6px',
                '2xl': '8px',
                '3xl': '10px',
            },
            boxShadow: {
                sm: '0 1px 2px rgba(28,27,24,.05)',
                DEFAULT: '0 1px 3px rgba(28,27,24,.07)',
                md: '0 4px 10px -4px rgba(28,27,24,.12)',
            },
        },
    },

    plugins: [forms],
};
