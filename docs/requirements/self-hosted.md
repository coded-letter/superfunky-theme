# Self-hosted requirements

Choose self-hosting when your team wants direct control of WordPress, the frontend build,
deployment credentials, monitoring, and release timing.

The Superfunky storefront remains open source. A Coded Letter hosting subscription is not
required for the Free Core.

> **Current availability:** the public theme, storefront, and shared UI library are
> available from the `coded-letter` GitHub organisation.

## What you operate

You are responsible for:

- WordPress, PHP, the database, media storage, backups, updates, and server security;
- WooCommerce configuration and payment-provider accounts;
- the frontend source checkout, dependency installation, builds, and deployments;
- environment variables and secret management;
- CDN, TLS, redirects, caching, uptime monitoring, and incident response;
- build hooks between WordPress and the frontend host;
- DNS and release cutovers.

Self-hosting does not include Coded Letter infrastructure operation or managed incident
response. Product support and software updates depend on the licence and support plan you
choose.

## Backend infrastructure

Use a WordPress host that provides:

- WordPress 6.7+ and PHP 7.4+;
- HTTPS with a valid public certificate;
- a supported MySQL or MariaDB version for the selected WordPress and WooCommerce
  releases;
- enough PHP memory and execution time for WooCommerce, GraphQL queries, media work, and
  scheduled tasks;
- WP-Cron or a real system cron that can run scheduled work reliably;
- outbound HTTPS access;
- rewrite rules for WordPress permalinks and custom REST/feed routes;
- access to logs and a staging environment for production stores.

Capacity depends on catalogue size, traffic, plugins, build frequency, and the amount of
content requested during static generation. Test against production-like data rather
than relying on a universal minimum server size.

## Frontend build environment

The public storefront requires:

- Node.js 18 or newer;
- pnpm 9 or newer (the repository currently pins pnpm 9.15);
- React, TypeScript, Vite, and Tailwind CSS;
- a static output directory that can be served from a CDN.

Your build platform must support:

- installing a pnpm workspace from its lockfile;
- running `pnpm run build`;
- publishing the generated storefront output;
- environment variables at build time;
- a fallback from unknown application routes to `index.html`;
- custom redirects, headers, and immutable asset caching;
- deploy hooks or another authenticated build trigger.

The frontend can run on Netlify, Vercel, Cloudflare Pages, an object-storage/CDN
combination, or another host that meets those requirements. Provider-specific behavior
must be tested before production.

## Required build values

| Value | Requirement |
|---|---|
| `VITE_GRAPHQL_ENDPOINT` | Required in production. Full HTTPS URL of the WordPress GraphQL endpoint. |
| `VITE_SITE_URL` | Required for correct absolute canonical URLs and generated discovery files. |
| `VITE_STRIPE_PUBLISHABLE_KEY` | Optional. Public Stripe key when the selected checkout integration needs it. |
| `VITE_GEOLOCATION_ENDPOINT` | Optional. Public geolocation endpoint for automatic regional behavior. |

Additional values may be documented for optional features. Treat every `VITE_*` value as
public.

## Network and CORS checks

Before deployment, verify that:

1. the GraphQL endpoint responds over HTTPS;
2. required WordPress and WooCommerce schema fields are present;
3. products can be queried;
4. the WooCommerce Store API accepts requests from the storefront origin;
5. `Cart-Token` is exposed to browser JavaScript;
6. authentication routes work when customer accounts are enabled;
7. security headers do not block GraphQL, checkout, fonts, images, analytics, or payment
   providers.

## Recommended production baseline

- Separate staging and production environments.
- Automated, tested backups for files and database.
- Uptime and checkout monitoring.
- Dependency and security update process.
- A rollback path for both WordPress and frontend releases.
- Least-privilege deployment tokens.
- Provider keys restricted by origin, referrer, API, and environment where supported.
- Documented ownership for DNS, payments, email, and incident response.

Next: continue to the WordPress, WooCommerce, frontend, and DNS pages in **Setup** after
the Requirements area has been approved.
