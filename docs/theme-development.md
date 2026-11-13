# Build a theme for WP ECard Addons

A theme is a directory with a `theme.json` manifest, a PHP template and an optional
stylesheet. The addon owns the document shell, loads the theme assets and renders the
universal action bar, so a theme only renders the card body. This contract lets a new
theme ship without changing any addon class.

## Quick path

1. Create `themes/my-theme/` inside the plugin, or register an external directory.
2. Add `theme.json` with an `id` equal to the directory name.
3. Add `template.php` that reads the `$card` object and renders the card body.
4. Add `style.css`, scope every selector under your theme root and declare it in the
   manifest.
5. Select the theme on a public card and verify the card and the action bar.

## Folder structure

```text
themes/my-theme/
├── theme.json          # manifest, required
├── template.php        # card body, required
├── style.css           # optional, declared in the manifest
├── preview.png         # optional admin preview, declared in the manifest
└── parts/              # optional partials used with my_ecard_template_part()
    ├── contacts.php
    └── social.php
```

The directory name and the manifest `id` must match. A theme with a missing template,
an invalid id, a duplicate id or an unsatisfied plugin requirement is not registered and
is not shown in the admin selector.

## theme.json

```json
{
    "id": "my-theme",
    "name": "My Theme",
    "description": "One sentence shown in the card theme selector.",
    "version": "1.0.0",
    "requires_plugin": ">=0.1.0",
    "template": "template.php",
    "styles": ["style.css"],
    "scripts": [],
    "preview": "preview.png",
    "preview_url": "",
    "supports": {
        "avatar": true,
        "cover": true,
        "accent_color": true,
        "header_height": true,
        "gallery": false,
        "videos": false
    }
}
```

| Key | Required | Notes |
|---|---|---|
| `id` | yes | Lowercase letters, digits, `-` and `_`; must match the directory name. |
| `name` | yes | Shown in the card theme selector. |
| `description` | no | Shown under the name in the selector. |
| `version` | no | Shown next to the name. |
| `requires_plugin` | no | For example `>=0.1.0`; defaults to `*`. |
| `template` | no | Defaults to `template.php`; must be a PHP file inside the theme. |
| `styles` | no | Relative CSS paths inside the theme. |
| `scripts` | no | Relative JS paths inside the theme. |
| `preview` | no | Relative image inside the theme (`png`, `jpg`, `jpeg`, `webp`, `gif`, `svg`). Served by the addon when the theme is inside the addon. |
| `preview_url` | no | Absolute `http(s)` URL for external themes whose files the addon cannot serve. |
| `supports` | no | Declares which card features the theme renders. `gallery` and `videos` default to `false`; the bundled Reference theme declares both `false` because media is delivered by the shell viewers, not by the card body. |

`styles` and `scripts` are enqueued by the addon for the resolved theme. External themes
should enqueue their own assets and use `preview_url` for the admin preview.

## template.php

The template renders the card body only. The addon shell provides the document:

- no `<!DOCTYPE>`, `<html>`, `<head>`, `wp_head()`, `wp_footer()` or `body_class()`;
- no action bar and no QR modal: the shell renders both;
- no direct ECard metadata reads: use `$card` or `my_ecard_card()`.

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$card = \MyECardExtensions\my_ecard_card();

if ( null === $card ) {
    return;
}
?>
<article class="my-theme" data-my-theme="my-theme">
    <h1 class="my-theme__name"><?php echo esc_html( $card->title ); ?></h1>

    <?php if ( '' !== $card->designation ) : ?>
        <p class="my-theme__designation"><?php echo esc_html( $card->designation ); ?></p>
    <?php endif; ?>
