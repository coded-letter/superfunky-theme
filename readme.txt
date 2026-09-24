=== Superfunky Headless ===
Contributors: coded-letter
Tags: headless, woocommerce, wpgraphql, full-site-editing
Requires at least: 6.7
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.49
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

This block theme is the WordPress control plane for the FunkyCommerce storefront.
By default it renders a minimal, headless-only shell because customer-facing output is
served by the separate headless application. When headless mode is disabled in the
Control Center, the theme also ships a complete native WordPress rendering path (see
"Native frontend theme" below) with accessible header/footer/navigation templates and
core front/home/singular/archive/search/404 routes styled to match the storefront.

== Storefront CSS controls ==

Open Superfunky > Control Center > Visual & CSS. Critical (above-the-fold) CSS
applies immediately after Site Editor global styles and WordPress Additional CSS.
Keep header, promo, hero, fonts, and initial layout rules here. Deferred
(below-the-fold) CSS is optional: enter complete top-level rules for content that
is not needed on first paint, without adding a separator.

Existing unmarked CSS, including the legacy funkycommerce_custom_css option,
stays entirely critical. A legacy standalone /* storefront:deferred */ comment
opens the following CSS in the deferred editor. This migration is read-only until
the next settings save; saving another section preserves both layers. Clearing
the deferred editor removes that layer. Legacy markers remain supported.

The existing customCss output and legacy option combine both editors with the
same marker; no GraphQL schema change is required. WordPress Additional CSS keeps
its existing position and stays critical unless it explicitly uses the marker.
With the matching storefront, deferred styles activate on window load or after
two seconds, with a no-JavaScript fallback. Rebuild the storefront after saving to
publish static pages. Older storefronts apply all CSS immediately.

== 1.2.49 candidate ==

* Removes synchronous CMS Tailwind inventory generation and its GraphQL field from the production build path.
* Restores the proven local reviewed-utility contract used before dynamic extraction, so CSS preparation performs no WordPress requests.
* Preserves native archive pagination, critical/deferred CSS, direct static navigation, single-locale discovery, hydration safeguards, and admin-bar submission counters.

== 1.2.48 highlights ==

* Paginates within each allowlisted post type using WordPress's `type_status_date` index, avoiding large ID ranges occupied by orders, revisions, and other irrelevant records.
* Keeps every backend request bounded to one content type and at most 100 stored records.
* Preserves revision-keyed page caching and rejects content changes during extraction.

== 1.2.47 highlights ==

* Replaces the monolithic cold Tailwind inventory query with bounded, primary-key cursor pages.
* Caps each backend page at 100 CMS records and 5 MiB of class-only output, eliminating the unbounded request that timed out on storage-bound servers.
* Caches each page by content revision so later builds reuse the manifest without serving stale CMS classes.

== 1.2.46 highlights ==

* Filters post, product, media, taxonomy, and author rows to class-bearing content in SQL before PHP scans them.
* Rotates the Tailwind inventory cache so the optimized cold path is measured after updating.
* Preserves the complete source coverage, class-only parsing, and admin-bar inbox counters from 1.2.45.

== 1.2.45 highlights ==

* Removes the broad post-metadata cache prime from Tailwind manifest generation; WooCommerce metadata is not needed to extract class attributes.
* Replaces recursive WordPress block parsing with bounded raw class/className matching.
* Uses a new cache generation so the first 1.2.45 request measures the optimized path rather than reusing an incomplete 1.2.44 attempt.
* Adds right-side admin-bar icons with unread counters for newsletter and form submissions, immediately before the account menu.

== 1.2.44 highlights ==

* Replaces build-time rendered CMS crawling with one cached, class-only Tailwind manifest.
* Extracts classes from stored public content, reusable blocks/templates, media, taxonomies, authors, menus, and Control Center HTML without running content filters or shortcodes.
* Invalidates the manifest when relevant CMS content changes and regenerates it lazily on the next build request.
* Keeps the direct static navigation payload and explicit single-locale build contract from 1.2.43.

