# Free and Pro theme settings

Superfunky uses a companion model:

- the WordPress theme is GPL-2.0 and provides the Free control plane;
- the complete React/Vite storefront and shared UI library are MIT;
- Superfunky Pro is a separate backend companion that unlocks premium operational
  capabilities.

There is no paid frontend fork. Free and Pro stores use the same open-source storefront.

## How the boundary works

Open **Appearance > FunkyCommerce**. Each field is labelled **Free** or **Pro** from the
released theme schema.

Free settings work with the theme alone. Pro settings require the licensed companion and
its runtime capability. Entering a Pro licence key does not turn third-party provider
accounts, WordPress plugins, hosting, or managed services into included products.

## Free theme capabilities

| Area | Included Free settings |
|---|---|
| Branding | Store and company names, tagline, image/text/mixed logo, icon, display/body/mono fonts, base size, type scale, default colour mode, favicon and Apple icon assets |
| Header and footer | Promotion copy and colour, header icon variants, copyright, social links, newsletter copy and privacy label |
| Visual and content | Post/page widths, reduced-motion protection, custom storefront CSS, Gutenberg/global-style transfer |
| Checkout | Heading, introduction, trust/support messages, marketing label, terms text, submit label, and physical/digital success headings |
| Catalogue | Products per page, stock badges, product reviews and ratings |
| Payments | Stripe publishable value and cheque/bank-transfer presentation |
| Language | Default publishing language |
| UX | Toast duration and interaction-sound switch |
| Deployment | Public frontend URL |
| Discovery files | Frontend sitemap, custom `robots.txt`, and `llms.txt` |
| Security | Version/head-link reduction, generic login errors, XML-RPC and self-pingback controls, file-editor/file-modification policies, baseline headers |
| Forms | Honeypot protection and notification address |
| Developer | Debug mode and Pro licence-key entry |

Native WordPress menus, Gutenberg blocks, supported shortcodes, WordPress content, and
the complete storefront source are also available without a paid frontend licence.

## Pro companion capabilities

| Area | Pro capabilities |
|---|---|
| Footer and identity | Sanitised extra footer HTML and native WordPress login branding |
| Currency and orders | Multiple display currencies, automatic/manual conversion policy, manual rates, and order prefixes |
| Payments and shipping | BLIK presentation, Bitcoin/Ethereum settings and rate lock, shipping display matrix, free-shipping thresholds, and cart estimator |
| Multilingual and community | Language inheritance, multilingual community content, public profiles, following, and controlled media uploads |
| PWA and sound | PWA features and custom interaction sound assets |
| Build and deploy | Deploy webhook, scheduled rebuilds, rebuild interval, and build-status badge |
| Feeds and platform files | RSS/Atom and merchant product feeds, full LLM and AI policy/data files, and Apple merchant association |
| Analytics and scripts | GTM container, approved head/body scripts, and cookie-consent configuration |
| Advanced security | Upload protections, author and REST enumeration restrictions, HSTS, CSP, approved custom headers, HTTPS enforcement, bot/request filtering, login lockout/honeypot/challenge, custom native login path, and sanitised SVG uploads |
| Email and newsletter | Mailgun, sender identity, MailPoet/Mailchimp selection, double opt-in, order-email template, and form autoresponder |
| Forms and push | Akismet integration, controlled file uploads, and web-push/VAPID support |

Some Pro fields are high-impact infrastructure controls. Test CSP, HSTS, forced HTTPS,
custom login paths, file-modification locks, request filtering, upload rules, scripts,
and deploy hooks on staging before production.

## Settings that belong elsewhere

The theme boundary does not replace the owning system:

| Requirement | Authoritative system |
|---|---|
| Product prices, stock, taxes, zones, coupons, and order rules | WooCommerce |
| Payment secret keys and transaction processing | Payment gateway/plugin |
| WordPress users, roles, updates, and core URL settings | WordPress |
| DNS, TLS, CDN, backups, and server firewall | Hosting or managed service |
| Provider billing, sending reputation, analytics properties | External provider |
| Personal header/footer/layout choices | Storefront user preferences |

For example, the theme may present currency or shipping information, but WooCommerce
remains authoritative for checkout totals and fulfillment.

## Choosing Free or Pro

Use Free when you:

- self-host and are comfortable managing deploys and integrations directly;
- need the full storefront source and standard visual/content controls;
- use provider plugins for operational features;
- do not need the companion's automation or advanced controls.

Add Pro when you need the backend companion's deploy automation, operational
integrations, advanced hardening, community/PWA capabilities, provider configuration, or
managed premium workflow.

## Upgrade and downgrade safety

Before enabling Pro:

1. back up WordPress;
2. update the Free theme to a compatible release;
3. install and activate the Pro companion;
4. enter the licence in the Control Center;
5. enable one capability at a time on staging.

Before deactivating Pro, disable Pro-owned hooks and infrastructure controls that could
outlive the plugin, including deploy schedules, custom login routing, security headers,
upload/server rules, and provider integrations. Free settings and the MIT storefront
remain available.

This completes [Customisation](README.md). Continue with post-processing and integrations
after the store's design and content have passed review.
