# Set up WooCommerce

WooCommerce remains authoritative for products, prices, stock, tax, shipping, customers,
orders, coupons, and payment gateways. Superfunky presents those rules; it does not
replace them with frontend-only totals.

## 1. Complete the WooCommerce setup

Use the WooCommerce onboarding wizard, then review:

1. **WooCommerce > Settings > General**
   - store address and selling locations;
   - base currency;
   - tax and coupon settings.
2. **Products**
   - units, inventory behavior, reviews, and downloadable-product rules.
3. **Tax**
   - whether prices include tax;
   - standard and reduced rates;
   - display rules for shop and checkout.
4. **Shipping**
   - zones, methods, classes, costs, and free-shipping conditions.
5. **Payments**
   - gateway activation, credentials, webhooks, test mode, and capture behavior.
6. **Accounts & Privacy**
   - guest checkout;
   - account creation;
   - privacy and retention settings.
7. **Emails**
   - sender identity and transactional templates.

Save these rules in WooCommerce before adjusting their presentation in the Superfunky
Control Center.

## 2. Verify system pages

Confirm that WooCommerce has assigned its Shop, Cart, Checkout, My account, Terms, and
Privacy pages where applicable. Superfunky renders its own headless interfaces, but
WooCommerce and extensions still use those assignments for settings, callbacks, emails,
and compatibility.

Do not delete the assigned pages merely because customers normally see React routes.

## 3. Prepare products

For each launch product, check:

- published status and catalogue visibility;
- regular and sale price;
- tax status and class;
- stock status and inventory quantity;
- shipping weight, dimensions, and class;
- featured image and gallery;
- categories, tags, attributes, and variations;
- SKU;
- virtual/downloadable settings and protected files;
- translated product links when multilingual commerce is enabled.

Create at least one simple product first. Add variable, virtual, downloadable, or
marketplace products after the base catalogue, cart, and checkout pass.

## 4. Configure payments

Install and configure payment gateways in WooCommerce. Use sandbox/test credentials until
the complete order flow passes.

For Stripe:

1. Install the supported WooCommerce Stripe gateway.
2. Configure secret credentials and webhooks only in the gateway's server-side settings.
3. Put only the Stripe publishable key (`pk_...`) in
   `VITE_STRIPE_PUBLISHABLE_KEY` when the frontend integration requires it.
4. Test successful, failed, and authentication-required payments.
5. Confirm WooCommerce creates the order once and records the correct payment status.

Never place a Stripe secret key (`sk_...`) or webhook secret in the storefront repository
or a `VITE_*` value.

Other gateways must support the chosen headless checkout flow. Test redirects, callbacks,
saved methods, refunds, and status transitions rather than assuming compatibility from
their classic WooCommerce checkout support.

## 5. Verify cart API access

The storefront uses the WooCommerce Store API for cart and checkout state. From the final
storefront origin, verify that:

- `/wp-json/wc/store/v1/cart` responds;
- preflight requests allow the storefront origin;
- `Cart-Token` and `Nonce` are accepted request headers;
- `Cart-Token` is exposed as a response header;
- no cache serves one customer's cart to another.

The Superfunky theme exposes `Cart-Token` and `Nonce` on Store API responses. The web
server, CDN, security plugin, or reverse proxy must not remove them.

Exclude cart, checkout, account, authentication, and Store API responses from public
full-page caching.

## 6. Align storefront settings

After WooCommerce is correct, use the Superfunky Control Center for presentation:

- visible stock badges and reviews;
- products per page;
- checkout headings, trust text, consent copy, and success wording;
- enabled display currencies and conversion behavior;
- shipping labels or thresholds;
- order-number prefix and alternative-payment presentation.

Some of these controls require Superfunky Pro. Locked fields are not applied until Pro is
active.

WooCommerce remains authoritative for chargeable totals. Storefront labels or display
conversions must never override the amount validated and charged by the backend.

## 7. Test the complete order lifecycle

Run at least one test for each enabled product and payment type:

1. browse and search the catalogue;
2. add, update, and remove cart items;
3. apply valid and invalid coupons;
4. calculate tax and shipping for representative addresses;
5. complete guest checkout when enabled;
6. complete account checkout when enabled;
7. verify payment, order status, stock reduction, and emails;
8. open the order in the customer account;
9. test cancellation, refund, and downloadable access where applicable.

Repeat critical tests on mobile and after a hard refresh to verify cart persistence.

Next: [connect the frontend](frontend.md).
