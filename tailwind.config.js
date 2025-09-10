/** @type {import('tailwindcss').Config} */
module.exports = {
	content: [
		'./system/templates/**/*.twig',
		'./system/pages/**/*.php',
		'./templates/chronicles/index.php',
		'./templates/chronicles/**/*.php',
	],
	theme: {
		extend: {},
	},
	plugins: [],
};
