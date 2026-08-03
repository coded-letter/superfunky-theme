# Visual design

Superfunky can be customised without editing source for most store launches. Start with
the Control Center and WordPress global styles, use Layout Studio to compare alternatives,
then reserve custom CSS or source changes for the final exceptions.

## Typography

Open **Appearance > FunkyCommerce > Branding** to configure:

- display, body, and monospace font-family stacks;
- base font size;
- type-scale ratio;
- the default colour mode: device, light, or dark.

Use valid CSS font-family values. A named web font must also be loaded by WordPress, a
font provider, or the storefront; entering its name alone does not download the font.

WordPress font faces and global typography are also transferred to the storefront. Body,
heading, link, caption, and button font choices made through global styles are applied to
their equivalent headless elements.

## Width, spacing, colour, and motion

The Control Center **Visual & CSS** section provides:

- post maximum width;
- page maximum width;
- reduced-motion protection;
- custom storefront CSS.

WordPress `theme.json` supplies the editor's brand, background, and foreground colours,
fluid typography, spacing tools, and content/wide widths. Changes made through the Site
Editor remain the right choice for Gutenberg content.

The storefront maps the WordPress `brand` colour into a complete tonal scale used by its
brand utilities. It also maps WordPress background and foreground colours to the
storefront theme variables.

## Layout Studio

Open the **Layout studio** route in the storefront to preview:

- announcement-bar visibility and scroll behaviour;
- sticky header, search style, logo style, icon visibility, and cart drawer/dropdown;
- content maximum width, border radius, breadcrumbs, and brand palettes;
- newsletter-popup style and cooldown;
- footer visibility, columns, newsletter, logo, bottom bar, and module arrangements;
- physical or digital checkout, field visibility, coupon placement, payment position,
  and order-summary behaviour.

Product, post, archive, community, and shortcode components also expose their own view
switches where supported.

Layout Studio updates the live storefront immediately. For authenticated users, the
storefront loads saved preferences from WordPress and saves changes after a short delay.
This makes the studio suitable for previewing and personal preferences; it does not make
every control a global setting for all visitors.

## Menus

Create menus under **Appearance > Menus** and assign these theme locations:

| Location | Purpose |
|---|---|
| Header Menu | Primary desktop navigation |
| Mobile Menu | Mobile navigation; may differ from desktop |
| Footer Menu | Footer links and hierarchy |

Superfunky reads menu labels, descriptions, URLs, targets, CSS classes, link
relationships, parent/child hierarchy, and order. Use absolute URLs only for external
destinations. Keep internal links on the public storefront hostname.

After changing a menu:

1. confirm the correct display location is assigned;
2. clear any WordPress, GraphQL, or edge cache;
3. test parent and child links on desktop and mobile;
4. verify external links that open a new tab have an appropriate link relationship.

## Custom CSS

Use **Appearance > FunkyCommerce > Visual & CSS > Custom storefront CSS** for a small,
site-wide override. WordPress Additional CSS is included too.

Scope rules to the smallest stable selector:

```css
.wp-site-blocks.entry-content .wp-block-button__link {
  letter-spacing: 0.02em;
}
```

The storefront loads WordPress block styles, font faces, global styles, Additional CSS,
and Control Center CSS. Its block-compatibility layer loads last so alignment, columns,
media, and responsive block behaviour survive collisions with application CSS.

Avoid:

- selectors based on temporary DOM depth;
- broad rules for `button`, `img`, or `a` that affect application controls;
- hiding required checkout or account fields with CSS;
- copying generated Tailwind CSS into the Control Center;
- `!important` unless it is required to override a known compatibility rule.

## When to edit source

Edit the MIT storefront source when you need a new component, route, design token,
Tailwind utility, interaction, or layout that the existing controls cannot express.
Source changes require a new production build and deployment.

Keep reusable components in the shared UI package and storefront-specific composition in
the storefront application. Test a production build, not only the development server:
Tailwind generates utilities from classes it can find in the configured source paths.

## Visual acceptance checklist

- Logos remain legible on light and dark surfaces.
- Heading, body, form, and monospace fonts load without layout shifts.
- Header and footer menus work at mobile and desktop widths.
- Content does not overflow the configured page and post widths.
- Gutenberg wide/full alignments behave as intended.
- Checkout controls remain visible and keyboard accessible.
- Reduced-motion mode removes non-essential animation.
- Custom CSS does not change WordPress admin or backend API responses.

Next: [configure brand and identity](brand-identity.md).
