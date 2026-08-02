# Extraction Status

Tracks what has been migrated from the private `superfunky-woo` monorepo into this
public repository. Nothing is extracted automatically — every addition here is a
reviewed, deliberate copy of free-tier-only code.

| Date | Module | Status |
|---|---|---|
| — | (none yet) | Repository scaffold created; awaiting theme free/Pro split before first extraction. |

## Blocking work before the first extraction

The internal theme at
`workspace/backend/apps/wp-instance/wp-content/themes/funkycommerce-headless` mixes
free and Pro functionality in the same files (e.g. `functions.php`, `inc/*.php`
covering crypto payments, the control center, headless login, and build webhooks).
Before any code lands here it must be split so that only genuinely free-tier
functionality is copied out — Pro-only code stays in the private monorepo.

## Process (once the split above is done)

1. Identify a free-tier module with no dependency on premium/paid functionality.
2. Copy it here manually (no automated subtree/CI export yet).
3. Strip any references to premium plugins, internal URLs, or secrets.
4. Add/adjust the theme's own tests (if any) and this status table in the same change.
5. Open a PR against this repo for review before merging.
