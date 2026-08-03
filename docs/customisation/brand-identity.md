# Brand and identity

Configure public identity in WordPress before adjusting individual page layouts. This
keeps the header, footer, checkout, metadata, icons, and account surfaces consistent.

## Prepare the assets

| Asset | Recommended preparation |
|---|---|
| Logo image | SVG or a sharp transparent raster image with clear padding |
| Logo icon | Square mark that remains recognisable at small sizes |
| SVG favicon | Simple square artwork with no external dependencies |
| ICO favicon | Multi-size fallback for older browsers |
| Apple touch icon | Square PNG suitable for a home-screen tile |

Optimise files before upload. Do not place secrets, private metadata, or licensed font
files without web-distribution rights in public media.

## Store identity

Open **Appearance > FunkyCommerce > Branding** and set:

1. **Store name** - falls back to the WordPress site title when empty.
2. **Company / legal name** - use the entity shown in legal and customer-facing copy.
3. **Store tagline**.
4. **Logo format** - image, text, or icon and text.
5. The logo image, logo text, and icon required by that format.
6. Display, body, and monospace fonts.
7. Default colour mode.
8. SVG, ICO, and Apple touch icons.

Save, then check the header, footer, browser tab, mobile home-screen artwork, checkout,
and account pages.

## Header identity

In the **Header** section, configure:

- promotional message and bar colour;
- search, colour-mode, account, reading-list, wishlist, cart, and mobile-menu icon
  variants.

Layout Studio controls whether the promo bar, logo, and individual header actions are
visible and how search and cart interactions open. The Control Center owns their content
and icon choices.

## Footer identity

In the **Footer** section, configure:

- copyright text;
- social profile links;
- newsletter heading, supporting text, and privacy label.

Social links are stored as structured JSON. Each entry can define its icon, URL, title,
CSS class, enabled state, and whether it opens a new tab. Use HTTPS profile URLs and
disable an entry instead of leaving a broken placeholder.

Pro adds sanitised extra footer HTML. It does not replace the native Footer Menu or the
Free newsletter and social settings.

## Brand colour

Set the WordPress global `brand` colour in the Site Editor for the persistent site
identity. Superfunky derives lighter and darker storefront tones from it and uses those
tones for links, actions, focus treatments, gradients, and highlights.

Layout Studio also provides curated palettes and flat/gradient previews. Because these
are storefront preferences, validate the final production policy before treating a
studio selection as the default for every visitor.

Always check:

- text and controls meet contrast requirements;
- the mark works on both light and dark backgrounds;
- status colours are not confused with the brand colour;
- focus outlines remain visible;
- colour is not the only way information is communicated.

## Editorial voice

Review these Control Center fields as one identity system:

- promotion and newsletter copy;
- checkout heading, introduction, trust, support, consent, terms, and submit text;
- physical and digital order-success headings;
- copyright and legal company name.

Keep operational facts accurate. A trust message must not claim a payment method,
delivery promise, certification, or security control that is not actually configured.

## Native WordPress login

Superfunky Pro can customise the native WordPress login logo, colours, button, links,
footer text, and an optional animated background. These settings affect the backend login
experience, not the headless customer authentication layout.

The animation respects reduced-motion preferences. Test login branding on the normal
WordPress login URL before enabling a custom native login path.

## Identity acceptance checklist

- Store and legal names are used consistently.
- Logo image, text, and mixed modes have valid fallback content.
- Favicons update after browser and edge caches are cleared.
- Social links point to owned profiles and use safe new-tab behaviour.
- Checkout and newsletter copy matches the enabled services.
- Light, dark, mobile, and reduced-motion presentations are approved.
- Native login branding does not obscure the form or recovery links.

Next: [build pages with shortcodes](shortcodes.md).
