# Frontend as a Service requirements

Frontend as a Service is for teams that already operate a WordPress/WooCommerce backend
but want Coded Letter to deploy and operate the Superfunky storefront.

Coded Letter manages the frontend build configuration, deployment workflow, CDN, TLS,
monitoring, and production cutover assistance. You retain ownership and operational
responsibility for WordPress.

> **Current availability:** managed frontend automation is in preview. Backend migration
> and plugin preparation are still reviewed manually before frontend provisioning.

## What you need before onboarding

- A working WordPress 6.7+ site on PHP 7.4+.
- HTTPS on the backend.
- WooCommerce configured with the catalogue, currency, tax, shipping, checkout, and
  payment methods required for launch.
- Superfunky, WPGraphQL, and WPGraphQL for WooCommerce installed and active.
- A public GraphQL endpoint.
- WordPress administrator access for the person preparing the integration.
- Permission to change DNS for the production domain.
- An active managed frontend subscription and its associated Pro entitlement.

If customer accounts, multilingual content, Stripe, geolocation, email, or other optional
features are required, their plugins and provider accounts must also be ready.

## Backend readiness

Provisioning starts only after the backend passes checks for:

- GraphQL availability and the required schema;
- public product access;
- WooCommerce Store API CORS behavior;
- browser access to `Cart-Token`;
- configured frontend URL and allowed origin;
- compatible multilingual schema when multiple languages are enabled;
- no duplicate GraphQL type or field registrations.

A backend that fails readiness remains in onboarding until the issue is corrected. A
frontend deployment cannot compensate for missing or conflicting backend APIs.

## Information supplied during onboarding

Provide:

- backend GraphQL URL;
- preferred frontend site name;
- production domain, if one will be connected;
- required public frontend values, such as a Stripe publishable key or public
  geolocation endpoint;
- launch contact and DNS contact;
- required environments, such as production only or production plus staging;
- expected launch date and any known traffic event.

Do not send WordPress passwords, private payment keys, email API secrets, or other
credentials through frontend environment fields. Secret backend integrations remain in
WordPress or an approved server-side secret store.

## Responsibility split

### You manage

- WordPress hosting, database, media, backups, updates, and security;
- content, products, orders, customer data, tax, shipping, and payment configuration;
- backend plugins and provider accounts;
- legal copy and consent requirements;
- domain ownership and approval of DNS changes.

### Coded Letter manages

- the supported Superfunky storefront release;
- frontend build and deployment configuration;
- approved public environment values;
- CDN and storefront TLS;
- deployment hooks and managed rebuild workflow;
- frontend monitoring and the support allowance in the selected plan;
- draft deployment, launch checks, and DNS cutover assistance.

## DNS requirement

Keep the existing production site live while the new storefront is tested on a draft or
provider URL. DNS changes are the final step.

You must either:

- provide controlled DNS access for the agreed migration window; or
- apply the exact records supplied by Coded Letter.

DNS propagation, registrar restrictions, domain renewal, and third-party DNS charges
remain outside the frontend service unless explicitly included in the order.

## Not included by default

- WordPress hosting or backend maintenance;
- content or product migration;
- repairs to incompatible custom plugins;
- payment, transactional email, AI, domain, or unusual third-party usage charges;
- custom feature development;
- guaranteed remediation of an unready backend.

These items can be scoped separately or covered by a Full Stack managed plan.

## Launch gate

Production cutover requires:

1. a passing backend readiness check;
2. a successful production-mode build;
3. approval of the draft deployment;
4. tested catalogue, cart, checkout, accounts, forms, search, and enabled languages;
5. verified canonical URLs, sitemap, robots directives, redirects, and security headers;
6. an agreed rollback plan;
7. final DNS approval.
