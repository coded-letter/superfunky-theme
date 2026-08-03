# Multilingual stores

WordPress and Polylang own translated content. Superfunky exposes configured languages,
translation relationships, language-aware routes, and optional Pro community language
behaviour to the headless storefront.

## Required plugins

Install one plugin for each role:

1. **Polylang** for WordPress languages and content relationships.
2. One compatible **WPGraphQL Polylang** integration.
3. **Polylang for WooCommerce** when products, product taxonomies, cart, checkout, and
   commerce emails are translated.

Do not activate two GraphQL Polylang bridges. Duplicate language types or fields can make
GraphQL schema registration fail.

## 1. Create the language model

1. Add every production language in Polylang.
2. Select the default language.
3. Prefer stable two-letter slugs such as `en`, `pl`, or `de` for the current storefront
   route and prerender conventions.
4. Decide whether the default language uses a prefixed URL.
5. In **Appearance > FunkyCommerce > Multilingual**, set the Free
   **Default publishing language**.

If the Control Center language is empty or unavailable, Superfunky falls back to the
Polylang default, then the first configured language, then the WordPress locale.

## 2. Translate content and navigation

Create and link translations for:

- homepage and every special storefront page;
- ordinary pages and posts;
- categories, tags, and media metadata;
- Header, Mobile, and Footer menus;
- legal, privacy, cookie, checkout, account, and email-preference pages.

Translation is not automatic. A language appearing in the switcher does not guarantee
that every destination has a translated record.

## 3. Translate WooCommerce

With Polylang for WooCommerce:

- translate products and product categories/tags;
- keep variations, stock, prices, tax classes, and downloadable files synchronised as
  required by the plugin;
- translate cart, checkout, account, order-success, terms, and privacy pages;
- test coupons, taxes, shipping, payment labels, and transactional emails per language.

When the WooCommerce multilingual GraphQL bridge is absent, Superfunky registers limited
product language/translation fallbacks. Those fallbacks do not replace the full
WooCommerce translation workflow.

## 4. Storefront routing

The storefront reads languages from `funkycommerceStorefrontConfig`, maps the Polylang
slug to the public route, and maps the backend language code to GraphQL queries.

- language home routes use `/{language-slug}`;
- the selected preference is retained in browser storage;
- the document `lang` attribute follows the selection;
- translated special-page paths are resolved from WordPress;
- search, navigation, posts, products, and UI strings request the selected backend
  language where supported.

Set `VITE_DEFAULT_LANGUAGE` for production generation when the deployment default is not
`en`. Rebuild after adding languages or changing translated routes, then inspect the
generated route and sitemap output.

## Community and comments

The Free setting chooses the default publishing language. Superfunky Pro adds:

- multilingual community posts and community tags;
- comment language inherited from the parent content.

When community multilingual support is enabled, existing community posts and terms
without a language are assigned the selected default during the one-time backfill.
Administrators can see and filter comment languages in WordPress.

Disable inheritance only when the project has a deliberate workflow for assigning a
different language to individual comments.

## Translation acceptance checklist

- GraphQL introspection succeeds with only one Polylang bridge.
- Every configured language appears once in the switcher.
- Direct links and refreshes work on language-prefixed routes.
- The logo, menus, search, and special pages remain in the selected language.
- Products and translated taxonomies resolve to the correct records.
- Cart, checkout, tax, shipping, payment, order, and email content use the expected
  locale.
- Canonical, sitemap, and indexable routes point to the public storefront.
- Missing translations have an intentional fallback or not-found behaviour.
- Newsletter/form records store the selected language.
- Community posts and comments receive the intended language.

Next: [configure forms and autoresponders](forms-autoresponders.md).
