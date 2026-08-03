# Push notifications

The open-source storefront and theme provide browser and subscription plumbing.
Superfunky Pro exposes the intended availability control and public-key field.
Delivering a notification also requires a server-side Web Push sender that owns the
private VAPID key.

## Architecture

The released flow contains:

1. a production service worker at `/sw.js`;
2. a user-triggered browser permission request;
3. a browser `PushSubscription`;
4. WordPress endpoints that store or remove that subscription;
5. service-worker handlers that display payloads and open an approved same-origin URL.

The backend routes are:

| Method | Route | Purpose |
|---|---|---|
| `GET` | `/wp-json/funkycommerce/v1/push/vapid-public-key` | Return the public VAPID key when configured |
| `POST` | `/wp-json/funkycommerce/v1/push/subscribe` | Store or update a subscription by endpoint |
| `POST` | `/wp-json/funkycommerce/v1/push/unsubscribe` | Remove a stored endpoint |

WordPress retains the latest 500 subscriptions and deduplicates them by endpoint. This
storage is not a notification sender.

## Prerequisites

- Superfunky Pro active;
- public frontend served over HTTPS;
- a service worker available at the origin root;
- browser Push, Notification, and Service Worker support;
- a generated VAPID key pair;
- a server-side sender or provider that supports Web Push encryption;
- a privacy notice and retention policy for subscription endpoints.

Keep the VAPID private key only in the sender's protected server configuration. The
public key can be exposed to browsers.

## 1. Connect the public key

Set the intended **Push notifications** state in
**Appearance > FunkyCommerce > Push**. The VAPID public key is read-only in the Control
Center and must be supplied by the active Pro sender or deployment integration.

In the current theme runtime, the public-key and subscription routes do not consult the
availability switch. The deployed Pro integration must enforce the disabled state in the
storefront/sender and define whether existing subscriptions are retained or removed.

For the current storefront release, explicitly provide:

- `VITE_VAPID_PUBLIC_KEY`;
- `VITE_PUSH_SUBSCRIBE_ENDPOINT`;
- `VITE_PUSH_UNSUBSCRIBE_ENDPOINT`.

Set the endpoint values to the full WordPress REST URLs listed above. This avoids relying
on the legacy `VITE_WP_GRAPHQL_URL` fallback, because the standard
`VITE_GRAPHQL_ENDPOINT` is not currently used to derive push URLs. Endpoint overrides
must use trusted HTTPS URLs. Rebuild after changing frontend environment values.

The browser can retain a local push subscription even when synchronisation to WordPress
fails, and the current helper does not surface that network failure. A successful UI
state is therefore not proof that WordPress stored the subscription.

## 2. Configure delivery

The inspected theme does not contain the private-key sender. The active Pro companion or
provider must:

- load the VAPID private key securely;
- encrypt and send standards-compliant Web Push payloads;
- define which application events trigger a message;
- remove expired or rejected subscriptions from WordPress;
- rate-limit campaigns and prevent duplicate sends;
- record provider responses without logging subscription secrets.

Order, shipping, and restock examples in the interface are not operational triggers
until this sender and its event wiring have been tested.

Order-specific notifications require an authenticated relationship between a
subscription and the correct customer. The public subscription endpoint alone does not
establish that relationship.

## 3. Permission UX

Ask for permission only after a clear user action. Explain:

- what notifications the visitor will receive;
- how often they may be sent;
- how to disable them in the storefront and browser;
- how the subscription is retained and processed.

Browsers can permanently deny repeated or unexpected prompts. Push permission is
separate from analytics consent and marketing-email consent.

## Payload and click behaviour

The service worker accepts JSON notification options such as:

```json
{
  "title": "Your order has shipped",
  "body": "Open the store to see tracking details.",
  "icon": "/pwa-192x192.png",
  "badge": "/pwa-192x192.png",
  "tag": "order-update",
  "url": "/account/orders"
}
```

Use a same-origin relative `url`. The worker focuses an existing storefront window when
possible, otherwise it opens a new one. Do not place private order details, secrets, or
untrusted external URLs in a notification payload.

## Testing

1. Deploy a production build; development mode does not register the service worker.
2. Confirm `/sw.js` returns JavaScript from the storefront origin and is not cached
   indefinitely.
3. Confirm the public-key endpoint returns the intended configured key.
4. Trigger the opt-in from a user gesture, require a successful HTTP response from the subscribe request,
   and confirm that WordPress stored the endpoint.
5. Send a real test from the configured server-side sender.
6. Test foreground, background, closed-browser, click, unsubscribe, and expired-endpoint
   behaviour.
7. Repeat on supported Chrome/Edge, Firefox, Android, and Safari versions in scope.
8. Verify the deployed integration's disabled state stops new subscriptions and document
   how existing records are retired.

Next: [harden the deployment](security.md).
