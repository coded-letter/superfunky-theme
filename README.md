# Superfunky Theme

Free, open-source (GPL-2.0) headless WordPress block theme with WPGraphQL +
WooGraphQL support for the [Superfunky](https://superfunky.pro) headless
WordPress/WooCommerce platform.

Pair this theme with [`superfunky-storefront`](https://github.com/coded-letter/superfunky-storefront)
(or any other WPGraphQL-compatible headless frontend).

## Features

- **170 Control Center settings** — single admin page for all headless configuration
- **Free tier** (71 fields) — branding, typography, favicons, dark mode, custom CSS, newsletter, essential security, SEO, robots.txt, llms.txt, sitemap, GraphQL storefront config
- **Pro tier** (98 fields) — multi-currency, crypto payments, shipping, community/marketplace, build webhooks, GTM/scripts, advanced security (HSTS, CSP, lockout), email providers, multi-input forms, push notifications, PWA, AI discovery files
- **Dependency-safe** — activates without WooCommerce, Polylang, WPGraphQL, or WooGraphQL
- **Full Site Editing** — block templates for posts, author archives, and community posts
- **GraphQL-first** — `storefrontConfig`, special pages, community posts, marketplace products, customer accounts

## Requirements

- WordPress 6.7+
- PHP 7.4+
- [WPGraphQL](https://wpgraphql.com/) (recommended)
- [WooCommerce](https://woocommerce.com/) + [WooGraphQL](https://woographql.com/) (optional, for commerce features)

## Installation

1. Download or clone this repository into `wp-content/themes/`
2. Activate the theme in WordPress admin
3. Navigate to **Appearance → FunkyCommerce** to configure

## Free vs Pro

All 170 settings are visible in the Control Center. Free-tier fields work immediately.
Pro-tier fields display a lock badge and upgrade link until the
[FunkyCommerce Pro](https://codedletter.com/products) companion plugin is activated.

Pro settings are preserved in the database even when Pro is inactive — upgrading
instantly applies previously saved values.

## Licence

GPL-2.0 — see [`LICENSE`](./LICENSE).

## Related repositories

- [`coded-letter/superfunky-storefront`](https://github.com/coded-letter/superfunky-storefront) —
  companion free headless storefront (MIT)
