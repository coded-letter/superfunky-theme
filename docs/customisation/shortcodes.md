# Shortcodes

Superfunky shortcodes place application screens and data-driven components inside
WordPress content. WordPress validates their attributes and emits safe component markers;
the React storefront renders the final interface.

Use the Shortcode block or enter a shortcode in a supported content field:

```text
[hero title="New season" variant="split" image="https://backend.example.com/wp-content/uploads/hero.jpg" primary_cta_label="Shop now" primary_cta_href="/shop"]
```

## Rules

- Use the documented attribute names with underscores.
- Attribute names and enum values are case-sensitive.
- Quote values containing spaces, commas, pipes, or URLs.
- Use `true` or `false` for booleans.
- Comma-separated fields accept values such as `sale,new`.
- Pipe-separated fields accept values such as `First title|Second title`.
- Dates use `YYYY-MM-DD`.
- Invalid enum values fall back to the documented default.
- Numbers are clamped to their supported range.
- Invalid dates and URLs are removed.
- Unsupported shortcode markers display a visible warning instead of failing silently.

The WordPress schema is the public contract. Do not rely on an attribute merely because
an internal frontend component happens to accept it.

## Application shortcodes

| Shortcode | Attributes and accepted values |
|---|---|
| `[funkycommerce_cart]` | `layout`: `classic` or `editorial`; `summary_position`: `sticky` or `static` |
| `[funkycommerce_checkout]` | `mode`: `physical` or `digital`; `coupon_position`: `inline` or `top`; `payment_position`: `left` or `right`; `summary_position`: `sticky` or `static`; `hide_optional_billing_fields`, `hide_optional_shipping_fields`, `show_order_notes`, `show_terms`, `show_privacy`, `allow_guest_checkout`: boolean |
| `[funkycommerce_wishlist]` | `card_variant`: `default`, `minimal`, `editorial`, `gallery`, `simple`, `variation`, or `expandable` |
| `[funkycommerce_reading_list]` | `layout`: `cards` or `editorial-2col` |
| `[funkycommerce_account]` | `default_tab`: `dashboard`, `orders`, `addresses`, or `community`; `tabs`: a comma-separated subset in the required order |
| `[funkycommerce_auth]` | `mode`: `login`, `register`, or `forgot-password`; `layout`: `split`, `centered`, or `image-bg` |

Short aliases `[cart]`, `[checkout]`, and `[account]` are registered by the theme.
WooCommerce content using `[woocommerce_cart]`, `[woocommerce_checkout]`, and
`[woocommerce_my_account]` is recognised by the storefront too.

```text
[funkycommerce_checkout mode="digital" coupon_position="top" payment_position="right" summary_position="static" hide_optional_shipping_fields="true"]
```

## Hero and taxonomy components

| Shortcode | Attributes and accepted values |
|---|---|
| `[hero]` | `variant`: `glow`, `fullbleed`, `split`, `minimal`, `strip`; `kicker`, `title`, `description`, `height`; `image`: URL; `primary_cta_label`, `primary_cta_href`, `secondary_cta_label`, `secondary_cta_href`; `fullwidth`: boolean |
| `[categories]` | `type`: `product`, `post`; `layout`: `cards`, `compact`, `minimal`, `editorial`, `graphical`, `pills`; `columns`: 2-4; `limit`: 1-24; `include`, `title`; `orderby`: `name`, `count`, `include`; `order`: `asc`, `desc` |
| `[tags]` | `layout`: `pills`, `cards`, `compact`; `limit`: 1-100; `include`, `title`; `orderby`: `name`, `count`, `include`; `order`: `asc`, `desc` |
| `[authors]` | `layout`: `cards`, `compact`; `limit`: 1-100; `include`, `title`; `show_bio`: boolean; `min_posts`: 0-1000000; `orderby`: `name`, `post-count`, `include`; `order`: `asc`, `desc` |

`include` accepts comma-separated IDs or slugs where applicable. CTA destinations can be
a storefront path beginning with `/` or an absolute URL.

## Sliders, carousels, and grids

