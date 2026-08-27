=== Superfunky Headless ===
Contributors: coded-letter
Tags: headless, woocommerce, wpgraphql, full-site-editing
Requires at least: 6.7
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.9
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

This block theme is the WordPress control plane for the FunkyCommerce storefront.
By default it renders a minimal, headless-only shell because customer-facing output is
served by the separate headless application. When headless mode is disabled in the
Control Center, the theme also ships a complete native WordPress rendering path (see
"Native frontend theme" below) with accessible header/footer/navigation templates and
core front/home/singular/archive/search/404 routes styled to match the storefront.

== 1.2.9 highlights ==

* Removes the paid-product licence manager from the theme.
* Keeps public signed theme updates independent while licensed products use the optional Superfunky Licensing plugin.

== 1.2.8 highlights ==

* Supplies complete internal customer data to Stripe for virtual-only purchases.
* Keeps country-agnostic Store API validation separate from payment gateway data.

== 1.2.7 highlights ==

* Prevents virtual-only Store API checkouts from requiring geographic billing fields.
* Handles cached default and country locale requirements consistently across countries.

== 1.2.6 highlights ==

* Adds a Control Center option for horizontally scrollable empty-cart recommendations
  containing either the first or all featured WooCommerce products.
* Keeps empty-cart recommendations consistent across drawer and dropdown cart layouts.
* Preserves canonical product links and requires a concrete priced variation before
  adding variable products from empty-cart recommendations.

== 1.2.5 highlights ==

* Restores backend-featured WooCommerce products in the enabled empty-cart promotion.
* Keeps hero background media full-frame and centered on mobile layouts.
* Expands the editable storefront UI-string contract across customer-facing controls,
  filters, dialogs, downloads, account, community, wishlist, and reading-list surfaces.

== 1.2.4 highlights ==

* Keeps backend UI-string overrides isolated per language, with English fallback and
  explicit admin values taking precedence over bundled and Polylang translations.
* Improves dynamic storefront media loading by limiting eager requests and avoiding
  hidden adjacent community-card image preloads.

== 1.2.3 highlights ==

* Makes the community-members role attribute an exact comma-separated whitelist
  of registered WordPress role slugs or labels and excludes roleless profiles.

== 1.2.2 highlights ==

* Makes Stripe BLIK available when shoppers select PLN in the storefront, while keeping
  presentation backend-controlled and persisting BLIK orders with PLN-denominated totals.
* Proxies externally hosted digital files through WooCommerce's signed download handler,
  with per-order/file/IP rate limiting and headless storefront download support.

== 1.2.1 highlights ==

* Adds editable 404 page content for native and headless not-found routes, with
  Polylang translation resolution and compatibility for both 404 and 4o4 slugs.

== 1.2.0 highlights ==

* Adds backend-controlled guest, optional-account, and required-account checkout modes,
  including automatic login recovery and password-setting email for checkout-created users.
* Keeps completed digital order downloads available through the secure seven-day order-key
  window even when WooCommerce attached a customer during checkout.
* Adds distraction-free checkout navigation, Home-only empty-menu fallbacks, printable PDF
  receipts, and dismissible recent-order notifications with configurable link targets.

== 1.1.20 highlights ==

* Adds the editor-ready [video-hero] module with direct MP4/WebM, YouTube, and Vimeo
  sources; poster fallback; overlays; text and CTA pills; accessible playback and
  mute controls; reduced-motion handling; and matching headless/native rendering.
* Adds a Video hero/banner block to the WordPress editor and a rendered example to
  the storefront shortcode library.
* Restores [chat_assistant] in headless page content while preserving the paid
  plugin's native WordPress renderer.
* Loads the inline headless assistant as a separate, lightweight frontend chunk only
  when [chat_assistant] is present. Pages without it make no shortcode assistant
  configuration or chat requests.
* Includes the latest atomic multilingual route and language-switch behavior from
  the synchronized storefront release line.

The WordPress theme ZIP and the headless storefront are separate deployment
artifacts. Theme updates install backend contracts, editor integrations, and native
renderers; headless React changes require a storefront rebuild and deployment.

== Headless account URLs ==

WordPress, WooCommerce, and WPGraphQL password-reset emails point to the storefront
reset form. Production defaults to https://funkycommerce.netlify.app. Override it in
wp-config.php when using another frontend:

define( 'FUNKYCOMMERCE_FRONTEND_URL', 'https://store.example.com' );

The equivalent funkycommerce_frontend_url option can be used by managed deployments.

== Navigation ==

The theme registers three classic WordPress menu locations:

* Header Menu: primary desktop navigation.
* Mobile Menu: optional mobile-specific navigation.
* Footer Menu: top-level items represent footer columns; their children are links.

