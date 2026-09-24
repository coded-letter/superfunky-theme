import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";
import { Engine } from "php-parser";

const source = (name) => readFileSync(new URL(`../${name}`, import.meta.url), "utf8");
const cleanup = source("inc/tailwind-manifest-cleanup.php");
const functions = source("functions.php");
const frontendTheme = source("inc/frontend-theme.php");
const style = source("style.css");
const webhooks = source("inc/build-webhooks.php");
const parser = new Engine({ parser: { suppressErrors: false } });

test("theme 1.2.54 retains the one-time Tailwind worker cleanup", () => {
  assert.doesNotThrow(() => parser.parseCode(cleanup));
  assert.match(functions, /FUNKYCOMMERCE_HEADLESS_VERSION', '1\.2\.54'/);
  assert.match(functions, /require_once .*\/inc\/tailwind-manifest-cleanup\.php'/);
  assert.doesNotMatch(functions, /tailwind-class-manifest\.php/);
});

test("content mutations and requests perform no CMS Tailwind work", () => {
  assert.doesNotMatch(cleanup, /add_action\( 'save_post'/);
  assert.doesNotMatch(cleanup, /add_action\( 'transition_post_status'/);
  assert.doesNotMatch(cleanup, /add_action\( '(?:before_)?delete_post'/);
  assert.doesNotMatch(cleanup, /register_rest_route/);
  assert.doesNotMatch(cleanup, /wp_schedule_(?:single_)?event/);
  assert.doesNotMatch(cleanup, /update_post_meta|delete_post_meta|get_post_meta/);
  assert.doesNotMatch(cleanup, /preg_match_all|wp_upload_dir|fopen|fwrite|fsync|rename\(/);
});

test("one-time cleanup removes every legacy Tailwind worker and aggregate option", () => {
  for (const hook of [
    "funkycommerce_tailwind_manifest_backfill",
    "funkycommerce_tailwind_manifest_aggregate",
    "funkycommerce_tailwind_manifest_retry_post",
  ]) {
    assert.match(cleanup, new RegExp(hook));
  }
  assert.match(cleanup, /wp_clear_scheduled_hook\( \$hook \)/);
  assert.match(cleanup, /funkycommerce_tailwind_manifest_v2/);
  assert.match(cleanup, /funkycommerce_tailwind_aggregate_v2/);
  assert.match(cleanup, /FUNKYCOMMERCE_TAILWIND_CLEANUP_VERSION\s*=\s*'1\.2\.52'/);
});

test("storefront build webhooks no longer depend on a CMS Tailwind manifest", () => {
  assert.doesNotMatch(webhooks, /tailwindManifest|tailwind_manifest|funkycommerce_tailwind/);
  assert.match(webhooks, /add_action\( 'save_post', 'funkycommerce_schedule_post_build', 20, 3 \)/);
});

test("the editor keeps the corrected single compiled stylesheet path", () => {
  assert.match(functions, /add_editor_style\( 'assets\/dist\/theme\.css' \)/);
  assert.doesNotMatch(functions, /add_editor_style\( 'style\.css' \)/);
  assert.doesNotMatch(frontendTheme, /enqueue_block_editor_assets|funkycommerce-frontend-theme-editor/);
  assert.doesNotMatch(style, /@import\s+url\(["']?assets\/dist\/theme\.css/);
});