== 1.2.43 highlights ==

* Exposes a bounded direct classic-menu payload for static builds, avoiding WPGraphQL's slow per-menu-item resolver chain.
* Explicit single-locale storefront builds use their configured locale contract instead of probing WPGraphQL/Polylang.
* Browser navigation keeps its existing WPGraphQL behavior; static builds fall back to it when the optimized field is unavailable.

== 1.2.42 highlights ==

* Exposes one bounded public build inventory for attachment captions/descriptions, taxonomy descriptions, author bios, and menu descriptions/classes.
* Avoids slow WPGraphQL connection resolution for these non-rendered class sources; storefronts retain the legacy connection fallback for older themes.
* Keeps rendered extraction for pages, posts, community posts, and products so block/shortcode output remains represented.

== 1.2.41 highlights ==

* Adds separate critical and deferred CSS editors under Visual & CSS, preserving existing CSS and the legacy separator.
* Reuses identical post/product headless-field and public rating resolver results within one read-only GraphQL operation.
* Resolver reuse is scoped by operation, site, viewer and locale, capped at 256 entries / 8 MiB, and bypassed for mutations. REST voting remains uncached.
* Standard WordPress and headless rendering remain separate. Page rendering is not memoized because block-support styles accumulate during rendering.
* Companion storefront changes reuse extracted bodies, catalog cards and public responses, and request post-detail metadata in batches of two while retaining all comments.
* Focused companion JavaScript regressions and Node-only CSS contracts pass; TypeScript has no additional diagnostics against the existing baseline. PHP execution, storefront builds and production timing validation remain deferred. The under-eight-minute target is unverified.

== 1.2.40 highlights ==

* Companion storefront update preserves promotional HTML classes and safe inline styles, and keeps Recipe JSON-LD as data rather than wrapped JavaScript.
* Product category, tag, and brand archives now use CollectionPage structured data.
* Storefront builds reject missing required hydration data instead of publishing loading-only pages; backend requests and optional SEO fetching are bounded.
* Custom CSS remains critical by default. With the matching storefront update, a standalone /* storefront:deferred */ comment between complete rules moves following CSS into a non-render-blocking layer.
* These fixes are in the frontend; update/rebuild the storefront alongside the theme. No backend behavior changes in this release.

== 1.2.39 highlights ==

* Shares native WordPress posts-per-page and effective WooCommerce products-per-page settings with headless archive grids and pagination.
* Makes grid shortcodes inherit those settings by default; explicit positive page_size values remain editorial overrides.
* Defaults Products per page override to 0 (inherit WooCommerce); existing saved positive overrides remain effective in both rendering modes.
* Schedules storefront rebuilding and settings invalidation when native pagination settings change.

== 1.2.38 highlights ==

* Restores configured, debounced storefront builds for CMS changes in shadow and artifact modes so new Tailwind utilities compile.
* Includes published shared blocks/templates, navigation changes, and public Control Center content in rebuild scheduling.
* Preserves webhook opt-in, headless-mode checks, and revision/autosave guards.

== 1.2.37 highlights ==

* Keeps WordPress uploads and WebP Express conversion requests on the backend when public headless redirects are enabled.

== 1.2.36 highlights ==

* Removes the stray admin-bar badge metadata output and adds Cloudflare Pages deployment controls.
* Places Superfunky Pro before submission inboxes and preserves environment-example line breaks.

== 1.2.35 highlights ==

* Adds a Studio product layout with top-section cross-sells and a full-width long description.
* Preserves product routing in static-first mode and restores blank inquiry-copy fallbacks.
* Restores the legacy top-level Netlify status badge controls and improves Control Center plugin ordering.

== 1.2.34 highlights ==

* Adds localized author biographies for multilingual headless storefronts.
* Makes build webhook and deployment badge controls available in the free theme.
* Adds the Superfunky Pro companion entry and hardens Control Center header contrast.

