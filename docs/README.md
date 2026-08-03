# Superfunky documentation

Superfunky is a headless WordPress and WooCommerce system with two technical layers:

- the Superfunky WordPress theme, which provides the content and commerce control plane;
- the open-source React storefront, which presents the customer-facing website.

The documentation is organised as a guided path from choosing a deployment model to
configuring and operating a store.

## Documentation map

1. **Requirements**
   - [Choose a deployment model](requirements/README.md)
   - [Self-hosted](requirements/self-hosted.md)
   - [Frontend as a Service](requirements/frontend-as-a-service.md)
   - [Full Stack managed](requirements/full-stack-managed.md)
2. **Setup**
   - [Setup overview](setup/README.md)
   - [WordPress](setup/wordpress.md)
   - [WooCommerce](setup/woocommerce.md)
   - [Frontend: local, self-hosted, or managed](setup/frontend.md)
   - [DNS](setup/dns.md)
3. **Customisation**
   - [Customisation overview](customisation/README.md)
   - [Visual design](customisation/visual-design.md)
   - [Brand and identity](customisation/brand-identity.md)
   - [Shortcodes](customisation/shortcodes.md)
   - [Tailwind CSS and Gutenberg blocks](customisation/tailwind-gutenberg.md)
   - [Free and Pro theme settings](customisation/free-pro-settings.md)
4. **Post-processing and integrations**
   - [Post-processing overview](post-processing/README.md)
   - [GTM and GA4](post-processing/gtm-ga4.md)
   - [Multilingual stores](post-processing/multilingual.md)
   - [Forms and autoresponders](post-processing/forms-autoresponders.md)
   - [Push notifications](post-processing/push-notifications.md)
   - [Security](post-processing/security.md)
   - [SMTP and email delivery](post-processing/smtp.md)
   - [Extra premium plugins](post-processing/extra-plugins.md)

## Release status

These pages describe the product boundary agreed on 3 August 2026.

- The free Superfunky WordPress theme is published under GPL-2.0 with Free and Pro
  controls separated. Free controls work immediately; Pro controls remain visible but
  locked until the Pro companion is active.
- The complete storefront and UI library are published under the MIT licence. The
  storefront is always free and open source; there is no separate paid frontend fork.
- Managed frontend automation exists, but backend preparation and dependency setup still
  include a manual readiness review.
- Full Stack managed hosting is delivered through assisted onboarding rather than
  self-service provisioning.

Availability notes should be updated as releases and managed onboarding mature.

## Product naming

Use **Superfunky** for the theme, storefront, Pro software, and documentation. Use
**Coded Letter** for billing, licences, managed services, and support.
