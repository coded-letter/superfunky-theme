# Extraction Status

Tracks what has been migrated from the private monorepo into this
public repository. Every addition here is a reviewed, deliberate copy of
free-tier-only code.

| Date | Module | Status |
|---|---|---|
| 2025-08-03 | Full theme (v0.7.0) | ✅ Complete — all PHP files, templates, and schema published |

## Free/Pro Tier Summary

The theme ships with **all** 170 Control Center fields. Each field is annotated with
`'tier' => 'free'` or `'tier' => 'pro'` in `inc/control-center-schema.php`.

- **71 free fields** — fully functional without any companion plugin
- **98 pro fields** — visible but locked; require the FunkyCommerce Pro companion plugin

When Pro is not active, `funkycommerce_is_pro()` returns `false` and:
- Pro fields show a lock badge + upgrade CTA in the admin UI
- Pro field values are preserved in the database but not applied
- The newsletter endpoint remains fully functional (free)
- The multi-input form endpoint returns a Pro-required notice

## Safety Verification

- ✅ No secrets, API keys, or credentials in extracted code
- ✅ No premium plugin source code included
- ✅ All Pro features gated via `funkycommerce_is_pro()` filter (defaults to `false`)
- ✅ Theme activates and runs without WooCommerce, Polylang, or WPGraphQL

## Process

1. Changes are developed in the private monorepo.
2. Free-tier code is manually extracted and reviewed.
3. Premium plugin references and secrets are verified absent.
4. Code is pushed to this repo with an updated status entry.
