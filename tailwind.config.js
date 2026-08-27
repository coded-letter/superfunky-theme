/**
 * Dedicated Tailwind build for the FunkyCommerce Headless theme's native WordPress
 * frontend (block templates, template parts, loader, and Spotify slot). This is
 * intentionally separate from the headless storefront's own Tailwind config — the
 * theme's compiled CSS is embedded/enqueued independently for the native, non-headless
 * rendering path (see assets/css/theme-source.css and inc/frontend-theme.php).
 *
 * Brand tokens mirror workspace/frontend/apps/storefront/tailwind.config.ts and the
 * matching CSS custom properties in theme.json so native and headless output stay in
 * visual parity.
 */
module.exports = {
	darkMode: ["class", '[data-funky-theme="dark"]'],
	content: [
		"./templates/**/*.html",
		"./parts/**/*.html",
		"./assets/js/**/*.js",
	],
	corePlugins: {
		preflight: false,
	},
	theme: {
		extend: {
			fontFamily: {
				sans: ["ui-sans-serif", "system-ui", "-apple-system", "BlinkMacSystemFont", '"Segoe UI"', "sans-serif"],
				display: ["ui-rounded", '"SF Pro Rounded"', "ui-sans-serif", "system-ui", "sans-serif"],
			},
			colors: {
				brand: {
					50: "rgb(var(--fc-brand-50) / <alpha-value>)",
					100: "rgb(var(--fc-brand-100) / <alpha-value>)",
					200: "rgb(var(--fc-brand-200) / <alpha-value>)",
					300: "rgb(var(--fc-brand-300) / <alpha-value>)",
					400: "rgb(var(--fc-brand-400) / <alpha-value>)",
					500: "rgb(var(--fc-brand-500) / <alpha-value>)",
					600: "rgb(var(--fc-brand-600) / <alpha-value>)",
					700: "rgb(var(--fc-brand-700) / <alpha-value>)",
					800: "rgb(var(--fc-brand-800) / <alpha-value>)",
					900: "rgb(var(--fc-brand-900) / <alpha-value>)",
					950: "rgb(var(--fc-brand-950) / <alpha-value>)",
				},
			},
			boxShadow: {
				soft: "0 1px 2px rgba(15, 15, 40, 0.04), 0 12px 32px -12px rgba(15, 15, 40, 0.12)",
				"soft-lg": "0 4px 12px rgba(15, 15, 40, 0.06), 0 24px 48px -16px rgba(15, 15, 40, 0.18)",
				glow: "0 0 0 1px rgb(var(--fc-brand-500) / 0.15), 0 8px 24px -4px rgb(var(--fc-brand-500) / 0.35)",
			},
			borderRadius: {
				sm: "calc(var(--fc-radius, 16px) * 0.25)",
				DEFAULT: "calc(var(--fc-radius, 16px) * 0.25)",
				md: "calc(var(--fc-radius, 16px) * 0.375)",
				lg: "calc(var(--fc-radius, 16px) * 0.5)",
				xl: "calc(var(--fc-radius, 16px) * 0.75)",
				"2xl": "var(--fc-radius, 16px)",
				"3xl": "calc(var(--fc-radius, 16px) * 1.5)",
				control: "calc(var(--fc-radius, 16px) * 3)",
			},
			backgroundImage: {
				"brand-gradient": "linear-gradient(135deg, rgb(var(--fc-brand-gradient-from)) 0%, rgb(var(--fc-brand-gradient-to)) 100%)",
				"brand-gradient-soft":
					"linear-gradient(135deg, rgb(var(--fc-brand-gradient-from) / 0.12) 0%, rgb(var(--fc-brand-gradient-to) / 0.12) 100%)",
			},
			transitionTimingFunction: {
				"out-back": "cubic-bezier(0.16, 1, 0.3, 1)",
			},
			keyframes: {
				"fc-fade-in": {
					"0%": { opacity: "0" },
					"100%": { opacity: "1" },
				},
				"fc-rise-in": {
					"0%": { opacity: "0", transform: "translateY(8px)" },
					"100%": { opacity: "1", transform: "translateY(0)" },
				},
			},
			animation: {
				"fc-fade-in": "fc-fade-in 0.5s ease-out",
				"fc-rise-in": "fc-rise-in 0.5s cubic-bezier(0.16, 1, 0.3, 1)",
			},
		},
	},
	plugins: [],
};
