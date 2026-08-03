# Publish the documentation to WordPress

`publish-wordpress-docs.mjs` converts the product Markdown to HTML and creates or updates
a hierarchical WordPress page tree through the REST API. Each page includes the complete
documentation navigation, its active state, and an accessible right-side table of
contents.

## Requirements

- Node.js 22 or newer;
- an HTTPS WordPress URL;
- a dedicated WordPress user with permission to create and edit pages;
- an Application Password created for that user under **Users > Profile**.

Use a least-privilege publisher account where possible. Application Passwords are
credentials: keep them out of shell history, Git, build logs, and frontend variables.

## Preview the tree

No WordPress connection or credentials are needed:

```bash
node docs/scripts/publish-wordpress-docs.mjs --dry-run
```

From the private monorepo, the equivalent command is:

```bash
pnpm docs:publish:wordpress -- --dry-run
```

## Publish drafts

```bash
SUPERFUNKY_WP_URL=https://backend.example.com \
SUPERFUNKY_WP_USERNAME=docs-publisher \
SUPERFUNKY_WP_APP_PASSWORD="xxxx xxxx xxxx xxxx xxxx xxxx" \
node docs/scripts/publish-wordpress-docs.mjs
```

The default root page slug is `documentation`. New pages are created as drafts.
Re-running the command updates pages previously created by the publisher without
duplicating them or changing their current status.

If the root page already exists, adopt it explicitly:

```bash
SUPERFUNKY_WP_ROOT_PAGE_ID=123 \
node docs/scripts/publish-wordpress-docs.mjs
```

The selected root page is updated with the main documentation page. Child pages are
created below it.

## Publish reviewed pages

After checking the generated titles, content, hierarchy, internal links, and preview:

```bash
node docs/scripts/publish-wordpress-docs.mjs --status=publish
```

An explicit `--status` applies that status to the whole managed tree. When the option is
omitted, existing statuses are preserved and only newly created pages default to draft.
The command does not delete WordPress pages when a local file disappears; retire
obsolete pages manually after review.

## Configuration

| Environment variable | CLI option | Default |
|---|---|---|
| `SUPERFUNKY_WP_URL` | `--wp-url` | Required except for `--dry-run` |
| `SUPERFUNKY_WP_USERNAME` | `--username` | Required except for `--dry-run` |
| `SUPERFUNKY_WP_APP_PASSWORD` | Environment only | Required except for `--dry-run` |
| `SUPERFUNKY_WP_STATUS` | `--status` | New pages: `draft`; existing pages: unchanged |
| `SUPERFUNKY_WP_ROOT_SLUG` | `--root-slug` | `documentation` |
| `SUPERFUNKY_WP_ROOT_PAGE_ID` | `--root-page-id` | Create or reuse the managed root |
| - | `--docs-dir` | Parent `docs/` directory |
| - | `--dry-run` | Off |

Prefer environment variables for credentials. CLI options are supported for non-secret
values. The Application Password is accepted only through the environment so it does
not appear in the process command.

## What the publisher manages

- page title, slug, parent, menu order, content, and status;
- section pages from each directory's `README.md`;
- article pages below their section;
- internal Markdown links rewritten to the resulting page paths;
- native Gutenberg Columns with a 25% navigation column and 75% content column;
- responsive desktop and mobile navigation with expandable documentation sections;
- a refined sticky dot-rail table of contents generated from second- and third-level headings;
- a scoped content script that highlights the current table-of-contents section;
- a hidden source marker used for idempotent updates.

The publisher never deletes pages, uploads media, changes templates, or modifies menus.
If two WordPress pages contain the same source marker, it stops rather than guessing
which page to overwrite.

The shell uses literal Tailwind class names, mirrored by the official storefront's
documentation class inventory during its Tailwind build. The table-of-contents script
is stored in the page content and is idempotent when the headless storefront re-executes
Gutenberg content scripts. The navigation and anchor links remain functional when
JavaScript is unavailable.
The page layout is stored as native Gutenberg Columns. Its navigation, article, and
table-of-contents fragments use Gutenberg Custom HTML blocks so WordPress does not add
automatic paragraph or line-break elements that would change the responsive layout.