== 1.2.33 highlights ==

* Documents the portable static-first SSG rollout and rollback workflow in Build & Deploy.
* Explains explicit admin-bar publishing, artifact freshness, per-site keys, and required storefront variables.

== Static-first SSG setup ==

Static-first delivery keeps WordPress as the content and artifact control plane while the
frontend host serves complete generated route documents. It is portable across static hosts;
the examples below use environment-variable names from the Superfunky storefront.

1. Configure the public Frontend URL in Build & Deploy.
2. Create a unique Artifact site key for this public site. Never share a key between sites.
3. Generate a random signing secret of at least 32 characters. Save the exact same value in
   WordPress and the frontend deployment environment.
4. Start with Generate artifacts in shadow mode. Confirm the Artifacts panel shows a healthy
   active shell, a working Action Scheduler or WP-Cron runner, and no failed/exhausted jobs
   for the current shell.
5. Switch Dynamic content delivery to Serve generated artifacts.
6. Configure the frontend deployment:

   STOREFRONT_ARTIFACT_MODE=artifact
   STOREFRONT_ARTIFACT_DELIVERY=static-first
   STOREFRONT_ARTIFACT_ORIGIN=https://wordpress.example.com
   STOREFRONT_ARTIFACT_SITE_KEY=unique-site-key
   STOREFRONT_ARTIFACT_SIGNING_SECRET=same-secret-as-wordpress
   VITE_ARTIFACT_ROUTE_HYDRATION=true

7. Configure the host build webhook in WordPress. Netlify users may also enter the Netlify
   site ID as Build status badge ID.
8. Edit and preview content in WordPress. When ready to publish, click Rebuild storefront in
   the top admin bar. Wait for the deploy badge to succeed before testing the public site.

WordPress invalidation and artifact regeneration happen automatically, but they do not replace
static-first public HTML. A storefront build is the explicit publish boundary. Until that build
finishes, visitors continue receiving the previous known-good static deployment.

To roll back, restore the previous static-host deployment. To return to proxy delivery, set
STOREFRONT_ARTIFACT_DELIVERY=proxy and rebuild the storefront.

== 1.2.32 highlights ==

* Adds an administrator-toolbar action for manually publishing changed WordPress content to the static storefront.
* Displays the Netlify deployment status in the toolbar when a site ID is configured.

== 1.2.31 highlights ==

* Resolves canonical storefront blog posts before WordPress rewrite and old-slug redirect handling.

== 1.2.30 highlights ==

* Resolves canonical storefront blog routes to their published WordPress posts during artifact generation.
* Keeps ready artifact markup visible while richer product, post, author, and taxonomy data hydrates in the background.
* Avoids seeding product and post caches with the artifact renderer's intentionally generic server payload.

== 1.2.29 highlights ==

* Aligns product artifact hydration keys with the storefront's canonical trailing-slash routes.
* Prevents ready product artifacts from falling through to a duplicate blocking GraphQL request.

== 1.2.28 highlights ==

* Keeps trusted CMS scripts inert in server-rendered HTML until the storefront safely decodes and executes them.
* Preserves explicit JavaScript types while preventing HTML entities from corrupting operators during artifact delivery.

== 1.2.27 highlights ==

* Discards queued regeneration work for obsolete storefront shells during atomic shell activation.
* Keeps deployment warming focused on the only shell that can serve traffic.

== 1.2.26 highlights ==

* Returns the registered React shell when a valid public artifact is not ready instead of exposing a JSON error.
* Resolves safe base and region locale aliases during artifact delivery.
* Keeps authoritative artifact delivery portable across static hosting providers.

== 1.2.25 highlights ==

* Runs artifact regeneration through WooCommerce Action Scheduler with a native WP-Cron fallback.
* Prevents broad singular-route lookups from exhausting the artifact worker.
* Reports the active artifact background runner in the Control Center.