WPGraphQL exposes assigned classic menus and their hierarchy through Menu and
MenuItem types. Localized installations should create one menu per location and
language, using stable slugs such as header-en, header-pl, footer-en, and footer-pl.

== Special storefront pages ==

The frontend keeps its mapped application UI for Home, Shop, Blog, Cart, Checkout,
Wishlist, Reading List, Account, Auth, and Community while also rendering supplemental
content from the corresponding WordPress page.

Pages are discovered by their route slugs, so creating and publishing a page named
home, shop, blog, cart, checkout, wishlist, reading-list, account, auth, or community
is sufficient; no WordPress reading-page or WooCommerce page assignment is required.
The conventional my-account slug remains supported for existing stores. The theme
creates editable Wishlist, Reading List, and Auth pages because WordPress and
WooCommerce do not provide those records. Their structural shortcodes remain in
WordPress as backend component references:

* [funkycommerce_wishlist]
* [funkycommerce_reading_list]
* [funkycommerce_auth]

Linked multilingual page translations are selected by their database identity, so
translated front pages remain distinct even when a translation plugin reports the same
public URI for more than one language.

Not-found routes use the published 404 page, or the WordPress-safe 4o4 alias, as their
editable content source. When Polylang is active, the frontend and native theme resolve
the translation linked to the language of the missing route. If no matching page or
translation is published, the built-in 404 content remains available.

Rendered page content also receives WordPress' block-library styles, merged block-theme
global CSS, and Additional CSS. The `themeStyles` field on Page (and the
`funkycommerceThemeStyles` root field) exposes typed color, gradient, font-family,
font-size, spacing, and content-width presets so the headless renderer stays aligned
with Site Editor customization while retaining the storefront's Tailwind shell. The
frontend queries this root field at application-layout level, keeps the CSS mounted for
every route, and writes the typed presets to their standard `--wp--preset--*` variables.

The block theme includes dedicated Single Post and Author Archive templates for direct
WordPress previews. Multilingual post records retain their WPGraphQL language and
translation identities; author archives use the selected language to filter their post
query, including explicit `?lang=en` and `?lang=pl` preview links.

== Native frontend theme ==

The theme owns a dedicated build pipeline for its native, non-headless rendering path
(template HTML/PHP, template parts, and frontend assets) that is entirely independent of
the Control Center's PHP contracts:

* `package.json` / `tailwind.config.js` / `postcss.config.js`: a Tailwind CSS v3 build
  scoped to this theme (content-scanned over `templates/`, `parts/`, and `assets/js/`,
  with Tailwind's Preflight reset disabled so it coexists with WordPress core and plugin
  CSS). Run `npm install` once, then `npm run build` to compile
  `assets/css/theme-source.css` and `assets/js/theme.js` into `assets/dist/theme.css` /
  `assets/dist/theme.js`, and to inline both compiled files into
  `parts/header.html` / `parts/footer.html` (see `build/sync-template-assets.mjs`).
  `npm run watch:css` re-compiles CSS on change during development. `npm run lint:php`
  checks the syntax of PHP files this theme package owns (currently
  `inc/frontend-theme.php`) using the `php-parser` npm package, since a native `php -l`
  binary is not guaranteed to be available in every environment this theme is built in.
* Because the compiled CSS/JS are inlined directly into the header/footer template
  parts, the native shell (sticky/collapsible announcement header, accessible primary
  navigation, dark-mode toggle, crystal-style loading overlay, footer newsletter panel,
  and lazy-loaded Spotify slot) works immediately with zero additional PHP wiring.
* `inc/frontend-theme.php` is an optional, self-contained upgrade path that is **not**
  currently loaded by `functions.php`. It idiomatically `wp_enqueue_style`/
  `wp_enqueue_script`s the same compiled files (so browsers can cache them separately
  from inline page HTML), mirrors Control Center loader/Spotify settings to the frontend
  script via `wp_localize_script()` when `funkycommerce_storefront_control_settings()` is
  available, and reflects a configured Spotify playlist embed URL into the static footer
  markup via a `render_block` filter. To activate it, add one line to `functions.php`:
  `require_once get_template_directory() . '/inc/frontend-theme.php';`. The theme is
  fully functional and styled without this line; it only upgrades asset delivery and
  wires dynamic Control Center settings once added.
* `theme.json` exposes `settings.custom.fc.*` tokens (radius, loader size/duration/
  glow-color/glow-opacity) as `--wp--custom--fc--*` CSS custom properties, matching the
  Control Center schema's `loading` section defaults, so the loader looks correct even
  before `inc/frontend-theme.php` is wired in. `style.css` `@import`s the same compiled
  stylesheet consumed by the existing `add_editor_style( 'style.css' )` call, giving the
  block editor canvas and the public front end visual parity from one CSS source.