</article>
```

`$card` is a `MyECardExtensions\CardData` instance. The same object is returned by
`my_ecard_card()`; the resolved theme record is returned by `my_ecard_theme()` and the
raw render context by `my_ecard_context()`.

## Card data

`CardData` is the only supported read model. It normalizes ECard metadata and validates
URLs and email addresses before the theme sees them.

| Field | Shape |
|---|---|
| `title`, `designation`, `company`, `address` | strings |
| `email`, `phone`, `phone_url`, `whatsapp`, `whatsapp_url` | strings |
| `website`, `location_url`, `map_embed_url` | safe URLs or `''` |
| `avatar_url`, `logo_url`, `cover_url` | safe URLs or `''` |
| `gallery` | list of `{id, url, thumb, alt, title}` |
| `videos` | list of `{title, url, provider}` |
| `social` | list of `{platform, label, url}` |
| `settings` | addon settings for the card |
| `is_publicly_viewable`, `requires_password` | booleans |

Do not read `_ecard_*` meta directly. If a field you need is missing, extend `CardData`
in the addon rather than coupling a theme to ECard internals.

## Partials

Use `my_ecard_template_part()` for files inside the theme directory. It rejects
traversal, non-PHP files and paths outside the theme.

```php
\MyECardExtensions\my_ecard_template_part(
    'parts/gallery.php',
    array( 'heading' => __( 'Gallery', 'my-theme' ) )
);
```

Inside the partial, `$card` is available and the optional `$args` array is exposed as
`$args`. Partials are still included by the addon, so guard them with
`if ( ! defined( 'ABSPATH' ) ) { exit; }`.

## Shell integration

Every addon card is rendered inside `templates/shell.php`:

1. `wp_head()`, `wp_body_open()` and the document wrappers run once.
2. `my_ecard_before_card` fires, the theme template renders, then
   `my_ecard_after_card` fires.
3. The shell buffers the theme output, then asks `ActionBar` which actions are
   available. Actions depend on card data and per-card settings, never on markup the
   theme renders.
4. The shell renders the universal action bar and, when QR is available, the QR dialog.

The shell is also responsible for the fixed-bar bottom spacing. A theme must not add a
second document, a second bar or its own QR modal.

## ECard fallback

Cards without an addon theme keep ECard's original template and its `.dc-footer` (map,
gallery and videos through Venobox). Addon themes never render `.dc-footer`; the shell
renders `ActionBar` and the media viewers instead. Both paths are mutually exclusive per
request, so a page never shows two action bars.

## Action bar

The action bar is shared by every theme. It shows, in this order, only the actions that
are enabled for the card, have real data and are supported by the current environment:

| Action | Requires |
|---|---|
| vCard | `enable_vcard` and a public card |
| Location | `enable_location` and a valid `location_url` |
| QR | `enable_qr` and the optional `my_ecard_qr_available` policy |
| Share | `enable_share` and a public card URL |
| Gallery | `enable_gallery` and gallery data |
| Videos | `enable_videos` and video data |

Gallery and videos open the shell-owned media viewers, so they no longer depend on the
theme rendering section anchors. Never render your own action bar, viewer or action
logic.

## Media viewers

The shell renders `#my-ecard-gallery-viewer` and `#my-ecard-videos-viewer` from
`CardData` whenever the corresponding action exists. They provide:

- an image viewer with prev/next, thumbnails, counter, alt/title text, Escape and close,
  arrow keys, focus restore and touch swipe;
- a video viewer with a title list, prev/next, counter, Escape and close, arrow keys,
  focus restore and provider playback for YouTube and Vimeo, direct files through
  `<video>` and a safe external link for other valid URLs.

The viewer is independent of the active theme. Do not call `my_ecard_media_viewers()`
from a theme template; the shell already does it.

## Gallery and videos sections (optional, not used by Reference)

Gallery and video content is delivered by the shell-owned viewers, opened from the
ActionBar. A theme is not expected to render that content in the page, and the bundled
Reference theme deliberately does not: its card body stays limited to the cover,
avatar, identity, contacts and social links.

If a theme still wants to duplicate the content in the page, use the exact ids
`my-ecard-gallery` and `my-ecard-videos`. These are optional integration points and
alternative content; the ActionBar buttons open the viewers regardless of whether the
sections exist.

