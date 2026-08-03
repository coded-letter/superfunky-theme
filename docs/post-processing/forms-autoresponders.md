# Forms and autoresponders

Superfunky provides public JSON submission endpoints and private WordPress inboxes.
Notification transport, mailing-list synchronisation, file handling, and customer
autoresponders are separate layers that must each be verified.

## Submission flow

| Purpose | Endpoint |
|---|---|
| Newsletter consent | `POST /wp-json/funkycommerce/v1/newsletter-submissions` |
| Contact, enquiry, or application form | `POST /wp-json/funkycommerce/v1/form-submissions` |

Successful requests return HTTP `201` with `{"received":true}`. The routes are public so
visitors can submit without a WordPress account.

The storefront newsletter helper sends:

```json
{
  "email": "customer@example.com",
  "consent": true,
  "source": "footer",
  "language": "en",
  "website": ""
}
```

`website` is the honeypot and must remain empty. Newsletter requests require a valid
email address and explicit consent.

A generic form integration sends:

```json
{
  "formId": "contact",
  "formName": "Contact form",
  "subject": "Product question",
  "email": "customer@example.com",
  "source": "https://shop.example.com/contact",
  "language": "en",
  "fields": {
    "Name": "Example customer",
    "Message": "Please send more information.",
    "Marketing consent": false
  },
  "website": ""
}
```

The released frontend helper supports text, number, and boolean field values. The backend
accepts at most 50 values, limits labels to 120 characters, and limits each value to
5,000 characters. `formId` is required.

## Free setup

In **Appearance > FunkyCommerce > Forms & Newsletter**:

1. enable honeypot protection;
2. set the administrator notification email;
3. confirm the public form shows a clear success and error state;
4. submit one test from the deployed storefront.

The current backend applies a privacy-minimised rate limit of 10 requests per submission
type per 15 minutes. Treat this as abuse reduction, not a replacement for CDN/WAF
protection.

Valid generic forms are stored privately, then the notification address is called
through WordPress `wp_mail`. A failed notification does not mean the inbox record was
lost.

## Private inboxes

Administrators with `manage_options` can use:

- **Appearance > Newsletter Submissions**;
- **Appearance > Form Submissions**.

Records can be marked unread, read, archived, spam, or not spam. Permanent deletion does
not use the trash. CSV exports neutralise spreadsheet-formula prefixes, but exported
files still contain personal data and need access controls.

Superfunky does not impose an automatic retention period. Define one in the store's
privacy policy, periodically export only what is required, and permanently delete expired
records.

## Akismet and spam

Superfunky Pro can send submissions to an active, configured Akismet installation.

- Detected spam is retained in the Spam view.
- Spam does not trigger the administrator notification or connector hook.
- **Mark as spam** and **Not spam** train Akismet when its API is available.
- The honeypot and rate limit should remain enabled alongside Akismet.

Test with provider-approved spam test data rather than real abusive content.

## Providers and double opt-in

Pro exposes newsletter provider, Mailchimp credentials/audience, and double-opt-in
settings. The inspected core runtime stores newsletter consent but does not itself
perform Mailchimp/MailPoet synchronisation.

Use the Control Center **Runtime coverage** result and the active Pro/provider connector
to verify all of the following before launch:

1. only non-spam, explicitly consented records are transferred;
2. source and language mapping are correct;
3. double opt-in email and confirmation status are provider-owned;
4. duplicate signups and provider errors have a defined outcome;
5. unsubscribe and deletion requests propagate to every owning system.

Do not call a stored WordPress record "subscribed" until the selected provider confirms
it.

## Autoresponders

The Pro order subject/template and form-autoresponder fields are configuration for the
active companion or delivery integration. The core form endpoint does not send a
customer autoresponder.

When an autoresponder is implemented:

- send only after successful storage and spam checks;
- use the submitted email only after validation;
- escape customer-controlled fields in HTML;
- include the correct language, sender identity, and reply-to policy;
- avoid echoing sensitive form answers back by email;
- log a provider message ID or explicit failure without exposing secrets.

Complete [SMTP and email delivery](smtp.md) before enabling autoresponders.

## File uploads

Pro declares allowed extension and size controls, but the inspected public endpoint is
JSON-only and does not process multipart files. An active companion/integration must
implement the upload route, authentication or anti-abuse controls, storage, malware
scanning, and retention.

Do not add a file input to production solely because upload fields are visible in the
Control Center.

## Acceptance checklist

- Empty honeypot succeeds and a filled honeypot is rejected.
- Invalid emails and missing newsletter consent are rejected.
- Rate limiting returns a visible retry message.
- A valid record appears once in the correct private inbox.
- Spam is stored without notification or provider transfer.
- Administrator mail arrives through the configured transport.
- CSV, archive, spam/ham, and permanent deletion behave as expected.
- Provider, double-opt-in, autoresponder, and upload behaviour is tested independently
  when enabled.
- The privacy notice names every destination and the retention period.

Next: [configure push notifications](push-notifications.md).