* The header's primary navigation uses a `core/navigation` block with no `ref`, relying
  on WordPress's classic-menu-fallback (a "Header Menu" location menu is promoted to a
  `wp_navigation` post on first render). The footer's link columns are static, editable
  block content instead, to avoid ambiguity between the header/footer/mobile classic
  menu locations resolving to the same fallback menu.
* The Spotify slot (`[data-funky-spotify-slot]` in `parts/footer.html`) stays hidden
  with a placeholder until a playlist URL is configured; `assets/js/theme.js` lazily
  mounts the embed iframe via `IntersectionObserver` once an embed URL is present
  (either injected by `inc/frontend-theme.php`, or hand-edited into the template part).

== Control Center ==

Appearance > FunkyCommerce is the single configuration surface for the headless theme.
Its tabbed sections cover branding, header and footer composition, visual CSS, checkout,
store and currency presentation, payments, shipping, multilingual
content, community features, UX and sound, deployment, SEO and AI files, scripts,
security, email, forms, push notifications, and advanced integration settings. Core
values are stored together in the funkycommerce_control_center option.

The Premium Companions tab is the boundary for independently sold plugins. It provides
activation and future licence slots for AI Shopping Assistant, Google Maps Locations,
Slack Notifications, Discord Notifications, and Abandoned Carts. This full-suite build
treats every companion as entitled while still reporting its real activation state and
allowing each plugin to provide its own Control Center handoff.

The supplied AO Vector Search plugin is recognized as the AI Shopping Assistant
companion. Its configuration button opens the plugin-owned Vector Search screen, while
the headless storefront probes its backend-derived REST config and exposes a safe,
plain-text chat interface only when that plugin API is available.

General WordPress utilities and free lead magnets are intentionally absent from the
theme's premium panel. Guest-order assignment, admin dark mode, macOS dots, page-menu
organisation, Starter Kit, Health Check, Analytics Lite, and Migration Helper remain
separate community plugins with no duplicated theme settings.

Authentication settings are owned by the auth plugin. The deployment frontend URL
remains under Build & Deploy because password-reset links and redirects need it.
Header and footer menu assignment remains in WordPress' native menu locations.

== Admin theme sync ==

The free theme automatically applies the active Site Editor Global Styles to wp-admin
and the post editor. The selected background, text, link/accent, button, and heading
colours theme the admin shell and core controls. Selected body, heading, and button font
families are applied as well, including locally installed WordPress Font Library faces.
Changes made under Appearance > Editor > Styles are reflected on the next admin page
load and require no separate setting or paid companion. Theme styling loads after the
user profile colour scheme through WordPress's core admin colour handle. Screen Options
remain available, and hidden Quick/Bulk Edit templates stay closed until WordPress clones
them into the active list table. Boot-time security constants read their saved raw values
without loading the translated Control Center schema before WordPress theme setup.

The Runtime coverage tab distinguishes controls consumed by current runtime code from
controls that are safely stored but still awaiting implementation.

== SEO feeds and static generation ==

WordPress remains the canonical publication source for RSS 2.0, Atom, XML sitemaps, and
robots.txt. The theme adds `/feed.xml`, `/rss.xml`, and `/atom.xml` aliases, maps public
content URLs to the configured headless frontend, and publishes WooCommerce products as
Google Merchant-compatible RSS at `/product-feed.xml` and `/feed/products/`.
Atom metadata uses the public storefront URL for its site, self, author, and entry
identifiers so the mirrored `/atom.xml` document remains valid for feed readers.

The merchant feed includes stable ID, title, description, frontend product URL, image,
availability, condition, currency-aware price, brand, and SKU/MPN where available. It is
dependency-safe and returns no product document when WooCommerce is unavailable.

Enabled `llms.txt`, `llms-full.txt`, brand voice, product JSON-LD, ranking signals, and
conversational FAQ settings are published as real root documents. The storefront build
mirrors these optional files plus robots.txt, all sitemap pages, RSS, Atom, and merchant
feeds into static output whenever `VITE_GRAPHQL_ENDPOINT` is configured.
The Apple Pay field accepts the complete domain-association document, not only a
Merchant ID, and the storefront publishes it unchanged at the required well-known path.

== Submission inboxes ==

Appearance > Newsletter Submissions stores explicit newsletter consent from the
storefront popup. Appearance > Form Submissions stores generic contact, enquiry, and
application forms submitted to the theme endpoint.

Both inboxes are private to administrators and support unread, read, spam, archived,
and permanently deleted states. When Akismet is active and configured, both channels
are checked before storage; suspected spam is retained in the Spam view and form
notifications are suppressed. Administrators can mark Spam or Not spam from the list or
detail screen, and those decisions train Akismet when available.

Each inbox can export the current filter or every record as UTF-8 CSV. Generic form
fields become dedicated columns, and cells that could be interpreted as spreadsheet
formulas are neutralised. Public REST endpoints validate input, apply a honeypot and
short request rate limit, and do not persist raw network addresses:

