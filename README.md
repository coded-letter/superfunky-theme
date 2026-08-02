# Superfunky Theme

Free, open-source (GPL-2.0) headless WordPress block theme with WPGraphQL +
WooGraphQL support for the [Superfunky](https://superfunky.pro) headless
WordPress/WooCommerce platform.

Pair this theme with [`superfunky-storefront`](https://github.com/coded-letter/superfunky-storefront)
(or any other WPGraphQL-compatible headless frontend).

## Status

**This repository is currently a scaffold only.** It contains no theme source code
yet. The current internal theme (`funkycommerce-headless`) is monolithic — free and
Pro/premium functionality are not yet separated — so nothing can be extracted safely
until that split happens. This repo exists so the public repository, licence, and CI
conventions are ready before that migration begins.  See
[`EXTRACTION_STATUS.md`](./EXTRACTION_STATUS.md) for the migration checklist.

## What this project is (and isn't)

- **Is**: a minimal, free, GPL-2.0 headless WordPress theme starter — the open-source
  "acquisition" edge of the Superfunky product family.
- **Isn't**: Superfunky Pro (the commercial theme upgrade) or the premium plugin bundle
  (Slack, Discord, Google Maps Locations, AI Shopping Assistant, Abandoned Carts).
  Those remain closed-source in the private monorepo and are never extracted here.

## Licence

GPL-2.0 — see [`LICENSE`](./LICENSE) (required for WordPress.org theme distribution
compatibility). Contributions are welcome once the initial code extraction lands.

## Related repositories

- [`coded-letter/superfunky-storefront`](https://github.com/coded-letter/superfunky-storefront) —
  companion free headless storefront (MIT).
- `coded-letter/superfunky-woo` (private) — the source-of-truth integration monorepo,
  including the commercial Pro theme and premium plugin bundle.
