# Setup

Complete setup in this order:

1. [Prepare WordPress](wordpress.md).
2. [Configure WooCommerce](woocommerce.md).
3. [Connect and deploy the frontend](frontend.md).
4. [Move the production domain](dns.md).

Do not start with DNS. Keep the current production site available until the new backend
and storefront have passed acceptance on temporary URLs.

## Before you begin

Choose a deployment model in [Requirements](../requirements/README.md), then collect:

- WordPress administrator and hosting access;
- the backend hostname, for example `backend.example.com`;
- the intended public storefront hostname, for example `example.com`;
- DNS access or the contact who controls DNS;
- payment, email, analytics, and other provider accounts required at launch;
- a staging or rollback plan for an existing store.

## Recommended URL layout

| Service | Example | Public use |
|---|---|---|
| Storefront | `https://example.com` | Customer-facing site |
| WordPress | `https://backend.example.com` | Admin, GraphQL, REST, media, and webhooks |
| GraphQL | `https://backend.example.com/graphql` | Storefront data endpoint |

The WordPress origin must remain reachable after launch. Do not redirect every backend
request to the storefront: GraphQL, REST, WooCommerce Store API, media, login, cron, and
provider callbacks still use it.

## Setup completion checklist

- WordPress and the Superfunky theme are active.
- Required plugins are active without duplicate GraphQL integrations.
- Permalinks and the GraphQL endpoint work.
- WooCommerce products, taxes, shipping, payments, and account rules are configured.
- The storefront is connected to the intended GraphQL endpoint.
- Cart CORS exposes `Cart-Token` and permits the storefront origin.
- A production build succeeds with the final public URL.
- Draft-deployment acceptance is complete.
- DNS records, TLS, email records, and rollback values are documented.

The remaining documentation areas cover visual customisation and optional integrations
after the base store is connected.
