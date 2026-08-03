# GTM and GA4

Superfunky Pro can supply a Google Tag Manager container ID to the storefront production
build. Configure Google Analytics 4 inside that GTM container rather than installing a
second GA script.

## Prerequisites

- a GA4 property and Web data stream for the public storefront domain;
- a web GTM container;
- publish access to the container;
- an approved analytics and cookie-consent policy;
- a production storefront build connected to
  `funkycommerceStaticGenerationConfig`.

The Control Center accepts a GTM container ID in the form `GTM-XXXXXXX`. A GA4
measurement ID such as `G-XXXXXXXXXX` belongs inside GTM and must not be entered in that
field.

## 1. Configure GA4 in GTM

1. In GA4, create or select the Web data stream for the final HTTPS storefront URL.
2. Copy its measurement ID.
3. In GTM, create a **Google tag** using that measurement ID.
4. Select the appropriate page-view trigger.
5. Use GTM Preview against a staging storefront.
6. Publish the container only after consent and duplicate-tag tests pass.

Do not install the same GA4 measurement ID through GTM, a WordPress analytics plugin,
custom head scripts, and host-level injection at the same time.

## 2. Connect the container

Open **Appearance > FunkyCommerce > Scripts & Tracking**, enter the **GTM container ID**,
and save.

The theme exposes the allowlisted value through the public static-generation
configuration. During `pnpm build`, the released storefront:

- validates the `GTM-` ID;
- inserts the GTM loader before `</head>`;
- inserts the standard no-script iframe immediately inside `<body>`;
- writes the result into every generated route.

This is a build-time integration. Trigger and deploy a new production build after
changing the container ID. Saving WordPress alone does not modify already-deployed HTML.

## 3. Consent before tracking

The current storefront consent interface records category preferences, but it does not
by itself prevent the build-time GTM loader from executing. The Pro
**Cookies Consent v2 configuration** is stored configuration and is not currently part of
the public static-generation payload.

Before production tracking, implement and verify a consent gate appropriate to the
store's jurisdiction. Common approaches include:

- default-denied Google Consent Mode set before GTM loads, then updated from the consent
  manager;
- loading GTM only after tracking consent;
- a reviewed consent-management platform integrated with GTM.

The current build inserts the GTM loader before the configured **Head scripts**.
Therefore, that field alone cannot establish a default consent state before GTM starts;
the generation template or a reviewed consent integration must own the ordering.

Do not describe the storefront banner as legally sufficient until the deployed tags are
actually blocked or placed in the required consent state.

## 4. SPA page views and commerce events

The released storefront injects the GTM container but does not emit a complete GA4
ecommerce `dataLayer` contract. Client-side route changes, product impressions, cart
actions, checkout stages, and purchases must be tested and, where required, wired as
explicit events.

At minimum, define and validate:

- page views for initial loads and client-side navigation;
- `view_item`, `view_item_list`, and `select_item`;
- `add_to_cart`, `remove_from_cart`, and `view_cart`;
- `begin_checkout`, shipping/payment selection, and `purchase`;
- newsletter or form conversions only after the submission succeeds.

Use WooCommerce order IDs as transaction IDs and prevent a confirmation-page refresh
from recording a second purchase.

## Custom scripts

The Pro **Head scripts** and **Body scripts** fields are also inserted during production
generation. They are public executable code, not a secret store.

- Prefer a reviewed GTM tag over duplicated snippets.
- Never place API secret keys or private tokens in these fields.
- Rebuild after every change.
- Re-test Content Security Policy when adding a script origin.
- Remove unused scripts rather than leaving disabled provider code in the page.

## Verification

1. Build the production storefront.
2. Inspect generated HTML for one GTM head loader and one no-script iframe.
3. Confirm the container ID matches the intended environment.
4. Use GTM Preview to inspect initial and client-side page transitions.
5. Use GA4 DebugView and Realtime reports.
6. Reject tracking consent and verify analytics storage/network requests follow policy.
7. Accept tracking and verify one event with correct page, currency, value, item, and
   transaction data.
8. Test CSP, ad blockers, slow connections, and a direct deep link.

Next: [configure a multilingual store](multilingual.md).