== 1.2.24 highlights ==

* Maps the configured WooCommerce terms page and its Polylang translations into storefront legal links.
* Keeps configured legal links and static navigation available before hydration.

== 1.2.23 highlights ==

* Exposes the WooCommerce-configured shop page and its Polylang translations to the storefront route registry.
* Adds the latest Polish inquiry and overlay-search labels to the versioned storefront string catalog.

== 1.2.22 highlights ==

* Restores product search on single-language WooCommerce sites and adds localized search and inquiry labels.
* Allows inquiry forms without uploads in the free theme while retaining server-enforced Pro licensing for file uploads.
* Adds optional direct product subcategories and category, tag, and author filtering for native slider and grid shortcodes.

== 1.2.21 highlights ==

* Supports capability-gated private pages and password-protected content without exposing protected pages through public caches, feeds, search, sitemaps, or prerendering.
* Preserves safe storefront return paths through login, registration, and provider authentication while rejecting external redirects.
* Adds opaque storefront media aliases while keeping existing upload links compatible.
* Adds full-screen header search, configurable mobile-menu dimensions, and a global code-block controls setting.

== 1.2.20 highlights ==

* Recovers header controls incorrectly disabled by section-specific Control Center saves and preserves schema defaults for newly introduced fields.
* Adds true single-row and floating-island headers, three-column footers, and configurable newsletter, assistant, and Spotify combinations.
* Improves multilingual blog and product-tag shortcodes, frontend favicon handoff, archive indexing, translations, and product/hero presentation.
* Uses cart-scoped payment, shipping, and tax availability at checkout, gates translated cart recovery on its companion plugin, and blocks unavailable products from cart actions.

== 1.2.19 highlights ==

* Preserves product image aspect ratios in galleries and lightboxes and serves linked media-library PDFs through the storefront domain.
* Keeps all public archive surfaces indexable and completes translated archive, inquiry, rating, review, comment, and related-product UI.
* Adds product wishlist and description-order controls, expanded footer/header layouts, an accessible back-to-top button, and persistent free-theme attribution.

== 1.2.18 highlights ==

* Allows safe HTML links in the free-theme footer copyright field.
* Sanitizes copyright markup in WordPress and again before storefront rendering.

== 1.2.17 highlights ==

* Publishes configured llms.txt documents reliably for static storefront deployments.
* Removes empty shop and community directories from generated sitemaps while preserving real CMS pages.

== 1.2.16 highlights ==

* Adds validated H1-H6 controls to video heroes and keeps static hero heading levels consistent in native and headless rendering.
* Fixes root fragment links from nested routes, honors the interaction-sound toggle, and exposes default-language UI strings without requiring Polylang.

== 1.2.15 highlights ==

* Disables automatic paragraph and line-break insertion across WordPress-rendered content.
* Applies the same formatting policy to posts, pages, excerpts, and WooCommerce short descriptions.

== 1.2.14 highlights ==

* Preserves complete Gutenberg Custom HTML blocks through headless content formatting.
* Keeps video hero posters and direct video sources covering the full hero on mobile.

== 1.2.13 highlights ==

* Preserves Custom HTML element structure while omitting blocks rendered by the headless application.
* Prevents generated paragraphs and line breaks from separating interactive HTML controls from their content.

== 1.2.12 highlights ==

* Repairs legacy paragraph and line-break markup injected into Custom HTML CSS blocks.
* Prevents content filters from leaving paragraph wrappers around preserved script and style blocks.

== 1.2.11 highlights ==

* Restores Control Center tab switching after settings were split into safe per-section forms.
* Preserves Custom HTML scripts and styles in generated headless content.

== 1.2.10 highlights ==

* Keeps theme settings site-local and submits each Control Center section independently on multisite hosts.
* Preserves trusted Custom HTML script and style blocks while generating headless storefront content.
* Validates Layout Studio imports atomically and rejects HTML responses before script execution.

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
