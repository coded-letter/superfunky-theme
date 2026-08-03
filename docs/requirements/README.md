# Requirements: choose a deployment model

Every Superfunky store uses the same basic architecture:

1. WordPress manages pages, posts, media, navigation, users, and theme settings.
2. WooCommerce manages products, stock, orders, checkout rules, shipping, and payments.
3. WPGraphQL and the WooCommerce GraphQL integration expose approved data to the
   storefront.
4. The React storefront is built and served from a separate public URL.

The deployment model determines who operates those layers. It does not change the open
source status of the storefront.

## Compare the options

| | Self-hosted | Frontend as a Service | Full Stack managed |
|---|---|---|---|
| Best for | Developers and teams that operate their own stack | Stores that already have WordPress and want the frontend operated for them | Teams that want Coded Letter to operate the complete platform |
| WordPress hosting | You | You | Coded Letter |
| WordPress setup and maintenance | You | You, with readiness guidance | Coded Letter |
| Frontend deployment | You | Coded Letter | Coded Letter |
| CDN and TLS for storefront | You | Coded Letter | Coded Letter |
| Build configuration and deploy hooks | You | Coded Letter | Coded Letter |
| Backups and backend monitoring | You | Not included | Included |
| DNS | You | You approve and apply the final records, with cutover assistance | You provide domain/DNS access or apply the supplied records |
| Superfunky software | Free Core, with optional Pro | Pro entitlement included with the managed frontend plan | Pro and the contracted companion bundle included |

## Requirements shared by all deployments

### WordPress

- WordPress 6.7 or newer.
- PHP 7.4 or newer. A currently supported PHP 8.x release is recommended for a new
  production store.
- HTTPS on both the WordPress backend and public storefront.
- Working WordPress REST API and non-plain permalinks.
- The ability to install themes and plugins.
- A public GraphQL endpoint, normally `https://backend.example.com/graphql`.
- A host that permits outbound HTTPS requests for deployment hooks, payment providers,
  email providers, licence checks, and other enabled integrations.

The theme can activate without its optional dependencies, but a functioning headless
WooCommerce store requires the commerce stack below.

### Core software

| Software | Required when | Purpose |
|---|---|---|
| Superfunky theme | Always | WordPress control plane and storefront configuration |
| WPGraphQL | Using the Superfunky storefront | WordPress content API |
| WooCommerce | Selling products | Catalogue, customers, orders, tax, shipping, and payment configuration |
| WPGraphQL for WooCommerce (WooGraphQL) | Using WooCommerce with the storefront | WooCommerce GraphQL schema and mutations |
| Headless Login for WPGraphQL | Enabling customer accounts and authenticated features | Headless login and token flow |
| Polylang and its compatible WPGraphQL integration | Enabling multiple languages | Translated content and language-aware queries |
| Polylang for WooCommerce | Translating WooCommerce data | Product, category, tag, and commerce translation workflows |

SEO, payment, email, anti-spam, and other provider plugins are optional and depend on the
features you enable.

### Frontend connectivity

- The storefront build must receive `VITE_GRAPHQL_ENDPOINT` with the full HTTPS GraphQL
  URL.
- The WordPress origin must allow the storefront origin to call the required GraphQL,
  authentication, custom REST, and WooCommerce Store API routes.
- WooCommerce cart responses must expose the `Cart-Token` header to the storefront.
- Production builds need the final public URL to generate correct canonical links,
  sitemaps, feeds, and redirects.
- Never place secret API keys in `VITE_*` variables. Vite variables are compiled into
  browser code. Only public values, such as a Stripe publishable key, belong there.

## Free Core and Pro

The deployment model and software tier are separate decisions:

- **Free Core** includes the GPL WordPress theme and the MIT storefront. It supports a
  useful headless baseline without registration or a paid hosting service.
- **Superfunky Pro** is a separate companion layered over the free theme. It unlocks the
  controls marked Pro without creating a different storefront fork.
- Pro controls remain visible when Pro is inactive, but they are locked and are not
  applied at runtime.
- Premium companion plugins are separate products with their own settings, licences,
  update channels, and support boundaries.

Continue with the requirements for [self-hosting](self-hosted.md),
[Frontend as a Service](frontend-as-a-service.md), or
[Full Stack managed hosting](full-stack-managed.md).
