/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./**/*.{php,html,js}",
    "!./node_modules/**",
    "!./vendor/**",
  ],
  theme: { extend: {} },
  plugins: [require("@tailwindcss/line-clamp")],
};
