import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { spawnSync } from "node:child_process";
import { fileURLToPath } from "node:url";
import test from "node:test";
import { Engine } from "php-parser";

const source = (name) => readFileSync(new URL(`../${name}`, import.meta.url), "utf8");
const settings = source("inc/archive-settings.php");
const functions = source("functions.php");
const nativeGrid = source("inc/native-shortcodes.php");
const parser = new Engine({ parser: { suppressErrors: false } });

test("native PHP resolves settings, filter precedence, GraphQL, and option invalidation", (context) => {
  const result = spawnSync(process.env.PHP_BINARY || "php", [
    fileURLToPath(new URL("archive-settings.test.php", import.meta.url)),
  ], { encoding: "utf8" });
  if (result.error?.code === "ENOENT" && !process.env.PHP_BINARY) {
    context.skip("PHP is unavailable; set PHP_BINARY to run native behavior assertions.");
    return;
  }
  assert.ifError(result.error);
  assert.equal(result.status, 0, `${result.stdout}\n${result.stderr}`);
  assert.match(result.stdout, /Archive settings behavior passed/);
});

test("archive settings parse and load independently of native/headless rendering mode", () => {
  assert.doesNotThrow(() => parser.parseCode(settings));
  assert.match(functions, /require_once .*\/inc\/archive-settings\.php/);
  assert.doesNotMatch(settings, /funkycommerce_is_headless_mode/);
  assert.doesNotMatch(source("inc/native-woocommerce.php"), /function funkycommerce_native_products_per_page/);
});

test("GraphQL exposes independent WordPress and effective WooCommerce sizes including unlimited", () => {
  assert.match(settings, /get_option\( 'posts_per_page', 10 \)/);
  assert.match(settings, /apply_filters\( 'loop_shop_per_page', wc_get_default_products_per_row\(\) \* wc_get_default_product_rows_per_page\(\) \)/);
  assert.match(settings, /-1 === \$count \|\| \$count > 0/);
  assert.match(settings, /'funkycommerceArchiveSettings'/);
  assert.match(settings, /'resolve' => 'funkycommerce_archive_settings'/);
  for (const field of ["postsPerPage", "productsPerPage"]) {
    assert.match(settings, new RegExp(`'${field}'\\s*=> array\\( 'type' => array\\( 'non_null' => 'Int' \\)`));
  }
});

test("saved positive overrides remain shared while an absent override inherits WooCommerce", () => {
  assert.match(settings, /\$settings\['products_per_page'\] \?\? 0/);
  assert.match(settings, /add_filter\( 'loop_shop_per_page', 'funkycommerce_native_products_per_page', 20 \)/);
  const field = source("inc/control-center-schema.php").split("'products_per_page'  =>")[1].split("\n")[0];
  assert.match(field, /'default' => '0'/);
  assert.match(field, /'min' => '0'/);
});

test("reading and WooCommerce option changes invalidate prerender settings and schedule rebuilding", () => {
  for (const option of ["posts_per_page", "woocommerce_catalog_columns", "woocommerce_catalog_rows"]) {
    assert.ok(settings.includes(`'${option}'`));
  }
  for (const hook of ["updated_option", "added_option", "deleted_option"]) {
    assert.ok(settings.includes(`add_action( '${hook}', 'funkycommerce_collect_archive_setting_change', 30 )`));
  }
  assert.match(settings, /funkycommerce_schedule_content_build\(\)/);
  assert.match(settings, /array\( 'config:storefront' \)/);
  assert.match(source("inc/artifact-invalidation.php"), /function funkycommerce_collect_control_center_change[\s\S]*funkycommerce_schedule_content_build\(\)/);
});

test("native and headless grid markers inherit settings unless the editor specifies a positive size", () => {
  const grid = functions.split("'grid'             => array(")[1].split("'offset'")[0];
  assert.match(grid, /'page_size'\s*=> array\( 'default' => 0, 'type' => 'integer', 'min' => 0, 'max' => 48 \)/);
  assert.match(nativeGrid, /\$a\['page_size'\] > 0 \? \(int\) \$a\['page_size'\] : \$settings\[ 'product' === \$type \? 'productsPerPage' : 'postsPerPage' \]/);
  assert.match(nativeGrid, /'offset'\s*=> -1 === \$per_page \? 0/);
  assert.match(nativeGrid, /array_slice\( \$result\['ids'\], \$base_offset \)/);
  assert.match(nativeGrid, /-1 !== \$per_page \? \(int\) ceil/);
});
