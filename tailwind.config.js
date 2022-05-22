module.exports = {
    purge: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./resources/**/*.vue",
    ],
    darkMode: false, // or 'media' or 'class'
    theme: {
        extend: {},
        height: {
            "3/4": "75vh",
        },
    },
    variants: {
        extend: {},
    },
    plugins: [],
};
