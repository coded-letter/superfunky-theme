# Post-processing and integrations

Add operational integrations only after the backend, storefront, commerce flows, and
design have passed acceptance. Each integration introduces an external system, new data
flows, or a potentially disruptive security policy.

## Guides

1. [GTM and GA4](gtm-ga4.md) - analytics container injection, consent, events, and
   verification.
2. [Multilingual stores](multilingual.md) - Polylang, translated commerce content,
   language routes, and community language ownership.
3. [Forms and autoresponders](forms-autoresponders.md) - submission inboxes, spam,
   notifications, providers, and current runtime boundaries.
4. [Push notifications](push-notifications.md) - service worker, VAPID, subscriptions,
   delivery integration, and browser testing.
5. [Security](security.md) - safe rollout of Free and Pro hardening across WordPress and
   the static storefront.
6. [SMTP and email delivery](smtp.md) - mail transport, sender authentication, DNS, and
   deliverability checks.
7. [Extra premium plugins](extra-plugins.md) - planned standalone companions for
   locations, notifications, assisted shopping, and cart recovery.

## Ownership and availability

| Area | Superfunky role | External owner |
|---|---|---|
| GTM/GA4 | Pro build inputs and script injection | Google accounts, consent policy, tag/event design |
| Languages | Free default plus Pro community controls | Polylang and its GraphQL/WooCommerce bridges |
| Forms | Core private inboxes and Free protection/routing | Akismet, provider connectors, delivery transport |
| Push | Pro switch and public VAPID/subscription API; open storefront browser flow | VAPID private key and push sender |
| Security | WordPress controls and generated storefront headers | Host, proxy, CDN, WAF, backups, monitoring |
| Email | Pro provider fields and WordPress mail calls | Mailgun/SMTP service, DNS, reputation, bounce handling |
| Extra plugins | Individually licensed companion integrations | External APIs, provider accounts, and usage charges |

Pro fields require the active Pro companion. Provider accounts, paid WordPress plugins,
DNS services, and usage charges remain separate.

## Before enabling an integration

1. Use staging with the same proxy, domain, and build process as production.
2. Back up WordPress and document a rollback path.
3. Assign an owner for provider access, billing, secrets, logs, and incident response.
4. Define what customer data is transferred and how long it is retained.
5. Configure the integration in one place; avoid duplicate analytics or mail transports.
6. Run an end-to-end test from the public storefront, not only WordPress admin.
7. Record the final working settings without copying secrets into project documentation.

## Runtime coverage

The Control Center's **Runtime coverage** tab distinguishes settings consumed by current
theme/runtime code from values stored for a companion or future integration. A saved
field is not evidence that a third-party request, email, analytics event, or push message
was delivered.

For static or hybrid deployments, settings used during generation require a new
storefront build. Runtime WordPress settings take effect on the backend after saving,
although caches and proxy configuration may still delay the result.

Next: [configure GTM and GA4](gtm-ga4.md).
