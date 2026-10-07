import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { Engine } from 'php-parser';

const checkoutContext = await readFile(
  new URL('../inc/checkout-context.php', import.meta.url),
  'utf8',
);
const fallback = checkoutContext.match(
  /function funkycommerce_process_verified_blik_charge\([\s\S]*?\n}\n/,
)?.[0];

test('BLIK fallback does not let a Woo Stripe payment lock block verified completion', () => {
  assert.ok(fallback, 'BLIK charge fallback is present');
  assert.doesNotMatch(fallback, /get_order_existing_payment_lock|is_order_payment_locked/);
  assert.match(fallback, /'blik' !== \( \$charge->payment_method_details->type \?\? '' \)/);
});

test('BLIK fallback accepts Stripe charge identifiers without assuming a ch_ prefix', () => {
  assert.ok(fallback, 'BLIK charge fallback is present');
  assert.match(fallback, /\$charge_id\s*=\s*is_object\(\s*\$charge\s*\)\s*\?\s*\(string\)\s*\( \$charge->id \?\? '' \)/);
  assert.match(fallback, /'' === \$charge_id/);
  assert.match(fallback, /'charge' !== \( \$charge->object \?\? '' \)/);
  assert.match(fallback, /'succeeded' !== \( \$charge->status \?\? '' \)/);
  assert.match(fallback, /true !== \( \$charge->captured \?\? false \)/);
  assert.match(fallback, /true !== \( \$charge->paid \?\? false \)/);
  assert.match(fallback, /'succeeded' !== \( \$intent->status \?\? '' \)/);
  assert.match(fallback, /\$charge_amount !== \$intent_amount/);
  assert.match(fallback, /\$captured_amount !== \$intent_amount/);
  assert.match(fallback, /strtolower\( \(string\) \( \$charge->currency \?\? '' \) \) !== strtolower\( \(string\) \( \$intent->currency \?\? '' \) \)/);
  assert.match(fallback, /'manual_review' === \( \$charge->outcome->type \?\? '' \)/);
  assert.match(fallback, /hash_equals\(\s*\(string\) \( \$intent->id \?\? '' \),\s*\(string\) \( \$charge->payment_intent \?\? '' \)\s*\)/);
  assert.doesNotMatch(fallback, /ch_/);
});

test('BLIK fallback completes verified captured charges when Stripe gateway handler no-ops', () => {
  assert.ok(fallback, 'BLIK charge fallback is present');
  assert.match(fallback, /\$gateway->process_response\(\s*\$charge,\s*\$order\s*\)/);
  assert.match(fallback, /catch \( \\Throwable \$error \)/);
  assert.match(fallback, /WC_Stripe_Helper::get_order_by_charge_id/);
  assert.match(fallback, /catch \( \\Throwable \$sync_error \)/);
  assert.match(fallback, /\$order->payment_complete\(\s*\$charge_id\s*\)/);
  assert.match(fallback, /\$order->is_paid\(\)/);
});

test('BLIK reconciliation reuses WooCommerce Stripe native webhook processing', () => {
  assert.match(checkoutContext, /WC_Stripe_Webhook_Handler/);
  assert.match(checkoutContext, /process_payment_intent/);
  assert.match(checkoutContext, /WC_Stripe_API::retrieve/);
  assert.doesNotMatch(checkoutContext, /FUNKYCOMMERCE_STRIPE_BLIK_WEBHOOK_SECRET|\/stripe\/blik-webhook/);
});

test('new BLIK retries use Stripe webhooks instead of Action Scheduler', () => {
  assert.doesNotMatch(checkoutContext, /as_schedule_single_action|wp_schedule_single_event|funkycommerce_schedule_blik_reconciliation_retry/);
  assert.match(checkoutContext, /function funkycommerce_reconcile_blik_order_retry/);
  assert.match(checkoutContext, /add_action\(\s*'funkycommerce_reconcile_blik_order_retry'/);
  assert.match(checkoutContext, /\$attempt > 10/);
  assert.match(checkoutContext, /in_array\( \$intent_status, array\( 'succeeded', 'processing' \), true \)/);
  const legacyRetry = checkoutContext.match(
    /function funkycommerce_reconcile_blik_order_retry\([\s\S]*?\n}\n/,
  )?.[0];
  assert.ok(legacyRetry, 'legacy queued retry callback remains available');
  assert.doesNotMatch(legacyRetry, /schedule_single_action|schedule_single_event/);
});

test('the legacy BLIK retry callback remains registered for already-queued actions', async () => {
  const parser = new Engine({ parser: { suppressErrors: false } });
  const source = await readFile(
    new URL('../inc/checkout-context.php', import.meta.url),
    'utf8',
  );
  const ast = parser.parseCode(source);
  const topLevelFunctions = ast.children
    .filter((node) => node.kind === 'function')
    .map((node) => node.name?.name);

  assert.ok(topLevelFunctions.includes('funkycommerce_reconcile_blik_order_retry'));
});
