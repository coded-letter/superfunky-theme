#!/usr/bin/env node
/**
 * Splices the compiled assets/dist/theme.css and assets/dist/theme.js files
 * into parts/header.html and parts/footer.html between marker comments, so
 * the native WordPress shell renders fully styled/interactive even before
 * (or without) the optional inc/frontend-theme.php enqueue is wired up by
 * the parent theme's functions.php.
 *
 * Run automatically as part of `npm run build` (see package.json), after
 * build:css and build:js have produced assets/dist/theme.css and
 * assets/dist/theme.js.
 */
import { readFileSync, writeFileSync, existsSync } from "node:fs";
import { fileURLToPath } from "node:url";
import path from "node:path";

const themeRoot = path.dirname(path.dirname(fileURLToPath(import.meta.url)));

const CSS_PATH = path.join(themeRoot, "assets/dist/theme.css");
const JS_PATH = path.join(themeRoot, "assets/dist/theme.js");
const HEADER_PATH = path.join(themeRoot, "parts/header.html");
const FOOTER_PATH = path.join(themeRoot, "parts/footer.html");

/**
 * @param {string} filePath
 * @param {string} startMarker
 * @param {string} endMarker
 * @param {string} injected
 */
function spliceBetweenMarkers(filePath, startMarker, endMarker, injected) {
	const original = readFileSync(filePath, "utf8");
	const startIndex = original.indexOf(startMarker);
	const endIndex = original.indexOf(endMarker);

	if (startIndex === -1 || endIndex === -1 || endIndex < startIndex) {
		throw new Error(
			`Could not find markers "${startMarker}" / "${endMarker}" in ${filePath}`
		);
	}

	const before = original.slice(0, startIndex + startMarker.length);
	const after = original.slice(endIndex);
	const next = `${before}\n${injected}\n${after}`;

	writeFileSync(filePath, next, "utf8");
}

function run() {
	if (!existsSync(CSS_PATH)) {
		console.warn(
			`[sync-template-assets] Skipping CSS inline: ${CSS_PATH} not found. Run "npm run build:css" first.`
		);
	} else {
		const css = readFileSync(CSS_PATH, "utf8").trim();
		spliceBetweenMarkers(
			HEADER_PATH,
			"<!-- funkycommerce:theme-css:start -->",
			"<!-- funkycommerce:theme-css:end -->",
			`<style id="funkycommerce-theme-inline-css">${css}</style>`
		);
		console.log(`[sync-template-assets] Inlined theme.css into ${path.relative(themeRoot, HEADER_PATH)}`);
	}

	if (!existsSync(JS_PATH)) {
		console.warn(
			`[sync-template-assets] Skipping JS inline: ${JS_PATH} not found. Run "npm run build:js" first.`
		);
	} else {
		const js = readFileSync(JS_PATH, "utf8").trim();
		spliceBetweenMarkers(
			FOOTER_PATH,
			"<!-- funkycommerce:theme-js:start -->",
			"<!-- funkycommerce:theme-js:end -->",
			`<script id="funkycommerce-theme-inline-js">${js}</script>`
		);
		console.log(`[sync-template-assets] Inlined theme.js into ${path.relative(themeRoot, FOOTER_PATH)}`);
	}
}

run();
