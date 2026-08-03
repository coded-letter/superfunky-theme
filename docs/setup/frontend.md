# Set up the frontend

The Superfunky storefront is always free and open source under the MIT licence. The same
repository is used for Free Core, Pro, self-hosted, and managed deployments. The
WordPress backend controls which optional features are active.

Public source:
[`coded-letter/superfunky-storefront`](https://github.com/coded-letter/superfunky-storefront)

## Local development

### 1. Install the tools

Install:

- Git;
- Node.js 18 or newer;
- pnpm 9 or newer.

The public repository currently pins pnpm 9.15.

### 2. Clone and configure

```bash
git clone https://github.com/coded-letter/superfunky-storefront.git
cd superfunky-storefront
pnpm install
cp apps/storefront/.env.example apps/storefront/.env
```

Edit `apps/storefront/.env`:

```dotenv
VITE_GRAPHQL_ENDPOINT=https://backend.example.com/graphql
VITE_STRIPE_PUBLISHABLE_KEY=
VITE_GEOLOCATION_ENDPOINT=
```

Always set your own GraphQL endpoint. The development app currently has a demo fallback
when the value is empty; relying on it can make local testing appear to use your content
when it is actually connected to the Superfunky demo backend.

Only public values belong in this file. Never add payment secrets, webhook secrets,
WordPress passwords, SMTP credentials, or private API keys.

### 3. Run the storefront

```bash
pnpm dev
```

Open the URL shown by Vite, normally `http://127.0.0.1:4173`.

Verify:

- the correct store name and content load;
- products open from your backend;
- browser requests target your backend hostname;
- cart requests receive `Cart-Token`;
- enabled account, language, and payment features behave as expected.

## Self-hosted production

### 1. Configure build values

Set these in the hosting provider rather than committing a production `.env` file:

| Value | Use |
|---|---|
| `VITE_GRAPHQL_ENDPOINT` | Required HTTPS WPGraphQL endpoint |
| `VITE_SITE_URL` | Final public storefront origin for canonical and generated URLs |
| `VITE_STRIPE_PUBLISHABLE_KEY` | Optional public Stripe key |
| `VITE_GEOLOCATION_ENDPOINT` | Optional public geolocation service |

### 2. Build

```bash
pnpm install --frozen-lockfile
pnpm build
```

Publish `apps/storefront/dist`.

The build prerenders stable and discovered routes and mirrors enabled discovery files
from WordPress. Production builds do not use the development GraphQL fallback.

### 3. Configure the host

The host must:

- serve generated files over HTTPS;
- route unknown application paths to `index.html`;
- preserve real generated HTML and XML files before applying that fallback;
- serve hashed assets with long-lived immutable caching;
- avoid public caching of customer-specific API responses;
- support redirects and security headers required by the site;
- provide a deploy hook if WordPress-triggered rebuilds are used.

Netlify-style hosts can use this fallback:

```text
/*  /index.html  200
```

Adapt the syntax for other providers.

### 4. Preview before production

Deploy to a temporary URL first. Set the WordPress Control Center Frontend URL to that
draft during acceptance, then test the launch checklist from the WooCommerce guide.

Do not connect the production domain until canonical URLs, redirects, checkout, and
customer routes have been tested in a production-mode build.

## Frontend as a Service

For the managed frontend plan, do not create a second production deployment yourself.
Provide Coded Letter with:

- the HTTPS GraphQL endpoint;
- preferred provider site name;
- production domain;
- required public frontend values;
- launch and DNS contacts.

Coded Letter then:

1. runs backend schema, product, and Store API CORS readiness checks;
2. configures the supported repository, branch, build, and publish settings;
3. creates or updates the managed frontend site;
4. adds allowlisted public environment values;
5. configures the build hook;
6. publishes a draft deployment for acceptance;
7. assists with DNS cutover after approval.

WordPress passwords and provider secrets are not frontend environment values. Backend
preparation remains your responsibility under Frontend as a Service.

## Full Stack managed

For Full Stack managed hosting, Coded Letter prepares both WordPress and the frontend.
You provide the domain, business configuration, provider accounts, content/migration
inputs, and launch approval described in
[Full Stack requirements](../requirements/full-stack-managed.md).

Next: [prepare DNS](dns.md).
