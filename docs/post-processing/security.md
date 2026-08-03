# Security

Superfunky hardening reduces common WordPress exposure and can generate matching
storefront headers. It complements, but does not replace, secure hosting, updates,
least-privilege access, MFA, backups, a WAF, malware monitoring, and incident response.

## Free baseline

The Free theme exposes conservative controls for:

- hiding WordPress version disclosure;
- replacing credential-specific native login errors;
- disabling XML-RPC;
- disabling self-pingbacks;
- removing legacy head links;
- disabling the built-in file editor;
- optionally locking plugin/theme/core file modifications;
- sending baseline response headers;
- hiding the WooCommerce **Visit Store** shortcut.

The baseline headers are:

```text
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(self), camera=(), microphone=()
```

`DISALLOW_FILE_MODS` blocks dashboard installs and updates. Enable it only when the
deployment pipeline owns updates and an administrator has a documented recovery path.

## Pro controls

Superfunky Pro adds controls for:

- blocking executable scripts and directory listings in uploads;
- numeric author enumeration and anonymous core REST user/theme discovery;
- HSTS, CSP, and allowlisted extra response headers;
- safe-method HTTPS redirects;
- configured bad-bot agents and suspicious query patterns;
- native WordPress failed-login lockout, honeypot, and signed registration challenge;
- a custom native login path and login-page branding;
- sanitised SVG uploads.

These controls affect WordPress's native login and registration. They do not replace or
automatically protect a separate headless authentication plugin.

## Safe rollout order

1. Update WordPress, plugins, theme, PHP, and server packages.
2. Verify off-site backups and a restore.
3. Confirm WordPress HTTPS detection behind the trusted proxy.
4. Enable the Free baseline and test WordPress, REST, GraphQL, cron, and webhooks.
5. Add enumeration, login, bot, and upload rules one group at a time.
6. Build and test CSP against every required origin.
7. Enable HTTPS redirect only after HTTP/HTTPS health checks pass.
8. Enable HSTS last, after every included subdomain is permanently HTTPS-capable.

Keep an authenticated WordPress session open during disruptive changes. Record the
custom native login URL outside WordPress.

## Headers and the static storefront

WordPress emits its effective headers on backend responses. During production generation,
the storefront also reads the allowlisted configuration and writes headers for the
static host.

Rebuild after changing header settings. Confirm that the deployment platform consumes
the generated header file; otherwise apply equivalent rules in the CDN or web server.

Test with:

```bash
curl -I https://shop.example.com/
curl -I https://cms.example.com/graphql
curl -I https://cms.example.com/wp-json/
```

Pro extra-header JSON is restricted to approved names. Configure transport, cache, and
provider-specific headers in the hosting layer when they fall outside that allowlist.

## CSP, GTM, and third parties

A Content Security Policy must permit only the origins actually needed by:

- the storefront assets and API;
- GTM/GA4 and the selected consent manager;
- payment, media, font, form, and push providers;
- any approved custom head/body scripts.

Develop the policy on staging and inspect browser violation reports. Do not solve a
broken policy with unrestricted `*`, broad `data:` rules, or unnecessary
`'unsafe-eval'`. Re-test checkout, account authentication, GraphQL/REST requests, images,
fonts, service workers, and deep links.

## HSTS and HTTPS

The Pro HSTS response includes a one-year lifetime and `includeSubDomains`. Browsers cache
it. Do not enable it until every current and planned subdomain works over valid HTTPS.

Theme HTTPS enforcement redirects only `GET` and `HEAD` requests. TLS termination and
canonical redirects should normally be correct at the proxy/CDN first; POST requests
must never depend on a theme redirect to become secure.

## Uploads and SVG

Upload script/listing protection writes a marked `.htaccess` section inside the uploads
directory. It is appropriate for Apache-compatible servers. Nginx and other servers need
equivalent deny-execution and directory-listing rules in server configuration.

The theme removes its marked section when the setting is disabled or the theme is
switched. If WordPress cannot write the file, apply equivalent rules manually and verify
them with a harmless test file.

Sanitised SVG support narrows risk; it does not make arbitrary SVG trustworthy. Restrict
upload capability, keep the sanitizer updated, and serve uploads with safe content-type
and execution policies.

## Recovery

- **Custom login path failure:** use host/database access to disable the setting in the
  `funkycommerce_control_center` option, then retry the standard login path.
- **File modification lock:** change the owning configuration or setting through the
  deployment path; do not leave writable emergency overrides behind.
- **Broken CSP/headers:** disable the affected setting at the backend and redeploy the
  storefront.
- **Upload rule failure:** remove only the theme's marked `.htaccess` section or switch
  the setting off; preserve unrelated server rules.
- **Request filtering false positive:** disable the specific filter and add a narrowly
  reviewed replacement at the WAF/server layer.

## Verification checklist

- Backend and storefront return only the intended headers.
- GraphQL, REST, webhooks, cron, payment callbacks, and email links still work.
- Native and headless login, registration, reset, and logout flows succeed.
- Lockout and anti-bot tests do not block administrators or provider callbacks.
- Upload execution is denied at the web-server layer.
- CSP has no unexplained violations.
- HSTS and HTTPS behaviour matches every subdomain.
- Backups, update ownership, MFA, logs, alerts, and recovery contacts are current.

Next: [configure SMTP and email delivery](smtp.md).
