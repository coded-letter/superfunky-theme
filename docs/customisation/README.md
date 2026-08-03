# Customisation

Superfunky separates content, global design settings, personal layout preferences, and
source-code changes. Choose the narrowest method that can make the required change.

| Change | Use | Rebuild required |
|---|---|---|
| Store name, logo, fonts, icons, footer copy | **Appearance > FunkyCommerce** | No |
| WordPress content and block styling | Page/Post editor and Site Editor | No |
| Header, footer, checkout, palette, radius, or view preview | Storefront Layout Studio | No |
| Small CSS override | Control Center **Custom storefront CSS** | No |
| New component, Tailwind utility, route, or application behaviour | Storefront source | Yes |

The current WordPress menu retains the legacy **FunkyCommerce** name for upgrade
compatibility. The product and storefront are Superfunky.

## Guides

1. [Visual design](visual-design.md) covers typography, styles, layouts, custom CSS, and
   menus.
2. [Brand and identity](brand-identity.md) covers names, logos, icons, colour, and public
   brand copy.
3. [Shortcodes](shortcodes.md) documents application, commerce, editorial, and community
   components.
4. [Tailwind CSS and Gutenberg blocks](tailwind-gutenberg.md) explains how the two style
   systems work together.
5. [Free and Pro theme settings](free-pro-settings.md) defines the released product
   boundary.

## Recommended workflow

1. Complete the base [Setup](../setup/README.md).
2. Configure the identity and global content in WordPress.
3. Build pages with Gutenberg and supported shortcodes.
4. Test layout alternatives in the storefront.
5. Add custom CSS only after the standard controls are exhausted.
6. Fork and rebuild the MIT storefront only for code-level changes.
7. Check desktop, mobile, light and dark modes, reduced motion, and an authenticated
   customer session before publishing.

## Understand where a setting lives

### WordPress Control Center

Control Center values are site configuration. They are exposed to the storefront and
apply wherever the corresponding component is used.

### WordPress Site Editor

`theme.json`, global styles, block presets, fonts, and Additional CSS describe WordPress
content. Superfunky carries those styles into headless pages and posts.

### Layout Studio

Layout Studio is a live storefront workspace. Its choices can affect the current
storefront session, and authenticated users' choices are saved to their WordPress user
profile. Treat it as a preview and preference surface unless your implementation
explicitly promotes a value to site-wide configuration.

### Storefront source

Source changes give complete control. They also create an ongoing responsibility to
build, test, deploy, and merge future upstream changes.

Next: [customise the visual design](visual-design.md).
