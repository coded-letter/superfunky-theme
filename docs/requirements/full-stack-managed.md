# Full Stack managed requirements

Full Stack managed hosting is for teams that want Coded Letter to operate both the
WordPress/WooCommerce backend and the Superfunky frontend.

From an infrastructure perspective, the only customer-controlled technical dependency
needed for launch is the domain: provide DNS access for the migration window or apply the
records supplied by Coded Letter.

> **Current availability:** Full Stack is an assisted managed service. Environments are
> reviewed and provisioned by the Coded Letter team rather than created through an
> instant self-service flow.

## What Coded Letter operates

- isolated WordPress and database hosting;
- supported PHP, WordPress, WooCommerce, theme, and plugin releases;
- the Superfunky frontend build and deployment;
- CDN, TLS, redirects, and deployment hooks;
- backups, updates, uptime monitoring, and managed recovery procedures;
- environment configuration and server-side secret placement;
- backend and frontend readiness checks;
- launch planning and DNS migration;
- Superfunky Pro and the companion plugins included in the contracted plan;
- the maintenance and support allowance defined by the order.

Staging, checkout monitoring, restore drills, enhanced incident handling, and additional
support are plan-dependent rather than assumed for every Full Stack engagement.

## What you provide

### Required for technical launch

- the production domain;
- DNS access or a contact who can apply records during the agreed window;
- approval of the launch and rollback windows.

### Required for store operation

Even when infrastructure is fully managed, Coded Letter cannot invent or own your
commercial settings. Provide or approve:

- business identity and contact details;
- catalogue, stock, prices, tax, shipping, returns, privacy, and terms content;
- payment-provider account access through the provider's approved connection flow;
- transactional email domain and provider details when email is enabled;
- analytics, tag-manager, maps, AI, and other third-party accounts when used;
- migration exports or access to the current site when content is being moved;
- named business and technical contacts.

Private keys and passwords are collected only through an agreed secure channel. They must
not be sent through public forms or stored in browser-visible frontend variables.

## Domain and DNS

You keep ownership of the domain. Coded Letter prepares the required DNS records, TLS
configuration, redirects, and cutover plan.

Before launch:

1. confirm who controls the registrar and authoritative DNS;
2. lower DNS TTL when appropriate;
3. preserve the existing service until the managed environment passes acceptance;
4. agree on rollback records;
5. schedule changes around payment, email, and traffic constraints.

Domain registration and renewal are not included unless the order explicitly says
otherwise.

## Third-party services

The managed platform can integrate with external payment, email, analytics, maps, push,
AI, and security services. The customer normally remains the account owner and pays
provider usage charges directly or as separately itemised costs.

Only public identifiers are exposed to the frontend. Secret keys remain on the backend or
in managed server-side environment storage.

## Acceptance requirements

A Full Stack launch is ready when:

- content and commerce data are approved;
- enabled payment and email providers pass test transactions or delivery checks;
- catalogue, cart, checkout, account, search, forms, and language flows pass acceptance;
- backups and restore access are confirmed;
- monitoring and support contacts are active;
- SEO/discovery output and redirects are reviewed;
- the production deployment and rollback procedure are approved;
- DNS is ready for cutover.

## Service boundaries

The managed fee covers the operational scope in the signed plan, not unlimited design,
content entry, custom development, provider usage, or emergency work outside that scope.
Requirements that exceed the plan are estimated separately before work begins.
