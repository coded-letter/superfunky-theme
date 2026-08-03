# Set up DNS

DNS cutover is the final setup step. Complete backend readiness, a production-mode
storefront build, and draft acceptance first.

## Recommended hostname plan

Use separate hostnames for the public storefront and WordPress:

| Hostname | Destination |
|---|---|
| `example.com` | Production storefront |
| `www.example.com` | Storefront or redirect to the preferred storefront hostname |
| `backend.example.com` | WordPress, GraphQL, REST, media, and callbacks |

You may use `cms.example.com` or another backend label. Pick one before configuring
WordPress, GraphQL, CORS, payment webhooks, email links, and the frontend build.

## 1. Inventory the existing zone

Export or record all current DNS entries:

- apex and `www` web records;
- backend or staging records;
- MX records;
- SPF, DKIM, and DMARC records;
- provider-verification TXT records;
- payment, email, analytics, and search-verification records;
- CAA records;
- any wildcard or service subdomains.

Changing the website must not remove email or verification records.

## 2. Prepare for cutover

1. Confirm the authoritative DNS provider and account owner.
2. Confirm that the domain will not expire during migration.
3. Lower the TTL for web records ahead of the agreed launch window when appropriate.
4. Record the current values as rollback records.
5. Add and validate the backend hostname before moving the storefront.
6. Add the custom domain to the frontend host and start certificate validation.

Do not delete the old deployment while DNS caches can still point to it.

## 3. Create provider records

Use the exact target supplied by the hosting provider:

- the apex may use `ALIAS`, `ANAME`, flattened `CNAME`, or provider-specific `A` records;
- `www` commonly uses a `CNAME`;
- the backend hostname normally points to the WordPress host with a `CNAME` or `A/AAAA`
  record.

Do not copy example IP addresses from documentation. Provider targets can change.

If using a DNS proxy or web application firewall, begin with proxying disabled unless the
deployment has been tested behind that provider. Proxy caching, bot protection, or header
rewrites can break GraphQL, the WooCommerce Store API, payment callbacks, or
`Cart-Token`.

## 4. Configure application URLs

Before switching customers:

- keep WordPress Address and Site Address on the backend origin;
- set the Superfunky Control Center Frontend URL to the final storefront origin;
- set `VITE_GRAPHQL_ENDPOINT` to the final backend GraphQL URL;
- set `VITE_SITE_URL` to the final storefront origin;
- update allowed origins, payment webhooks, OAuth callbacks, SMTP/email links, maps, and
  other provider restrictions that depend on hostnames;
- rebuild the storefront with the production values.

Avoid changing WordPress database URLs with an untested global text replacement. Use a
serialization-safe migration tool when an existing WordPress origin changes.

## 5. Cut over

1. Freeze high-risk content or order changes for the agreed window if migration requires
   it.
2. Confirm the latest backend data and successful storefront draft.
3. Apply the storefront DNS records.
4. Confirm the preferred apex/`www` redirect.
5. Wait for the frontend host to issue a valid TLS certificate.
6. Test from more than one network or resolver.
7. Keep the old service and rollback records available.

## 6. Validate production

Check:

- apex and `www` resolve to the intended storefront;
- the backend hostname still resolves and has valid TLS;
- `/graphql`, `/wp-json/`, media, and WordPress admin remain reachable;
- canonical URLs, sitemap, robots, feeds, and social metadata use the storefront domain;
- cart, checkout, payment callbacks, login, account, forms, and email links work;
- no mixed-content request uses HTTP;
- MX, SPF, DKIM, and DMARC records are unchanged;
- redirects do not loop and old public URLs reach their intended destinations.

Keep elevated monitoring during DNS propagation.

## 7. Roll back if necessary

If a launch-blocking issue cannot be corrected safely:

1. restore the recorded web DNS values;
2. keep the backend online;
3. pause deployment hooks if they worsen the incident;
4. verify the previous storefront and checkout;
5. document the failure before scheduling another cutover.

DNS rollback does not reverse orders or content written during the launch window. Plan
data reconciliation separately when replacing an active store.
