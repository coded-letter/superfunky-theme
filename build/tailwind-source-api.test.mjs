import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";
import { Engine } from "php-parser";

const source = readFileSync(new URL("../inc/tailwind-source-api.php", import.meta.url), "utf8");
const functions = readFileSync(new URL("../functions.php", import.meta.url), "utf8");
const parser = new Engine({ parser: { suppressErrors: false } });

test("read-only Tailwind source API parses and loads before build webhooks", () => {
  assert.doesNotThrow(() => parser.parseCode(source));
  assert.match(
    functions,
    /require_once .*\/inc\/tailwind-source-api\.php';\s*require_once .*\/inc\/build-webhooks\.php'/,
  );
  assert.match(functions, /FUNKYCOMMERCE_HEADLESS_VERSION', '1\.2\.54'/);
});

test("source API is signed, bounded, direct, and read-only", () => {
  assert.match(source, /funkycommerce_artifact_signing_secret\(\)/);
  assert.match(source, /hash_hmac\( 'sha256', \$timestamp \. '\.' \. \$event_id \. '\.' \. \$body, \$secret \)/);
  assert.match(source, /FUNKYCOMMERCE_TAILWIND_SOURCE_INVENTORY_LIMIT\s*=\s*500/);
  assert.match(source, /FUNKYCOMMERCE_TAILWIND_SOURCE_BATCH_LIMIT\s*=\s*10/);
  assert.match(source, /if \( ! \$types \) \{\s+return new WP_Error\( 'tailwind_source_types'/);
  assert.match(source, /FUNKYCOMMERCE_TAILWIND_SOURCE_ITEM_BYTES\s*=\s*524288/);
  assert.match(source, /FUNKYCOMMERCE_TAILWIND_SOURCE_RESPONSE_BYTES\s*=\s*1048576/);
  assert.match(source, /array_diff\( \$post_types, array\( 'attachment' \) \)/);
  assert.match(source, /FROM \{\$wpdb->posts\}/);
  assert.match(source, /ORDER BY ID ASC/);
  assert.match(source, /Cache-Control', 'private, no-store/);
});

test("source API performs no mutation, extraction, rendering, or scheduling", () => {
  assert.doesNotMatch(source, /add_action\( '(?:save_post|transition_post_status|before_delete_post)'/);
  assert.doesNotMatch(source, /update_(?:option|post_meta)|add_option|delete_(?:option|post_meta)/);
  assert.doesNotMatch(source, /set_transient|wp_schedule|wp_remote_|fopen|fwrite|fsync|rename\(/);
  assert.doesNotMatch(source, /preg_match_all|parse_blocks|render_block|do_shortcode|apply_filters\( 'the_content'/);
});

test("source endpoints expose inventory and explicit ten-record batches only", () => {
  assert.match(source, /'\/inventory'/);
  assert.match(source, /'\/sources'/);
  assert.match(source, /'ids'/);
  assert.match(source, /count\( \$body\['ids'\] \)/);
  assert.match(source, /A requested Tailwind source changed during the build/);
});
