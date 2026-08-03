# Set up WordPress

This guide prepares WordPress as the Superfunky content and configuration backend.

## 1. Prepare the site

1. Install WordPress 6.7 or newer on an HTTPS hostname.
2. Use PHP 7.4 or newer; use a currently supported PHP 8.x release for a new production
   environment.
3. In **Settings > Permalinks**, select any non-plain structure and save.
4. Confirm that `https://backend.example.com/wp-json/` returns the WordPress REST index.
5. Configure reliable scheduled tasks. For production, a system cron that calls
   WordPress cron is preferable to traffic-dependent scheduling.
6. Take a backup before converting an existing store.

Use a dedicated backend hostname when possible. Keep it private from normal navigation,
but do not block the APIs, media, callbacks, or authenticated admin access the storefront
needs.

## 2. Install the theme

Download the latest release from
[`coded-letter/superfunky-theme`](https://github.com/coded-letter/superfunky-theme).

Install it by either:

- uploading the release zip in **Appearance > Themes > Add New > Upload Theme**; or
- cloning/copying the repository into `wp-content/themes/`.

Activate **FunkyCommerce Headless**. The package retains that legacy technical name for
WordPress upgrade compatibility; the customer-facing product is Superfunky.

The theme is dependency-safe and can activate before WooCommerce or GraphQL plugins.

## 3. Install the API stack

Install only one plugin for each integration role.

### Required for the Superfunky storefront

1. **WPGraphQL**
2. **WooCommerce**
3. **WPGraphQL for WooCommerce (WooGraphQL)**

### Required by enabled features

- **Headless Login for WPGraphQL** for login, registration, customer accounts, and other
  authenticated flows.
- **Polylang** plus one compatible WPGraphQL Polylang integration for multilingual
  content.
- **Polylang for WooCommerce** when WooCommerce products and taxonomies are translated.
- Your selected SEO, payment, SMTP, anti-spam, and provider plugins.

Do not activate two plugins that register the same Polylang GraphQL types. Duplicate
`Language`, language-enum, or root-query registrations can make schema introspection
return HTTP 500.

## 4. Verify GraphQL

Open the GraphQL IDE supplied by WPGraphQL or send this query to
`https://backend.example.com/graphql`:

```graphql
query SuperfunkyReadiness {
  generalSettings {
    title
    url
  }
  products(first: 1) {
    nodes {
      id
      name
    }
  }
}
```

Before frontend launch, the schema must also contain:

- `RootQuery.funkycommerceSpecialPage`
- `RootQuery.funkycommerceStorefrontConfig`
- `RootQuery.funkycommerceThemeStyles`
- `RootQuery.funkycommerceUiStrings`
- `Page.funkycommerceSpecialPageKey`
- `Page.headlessShortcodes`

Account, order, viewer, and static-generation fields are required only by the features
that use them.

If introspection fails, check PHP logs and deactivate conflicting GraphQL extensions
before changing the frontend.

## 5. Configure the Control Center

Open **Appearance > FunkyCommerce** in the current theme release. This legacy admin label
will be renamed to Superfunky in a future compatibility-safe release.

For initial connection:

1. Set the public **Frontend URL** to the draft storefront URL.
2. Configure the store identity and required Free controls.
3. Save, then query `funkycommerceStorefrontConfig` to confirm the values are exposed.
4. If Superfunky Pro is licensed, activate the Pro companion and confirm the required
   controls unlock before configuring them.

Do not paste secrets into fields described as public frontend values. Provider secret
keys belong in the owning server-side plugin or approved secret store.

## 6. Create content and navigation

- Create the homepage and other WordPress Pages used by the storefront.
- Create and assign Header, Mobile, and Footer menus under **Appearance > Menus**.
- Create posts, categories, tags, media, and authors needed for launch.
- Add supported shortcodes or Gutenberg content to storefront Pages only after the base
  GraphQL connection works.
- Publish at least one product before running production readiness checks.

## 7. Configure rebuilds

For a static or hybrid deployment:

1. Create a deploy hook in the frontend hosting provider.
2. In the Control Center, set the **Build webhook URL**.
3. Publish a harmless staging change and confirm one deployment starts.
4. Enable periodic rebuilds only when the selected plan and content model require them.

The theme debounces public content changes before calling the hook. Treat the hook URL as
a secret: do not publish it in documentation, frontend variables, or source control.

Build webhook and scheduled-rebuild controls are Pro features. Free self-hosted
deployments can trigger builds from their CI provider or build manually.

## 8. Final WordPress checks

- REST and GraphQL respond over HTTPS.
- The GraphQL schema has no duplicate registrations.
- Media URLs are publicly reachable by the storefront and build system.
- WordPress Site URL and Home URL still point to the backend origin.
- The Control Center Frontend URL points to the current draft storefront.
- Backups, updates, logs, cron, and administrator access are working.
- Security or caching rules do not block `/graphql`, `/wp-json/`, media, cron, or provider
  callbacks.

Next: [configure WooCommerce](woocommerce.md).
