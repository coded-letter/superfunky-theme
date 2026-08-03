# Tailwind CSS and Gutenberg blocks

Superfunky supports two complementary styling systems:

- Gutenberg and WordPress global styles for editorial page and post content;
- Tailwind CSS for the React storefront and shared application components.

Use Gutenberg for content composition. Use Tailwind when changing source.

## Gutenberg support

Build pages and posts in the WordPress block editor as normal. The storefront fetches the
rendered content together with the theme's styles, font faces, presets, and layout
settings.

The compatibility layer supports the standard behaviour of:

- left, right, center, wide, and full alignments;
- groups, flex and grid layouts;
- columns and mobile stacking;
- buttons;
- images, galleries, covers, embeds, and captions;
- media-and-text blocks;
- tables, separators, and spacers;
- WordPress colour, typography, and spacing preset classes.

Third-party blocks are not automatically equivalent to core blocks. A block that depends
on frontend JavaScript, PHP rendering, plugin CSS, or a browser global must be tested in
the headless storefront.

## Style loading order

Superfunky loads content styles in this order:

1. WordPress block-library and block-theme stylesheets;
2. WordPress font-face rules;
3. Site Editor global styles;
4. WordPress Additional CSS and Control Center custom CSS;
5. Superfunky's WordPress block-compatibility CSS.

The final layer protects block layout semantics from Tailwind resets and application
styles. Use a scoped selector when intentionally overriding one of those rules.

## `theme.json`

The theme's `theme.json` enables appearance tools and provides:

- brand, background, and foreground colour presets;
- fluid typography and a system-font preset;
- spacing controls;
- a `720px` content width and `1200px` wide width;
- header and footer template parts.

Site Editor changes are returned through the backend and mounted by the storefront. Keep
the editor and public page open side by side when tuning global block styles.

## Tailwind tokens

The storefront Tailwind configuration scans the storefront and shared UI source. It
includes:

- `brand-50` through `brand-950`, driven by CSS variables;
- `bg-brand-gradient` and `bg-brand-gradient-soft`;
- `shadow-soft`, `shadow-soft-lg`, and `shadow-glow`;
- a proportional radius scale driven by `--theme-radius`;
- display and sans-serif font families;
- dark mode through the `dark` class.

Prefer these tokens over hard-coded brand hex values:

```tsx
<a
  className="rounded-2xl bg-brand-gradient px-5 py-3 font-semibold text-white shadow-glow"
  href="/shop"
>
  Shop now
</a>
```

That component follows the active WordPress brand colour or storefront palette without
requiring a second colour implementation.

## Adding Tailwind classes

Tailwind only generates classes found in its configured source paths. Add complete class
names to storefront or shared UI source:

```tsx
const tone = featured
  ? "border-brand-300 bg-brand-50"
  : "border-zinc-200 bg-white";
```

Do not construct arbitrary class fragments at runtime:

```tsx
// Do not rely on this class being generated in production.
const className = `bg-${colour}-500`;
```

Use a typed lookup of complete classes, a CSS variable, or an explicit Tailwind safelist
when a finite dynamic set is unavoidable. Any Tailwind configuration or source change
requires a rebuild.

## Custom block patterns and third-party blocks

Before approving a block or pattern:

1. publish it on a staging page;
2. inspect the rendered HTML returned by WordPress;
3. confirm required styles are loaded on the storefront;
4. test responsive behaviour and keyboard interaction;
5. check both colour modes;
6. verify that editor-only scripts are not required.

If a block is primarily an application surface, prefer a supported
[shortcode](shortcodes.md) or a React component over embedding a script-heavy block.

## Troubleshooting

### The editor looks correct but the storefront does not

- Clear WordPress and frontend caches.
- Confirm the page response includes `themeStyles`.
- Check whether the block comes from a plugin with a separate stylesheet.
- Look for a broad Tailwind or custom CSS rule overriding `.wp-block-*`.

### A Tailwind class works in development but not production

- Ensure the complete class appears in a scanned `.ts` or `.tsx` file.
- Avoid runtime string construction.
- Run a fresh production build and deploy its new assets.

### A wide or full block overflows

- Confirm the block uses WordPress `alignwide` or `alignfull`.
- Check custom CSS for fixed widths or transformed ancestors.
- Test the content outside nested constrained blocks.

Next: [understand Free and Pro theme settings](free-pro-settings.md).