```php
<section id="my-ecard-gallery" class="my-theme__section" aria-labelledby="my-ecard-gallery-title">
    <h2 id="my-ecard-gallery-title" class="my-theme__section-title"><?php esc_html_e( 'Gallery', 'my-theme' ); ?></h2>
    <ul class="my-theme__gallery">
        <?php foreach ( $card->gallery as $item ) : ?>
            <li>
                <a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer">
                    <img src="<?php echo esc_url( $item['thumb'] ); ?>" alt="<?php echo esc_attr( $item['alt'] ); ?>" loading="lazy" decoding="async" />
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<section id="my-ecard-videos" class="my-theme__section" aria-labelledby="my-ecard-videos-title">
    <h2 id="my-ecard-videos-title" class="my-theme__section-title"><?php esc_html_e( 'Videos', 'my-theme' ); ?></h2>
    <ul class="my-theme__videos">
        <?php foreach ( $card->videos as $video ) : ?>
            <li>
                <a href="<?php echo esc_url( $video['url'] ); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo esc_html( '' !== $video['title'] ? $video['title'] : __( 'Watch video', 'my-theme' ) ); ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
```

Render each section only when its data is non-empty. Do not create a second gallery or
video store, do not embed arbitrary iframes and do not render untrusted HTML.

## Public functions

| Function | Purpose |
|---|---|
| `my_ecard_card()` | Current `CardData`, or `null`. |
| `my_ecard_theme()` | Current theme record. |
| `my_ecard_context()` | Raw render context. |
| `my_ecard_template_part( $name, $args )` | Render a partial inside the theme directory. |
| `my_ecard_vcard_url( $card = null )` | vCard download URL, or `''` when unavailable. |
| `my_ecard_qr_url( $card = null )` | QR image URL, or `''` when unavailable. |
| `my_ecard_qr_modal( $opener = true )` | Render the shared QR dialog. The shell already does this; do not call it from a theme template. |
| `my_ecard_action_bar( $card = null, $actions = null, $anchors = array() )` | Render the shared bar. The shell already does this; do not call it from a theme template. |
| `my_ecard_media_viewers( $card = null )` | Render the shared gallery/videos viewers. The shell already does this; do not call it from a theme template. |

## External theme directories

Register another base directory with the public filter. Every direct child containing a
`theme.json` is discovered; the id must match the directory name.

```php
add_filter(
    'my_ecard_theme_directories',
    static function ( $directories ) {
        $directories[] = __DIR__ . '/themes';
        return $directories;
    }
);
```

External themes cannot use the addon URL helper for their own assets. Enqueue their CSS
and JS from the theme or a small plugin, and use `preview_url` for the admin preview.

## Public filters

| Filter | Purpose |
|---|---|
| `my_ecard_theme_directories` | Add or remove theme base directories. |
| `my_ecard_themes` | Adjust the final theme record map. |
| `my_ecard_render_context` | Adjust the render context before the shell renders. |
| `my_ecard_before_card` | Output before the theme template. |
| `my_ecard_after_card` | Output after the theme template. |
| `my_ecard_qr_available` | Force the QR availability policy for the bar. `generate()` still requires the real library and the GD extension. |
| `my_ecard_loaded` | Fires when the addon is wired and ECard is available. |

## Checklist

- [ ] The directory name and `theme.json` id match.
- [ ] `template.php` renders the card body only.
- [ ] Every selector is scoped under the theme root.
- [ ] `styles` and `scripts` only reference files inside the theme.
- [ ] The theme reads `$card`/`CardData`, never `_ecard_*` meta.
- [ ] Gallery and videos open through the shared ActionBar viewers; the theme body renders no Gallery or Videos section, and the manifest declares `gallery`/`videos` as `false` unless the theme really renders them.
- [ ] URLs, text and attributes are escaped.
- [ ] No custom action bar, media viewer, QR modal or document shell, and no `.dc-footer`.
- [ ] A public card selects the theme and the expected actions appear.

## Next step

Select the theme on a test card, verify the card at 320, 375, 430 and 1366 px, and check
that the action bar, gallery and videos behave as declared.
