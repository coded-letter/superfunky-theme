#!/usr/bin/env node
/**
 * Lightweight PHP syntax linter for the theme's own PHP files, using the
 * php-parser npm package as a stand-in for `php -l` (not available in every
 * environment this theme is built in).
 */
import { readFileSync, readdirSync } from "node:fs";
import { fileURLToPath } from "node:url";
import path from "node:path";
import { Engine } from "php-parser";

const themeRoot = path.dirname(path.dirname(fileURLToPath(import.meta.url)));

function phpFiles(directory, prefix = "") {
	return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
		const relativePath = path.join(prefix, entry.name);
		const absolutePath = path.join(directory, entry.name);
		if (entry.isDirectory()) {
			return phpFiles(absolutePath, relativePath);
		}
		return entry.isFile() && entry.name.endsWith(".php") ? [relativePath] : [];
	});
}

const FILES_TO_LINT = [
	"functions.php",
	...phpFiles(path.join(themeRoot, "inc"), "inc"),
];

const parser = new Engine({
	parser: { extractDoc: true, suppressErrors: false },
	ast: { withPositions: true },
});

let hasErrors = false;

for (const relativePath of FILES_TO_LINT) {
	const filePath = path.join(themeRoot, relativePath);
	try {
		const source = readFileSync(filePath, "utf8");
		parser.parseCode(source, filePath);
		console.log(`[lint:php] OK  ${relativePath}`);
	} catch (error) {
		hasErrors = true;
		console.error(`[lint:php] FAIL ${relativePath}`);
		console.error(`  ${error.message}`);
	}
}

if (hasErrors) {
	process.exitCode = 1;
} else {
	console.log(`[lint:php] All ${FILES_TO_LINT.length} file(s) parsed without syntax errors.`);
}