| Shortcode | Attributes and accepted values |
|---|---|
| `[slider]` | `type`: `campaign`, `product`, `post`; `layout`: `3/3`, `2/3`, `1/3`; `card_variant`: supported card variant; `slides`: 1-12; `limit`: 1-48; `navigation`: `dots`, `arrows`, `both`, `none`; `autoplay`: 0-60000 ms; `loop`, `fullwidth`: boolean; `include`, `category`, `tag`, `author`, `title`, `subtitle`, `kicker`, `description`, `height`; `date_from`, `date_to`; `min_rating`: 0-5; `orderby`: `date`, `title`, `rating`, `include`; `order`: `asc`, `desc`; campaign `titles`, `descriptions`, `images`, and `kickers` use pipe-separated lists |
| `[carousel]` | `type`: `product`, `post`; `card_variant`: supported card variant; `columns`: 1-6; `limit`: 1-48; `include`, `category`, `tag`, `author`, `title`, `subtitle`; `date_from`, `date_to`; `min_rating`: 0-5; `autoplay`: 0-60000 ms; `loop`: boolean |
| `[grid]` | `type`: `product`, `post`, `community-article`; `card_variant`: supported card variant; `layout`: `standard`, `compact`, `editorial`, `masonry`; `columns`: 1-6; `page_size`: 1-48; `paginated`: boolean; `include`, `category`, `tag`, `author`, `title`, `subtitle`; `date_from`, `date_to`; `min_rating`: 0-5; `orderby`: `date`, `title`, `rating`, `include`; `order`: `asc`, `desc` |

Product card variants are `default`, `minimal`, `editorial`, `gallery`, `simple`,
`variation`, and `expandable`. Post card variants are `default`, `compact`, `editorial`,
and `minimal`.

```text
[slider type="product" title="Top rated" layout="2/3" slides="3" navigation="both" autoplay="5000" card_variant="editorial" orderby="rating"]
```

## Reviews and comments

| Shortcode | Attributes and accepted values |
|---|---|
| `[reviews]` | `layout`: `grid-4`, `grid-3`, `grid-5`, `masonry`, `compact`; `variant`: `cards`, `full`, `compact`; `limit`: 1-48; `product`, `title`; `min_rating`, `max_rating`: 0-5; `date_from`, `date_to` |
| `[comments]` | `layout`: `cards`, `compact`; `variant`: `cards`, `full`, `compact`; `limit`: 1-48; `post`, `title`; `min_rating`, `max_rating`: 0-5; `date_from`, `date_to` |
| `[testimonials]` | `layout`: `grid-3`, `carousel`, `compact`; `limit`: 1-12; `min_rating`: 0-5; `date_from`, `date_to`; `title` |

Only approved source records returned by WordPress or WooCommerce are displayed.

## Community components

| Shortcode | Attributes and accepted values |
|---|---|
| `[community-feed]` | `layout`: `masonry`, `grid-3`, `grid-4`, `list`, `compact`; `load_mode`: `manual`, `infinite`; `page_size`: 1-48; `show_filters`: boolean; `tags`, `author`, `title`; `date_from`, `date_to`; `min_rating`: 0-5; `min_likes`: 0-1000000 |
| `[community-hero]` | `layout`: `gradient`, `split`, `image-bg`; `kicker`, `title`, `description`; `image`: URL; `show_upload`: boolean |
| `[community-marketplace]` | `layout`: `grid`, `compact`, `carousel`; `card_variant`: any product card variant; `columns`: 1-6; `limit`: 1-48; `min_rating`: 0-5; `title` |
| `[community-tag-picks]` | `layout`: `grid-3`, `grid-4`, `compact`; `tags`, `title`; `tag_limit`, `post_limit`: 1-12; `min_likes`: 0-1000000; `date_from`, `date_to` |
| `[community-members]` | `layout`: `grid`, `compact`, `list`; `columns`: 1-6; `limit`: 1-100; `include`, `title`; `role`: `all`, `member`, `creator`, `collaborator`; `show_bio`: boolean |

Community shortcodes require the corresponding community data and capabilities. A valid
shortcode can still show an empty state when no public records match its filters.

## Composite and utility components

| Shortcode | Attributes and accepted values |
|---|---|
| `[related-sections]` | `items`: up to three comma-separated values from `products`, `posts`, `community`, `testimonials`, `none`; `product_limit`, `post_limit`, `community_limit`: 1-12 |
| `[order-success]` | `mode`: `physical`, `digital`; `show_native_link`, `show_support_link`: boolean |
| `[unsubscribe-form]` | `title`, `description` |

## Publish and test

1. Preview the WordPress page and check that the shortcode is not shown as raw text.
2. Open the page on the headless storefront.
3. Test loading, populated, empty, and error states.
4. Check every link and filter.
5. Test the smallest supported viewport.
6. For account, checkout, upload, or community components, test both anonymous and
   authenticated sessions.

Next: [use Tailwind CSS and Gutenberg blocks](tailwind-gutenberg.md).