* POST /wp-json/funkycommerce/v1/newsletter-submissions
* POST /wp-json/funkycommerce/v1/form-submissions

Domain-specific auth, order, comment, review, and community forms keep using their own
APIs instead of duplicating records in the generic form inbox.

== Security hardening ==

The Security tab replaces the legacy hardening prototype with individually configurable
WordPress-native protections. Administrators can control:

* WordPress version disclosure, generic login errors, XML-RPC, self-pingbacks, and
  legacy wp_head discovery links.
* Theme/plugin editor and modification locks.
* Anonymous numeric-author enumeration and public core REST user/theme discovery.
* Baseline response headers, HSTS, CSP, and an allowlisted additional-header map.
* HTTPS redirects, configured bot identifiers, and suspicious traversal/query patterns.
* Privacy-minimized failed-login throttling, native login/registration honeypots, and a
  signed native-registration math challenge.
* A custom native WordPress login path and login-page branding, without copying or
  modifying wp-login.php.
* Apache-compatible uploads script blocking and directory-listing rules.

Potentially disruptive protections are disabled by default, including HSTS, CSP, HTTPS
forcing, bot and query filtering, the custom login path, file-modification locking, and
uploads .htaccess changes. Upload rules use a removable FunkyCommerce marker and are
removed when the theme is switched. Nginx and other web servers require equivalent
server-level upload rules.

The custom native login controls do not replace the separate headless authentication
plugin. Reserved WordPress routes cannot be saved as the login slug.

The free theme is dependency-safe: WordPress can activate and render it without
WooCommerce, Polylang, an SEO plugin, WPGraphQL, or WooGraphQL. Optional integration
fields and hooks are registered only when their owning plugin API is available. The
Control Center remains usable and supplies neutral currency, language, commerce, and
content defaults for an otherwise empty installation.

Layout Studio remains a frontend-only, session-based design-review tool and its layout
preferences are intentionally not duplicated in the Control Center. Both `/layout-studio`
and `/shortcodes` remain permanent storefront routes, but resolve to the normal not-found
surface unless the current authenticated viewer has the WordPress `manage_options`
capability.

WPGraphQL exposes the slug-resolved record through funkycommerceSpecialPage(key: ...)
and exposes headlessContent plus headlessShortcodes on Page. headlessContent omits
structural WooCommerce/custom application shortcodes and blocks, preventing duplicate
cart, checkout, account, wishlist, reading-list, or auth interfaces in React.

== Community and marketplace ==

The theme registers a GraphQL-only Community Post content type and Community Tag
taxonomy. Community posts retain featured images, tags, likes, moderated threaded
comments, and 1–5 star ratings.

Two narrow roles support frontend publishing:

* Creator: community posts only.
* Collaborator: WordPress blog articles and WooCommerce marketplace products only.

Collaborator products store their seller user ID and can be queried through
marketplaceProducts, optionally filtered by sellerId. Administrators and WooCommerce
managers can set a private platform commission percentage on each seller's user profile.
This percentage is intentionally not exposed by the public GraphQL schema.

When public community profiles are enabled, communityMembers returns every user not
individually marked private through the public-safe CommunityMemberProfile type. It exposes
only storefront profile fields and does not make private WPGraphQL User fields public.
The same type is used by Product.seller so marketplace cards and `/community/:handle`
profiles work for customers and custom roles even when they have no published blog posts.

Authenticated Collaborators can create simple or variable WooCommerce products through
createMarketplaceProduct. The mutation accepts up to three variation attributes, 100
variation combinations, and eight validated images; uploads become normal WordPress
media attachments, with the first image featured and the remainder assigned to the
product gallery.

== Customer account GraphQL ==

funkycommerceAccount returns only the authenticated customer's profile, WooCommerce
orders, and billing/shipping addresses. updateFunkycommerceAddress validates and persists
one address at a time. Unauthenticated requests fail rather than exposing account data.
The theme also bootstraps AxeWP Headless Login server authentication before WordPress
resolves the current user, including an Authorization-header compatibility fallback for
hosts that do not populate the standard PHP server variables. Authenticated storefront
requests also mirror the token through the plugin-approved X-WPGraphQL-Login-Token header
when Apache strips Authorization. The request user is restored during
graphql_process_http_request after WPGraphQL 2.6 clears its cached anonymous user and
before it creates the query context. Password login accepts either the WordPress username
or account email.

The theme does not define or expose the JWT secret. Headless Login reads it from its own
settings and generates one when none exists; successful login token issuance therefore
confirms that a signing key is configured. Account and community resolvers additionally
validate the request token directly, and funkycommerceViewer provides a resolver-time
authenticated User for role and capability checks.
