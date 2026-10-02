import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { test } from "node:test";

const stylesheet = readFileSync(new URL("../style.css", import.meta.url), "utf8");
const schema = readFileSync(new URL("../inc/control-center-schema.php", import.meta.url), "utf8");
const controlCenter = readFileSync(new URL("../inc/control-center.php", import.meta.url), "utf8");

test("public theme defaults use Superfunky branding without renaming internal identifiers", () => {
  assert.match(stylesheet, /^Description:.*Superfunky/m);
  assert.doesNotMatch(stylesheet, /^Description:.*FunkyCommerce/m);
  assert.match(schema, /Superfunky WordPress theme/);
  assert.match(controlCenter, /Superfunky WordPress theme/);
  assert.match(controlCenter, /\$legacy_theme_credit === \$configured_theme_credit/);
  assert.match(controlCenter, /funkycommerce-headless/);
});

test("new installations default public order numbers to the order prefix", () => {
  assert.match(schema, /'order_prefix'\s*=>.*'default'\s*=>\s*'order'/);
});
