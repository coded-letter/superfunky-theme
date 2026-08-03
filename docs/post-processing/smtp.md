# SMTP and email delivery

WordPress `wp_mail` is the common boundary for Superfunky form notifications and normal
WordPress/WooCommerce mail. Connect it to one authenticated delivery transport before
enabling transactional email or autoresponders.

## Choose one transport owner

Use one of these approaches:

### Pro Mailgun integration

Superfunky Pro exposes Mailgun API key, sending domain, From name, and From address
fields. Use this path only when the active Pro companion reports runtime coverage for
Mailgun delivery and can send a verified test message.

An API-based Mailgun integration is not SMTP even though it serves the same delivery
role.

### WordPress mailer plugin

Install one maintained SMTP or provider-API plugin and configure the transport there.
This path can be used independently of the Pro fields. A provider's WordPress plugin may
also own Mailgun delivery.

Do not enable a Pro Mailgun transport and a second SMTP/API mailer simultaneously.
Multiple `wp_mail` interceptors cause duplicate messages, conflicting From headers, and
unreliable diagnostics.

## Sender identity

Use a sender on a domain you control, for example:

```text
Store name <mail@updates.example.com>
```

- Verify the exact provider domain.
- Keep the From address stable and authenticated.
- Use Reply-To for a customer-service mailbox when replies are monitored.
- Do not send production commerce mail from a free consumer mailbox.
- Keep marketing and transactional streams separate when the provider supports it.

Provider API keys and SMTP passwords are server secrets. Never put them in `VITE_*`
variables, custom storefront scripts, Git, public GraphQL fields, or documentation.

## DNS authentication

Add the records issued by the selected provider:

1. **SPF** - authorise the sender. A hostname must have one combined SPF TXT record, not
   multiple competing records.
2. **DKIM** - publish the provider's selector record and confirm signing passes.
3. **DMARC** - start with an observed policy and reporting address, then tighten it after
   legitimate sources align.
4. **Tracking/return-path records** - add provider CNAME or MX records only when the
   provider explicitly requires them.

Preserve the domain's existing MX records unless inbound mail is intentionally moving.
DNS authentication does not take effect until the provider verifies it and caches have
expired.

See also [DNS setup](../setup/dns.md).

## Configure WordPress

1. Create a restricted provider credential for the production sending domain.
2. Configure the chosen transport owner.
3. Set the authenticated From name/address and prevent untrusted plugins from overriding
   it when the mailer supports that policy.
4. Configure timeout, TLS, and region/endpoint according to the provider.
5. Send a test message from WordPress.
6. Confirm the provider accepted it and the receiving mailbox authenticated it.

Do not use a successful PHP return value as the only test; it means the message was
handed to the mail layer, not that it reached the inbox.

## Forms, orders, and autoresponders

Test each producing workflow separately:

- password reset and new-account messages;
- WooCommerce new order, status change, refund, and customer invoice;
- Superfunky administrator form notification;
- provider double opt-in;
- Pro form autoresponder and customised order template, when an active connector consumes
  those fields.

The released core form endpoint calls `wp_mail` only for the administrator notification.
It does not send the Pro customer autoresponder by itself.

Use WooCommerce email settings for recipients and enablement. The Superfunky Pro
order-template fields apply only when the active companion explicitly owns that render
path.

## Deliverability and operations

- Test Gmail, Outlook, Apple/iCloud, and any important regional provider.
- Inspect SPF, DKIM, and DMARC alignment in received headers.
- Monitor bounces, complaints, suppression lists, rate limits, and provider billing.
- Remove hard bounces and honour unsubscribe requests.
- Avoid sensitive order/form content in subject lines.
- Give transactional and marketing mail an accountable owner.
- Rotate credentials and revoke the old value after staff or provider changes.

## Launch checklist

- Exactly one transport owns `wp_mail`.
- Provider domain and credential are verified.
- SPF, DKIM, and DMARC pass and align with the visible From domain.
- Existing inbound MX records remain correct.
- WordPress and every required workflow deliver a real test.
- Failures are visible in provider or mailer logs.
- Secrets are absent from frontend output and source control.
- Replies, bounces, complaints, unsubscribes, and incident escalation have owners.

Next: [review the extra premium plugins](extra-plugins.md).
