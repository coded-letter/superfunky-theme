=== FunkyCommerce Headless ===

This block theme is the WordPress control plane for the FunkyCommerce storefront.
Its frontend templates are intentionally minimal because customer-facing output is
rendered by the separate headless application.

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

The Runtime coverage tab distinguishes controls consumed by current runtime code from
controls that are safely stored but still awaiting implementation.

== SEO feeds and static generation ==

WordPress remains the canonical publication source for RSS 2.0, Atom, XML sitemaps, and
robots.txt. The theme adds `/feed.xml`, `/rss.xml`, and `/atom.xml` aliases, maps public
content URLs to the configured headless frontend, and publishes WooCommerce products as
Google Merchant-compatible RSS at `/product.feed.xml`, `/product-feed.xml`, and
`/feed/products/`.

The merchant feed includes stable ID, title, description, frontend product URL, image,
availability, condition, currency-aware price, brand, and SKU/MPN where available. It is
dependency-safe and returns no product document when WooCommerce is unavailable.

Enabled `llms.txt`, `llms-full.txt`, brand voice, product JSON-LD, ranking signals, and
conversational FAQ settings are published as real root documents. The storefront build
mirrors these optional files plus robots.txt, all sitemap pages, RSS, Atom, and merchant
feeds into static output whenever `VITE_GRAPHQL_ENDPOINT` is configured.

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
