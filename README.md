# WP ECard Addons

[![Version](https://img.shields.io/badge/version-0.1.0-blue.svg)](https://github.com/Barceloch/WP-ECard-Addons/releases)
[![WordPress](https://img.shields.io/badge/WordPress-6.5%2B-21759b.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

Themes, QR codes, vCard downloads and an action bar for
[ECard - Digital Visiting Card](https://wordpress.org/plugins/ecard-digital-visiting-card/).

WP ECard Addons is an independent addon for ECard 1.6.x. It keeps ECard as the card
management engine and adds visual themes, an action bar, QR codes and vCard downloads
on top of the existing data.

ECard is not modified: the addon reads its metadata, registers its own `_my_ecard_`
metadata and replaces the public card template only when a card explicitly selects a
registered addon theme.

---

## Requirements

- WordPress **6.5 or newer**.
- PHP **7.4 or newer**.
- **ECard - Digital Visiting Card 1.6.x**, active.
- PHP `mbstring` (required by the bundled QR library).
- PHP `gd` for QR image rendering. Without GD the rest of the addon works and QR stays
  hidden with a controlled HTTP 503 response.

### Compatibility status

This release distinguishes what was actually executed from what is only declared.

| Environment | Status |
| --- | --- |
| WordPress 6.5.12 | **Executed.** The release ZIP was installed with ECard 1.6.0; the Reference card, action bar, QR endpoint and vCard download were verified without PHP notices. |
| WordPress 6.8.2 | **Executed.** The end-to-end suite installs and exercises the release ZIP on this version. It is the version named by `Tested up to`. |
| PHP 8.2 | **Executed.** The unit and end-to-end suites run on PHP 8.2. |
| PHP 7.4 and 8.1 | **Declared minimum and static compatibility only.** The shipped code contains no syntax newer than PHP 7.4 and `composer.json` pins `config.platform.php` to 7.4.33, but neither version was executed for this release. They are not tested configurations. |
| ECard 1.6.0 | **Executed.** The addon accepts the whole 1.6.x range, but only 1.6.0 was installed and verified. Other 1.6.x releases are supported by declaration, not by test. |

---

## What is included in 0.1.0

- Dependency detection for ECard 1.6.x with an admin notice and safe degradation.
- Extensible theme registry based on `themes/{id}/theme.json` manifests, with optional
  preview images and declared gallery/videos support.
- Per-card settings storage and admin UI using the `_my_ecard_` prefix.
- A native theme card selector with name, description, preview, selected state and an
  accessible warning when a stored theme is no longer registered.
- `CardData`, the single normalization layer for ECard metadata.
- Template engine: `template_include` at priority 20 renders registered addon themes
  inside an autonomous document shell, with an explicit fallback to the ECard template.
- Theme assets: manifest `styles` and `scripts` are enqueued for the resolved theme.
- **Reference theme**: panoramic cover with configurable height, optional overlapping
  avatar, centred identity, address, circular phone/email/WhatsApp/website buttons, a
  brand-coloured social grid, a welcome description and a services list. Gallery and
  video content is never rendered inside the card: the action bar opens the independent
  viewers instead.
- **Universal action bar** rendered by the document shell for every addon theme: vCard,
  location, share, gallery and videos, plus QR when the renderer is available. Each
  action appears only when its own setting and data allow it. Gallery and videos open
  shared media viewers built from the card data, independent of the theme.
  `bar_position` supports `fixed`, `sticky` and `static`; `bar_labels` supports `show`,
  `hide` and `hide_mobile`. ECard's `.dc-footer` remains the fallback for cards without
  an addon theme.
- **vCard 3.0 downloads**: server-rendered file with UTF-8, escaping, 75-octet folding,
  safe filename and HTTP headers, served from a public card endpoint.
- **QR service**: payload, options, payload-aware cache key and a fail-closed endpoint.
  The production ZIP bundles `chillerlan/php-qrcode` 4.4.2 in `vendor/`, so QR works out
  of the box when GD is enabled.
- Theme contract in [`docs/theme-development.md`](docs/theme-development.md): manifest,
  template, assets, card data, partials, shell, action bar, section anchors, external
  directories and filters.
- Controlled uninstall: data is kept by default and can be removed only when the site
  explicitly opts in.

The action bar is functional: it renders real links and buttons, and the share action
falls back to the clipboard when the Web Share API is unavailable.

The Modern, Minimal and Corporate themes are **not** part of 0.1.0.

---

## Installation

### From the release ZIP

1. Download `wp-ecard-addons.zip` from
   [Releases](https://github.com/Barceloch/WP-ECard-Addons/releases).
2. In WordPress go to **Plugins → Add New → Upload Plugin** and select the ZIP.
3. Activate **ECard - Digital Visiting Card 1.6.x**.
4. Activate **WP ECard Addons**.

The release ZIP is self-contained: production Composer dependencies are already inside
`vendor/`, so no Composer command is needed on the site.

If ECard is missing, the addon loads without errors, shows an admin notice and keeps
every integration disabled.

### From source

Source checkouts do not include `vendor/`. Run these commands inside the plugin
directory before activating it:

```bash
composer install --no-dev --classmap-authoritative
```

`composer.lock` pins `chillerlan/php-qrcode` 4.4.2 and
`chillerlan/php-settings-container` 2.1.6, both MIT licensed.

### Building the release ZIP

```bash
bash scripts/build-release.sh
```

The script stages a clean copy, validates the plugin header, checks that the header
version, `MY_ECARD_VERSION` and the readme `Stable tag` agree, verifies that the Composer
autoloader can load the QR library, excludes tests and development files, creates
`dist/wp-ecard-addons.zip` and verifies the internal structure.

Two consecutive builds of the same sources produce a byte-identical ZIP.

---

## Configuration

Every setting is stored per card under the `_my_ecard_` meta prefix.

| Setting | Values | Default |
| --- | --- | --- |
| `theme` | Any registered theme id, or empty for ECard's own template | empty |
| `show_avatar` | `0` / `1` | `1` |
| `cover_image` | Attachment ID | `0` |
| `header_height` | `140`–`480` px | `220` |
| `accent_color` | `#rrggbb`, or empty to inherit the theme default | empty |
| `show_bar` | `0` / `1` | `1` |
| `bar_position` | `fixed`, `sticky`, `static` | `fixed` |
| `bar_labels` | `show`, `hide`, `hide_mobile` | `show` |
| `enable_vcard`, `enable_location`, `enable_qr`, `enable_share`, `enable_gallery`, `enable_videos` | `0` / `1` | `1` |

`bar_position` follows the CSS behaviour: `fixed` stays at the bottom of the viewport and
the shell reserves room for it, `sticky` sticks inside its container, and `static` flows
after the card content.

---

## FAQ

### Does this plugin replace ECard?

No. ECard keeps managing cards, users and data. The addon only reads that data and adds
its own settings.

### What happens when a card has no addon theme selected?

ECard renders the card exactly as before. The addon does not intercept the template.

### When does the template engine take over?

Only for published, publicly viewable single cards that have a registered addon theme
with a readable template. Drafts, private cards, non-ECard content, archives, feeds, REST
and AJAX requests keep the default WordPress/ECard behaviour.

### What happens to password-protected cards?

When the card has a resolved addon theme, only the core WordPress password form is
rendered and no card data is loaded. ECard 1.6.0 itself has no password handling, so
cards that do not use an addon theme keep that upstream behaviour.

### How do I add a theme?

Create `themes/{id}/theme.json` inside the addon, or register another base directory with
the `my_ecard_theme_directories` filter. The `id` must match the directory name and the
manifest must reference an existing `template.php`. See
[`docs/theme-development.md`](docs/theme-development.md).

### How do I use the Reference theme?

Select `Reference` in the card's addon theme setting. The panoramic cover uses the card
cover image, falls back to the company logo and finally to a decorative gradient, so the
hero never collapses. The avatar is the card photo and can be hidden per card; when it is
hidden no empty space is reserved. Header height is configurable per card.

### How does the action bar work?

The bar is built from the card data by a single shared component and rendered inside the
shell after the theme body. Each action appears only when its card setting is enabled and
the required data exists, so a card without a location or without vCard simply shows
fewer buttons. The stylesheet and script load only on addon-rendered cards. Share first
tries the Web Share API and falls back to copying the card URL to the clipboard,
reporting the result in an `aria-live` status region.

### How do gallery and videos work?

The Gallery and Videos buttons open a shell-owned viewer, not an anchor jump. The image
viewer shows the card gallery with previous/next, thumbnails, a counter, alt text,
Escape/close, arrow keys, focus restoration and touch swipe. The video viewer lists the
card videos, plays YouTube and Vimeo through their embed players, plays direct video
files with a native `<video>` element, and offers a safe external link for other valid
URLs. The card body never renders gallery or video sections: the viewers are the only way
to reach that content, and they do not depend on the theme.

### What is ECard's `.dc-footer`?

`.dc-footer` is ECard's native bottom footer (map, gallery and videos through Venobox). It
remains untouched and is used only when a card has no addon theme. Addon themes never
render it: the shell renders the universal ActionBar and the media viewers instead. The
two paths are mutually exclusive, so a page never shows both bars.

### Why does the Reference theme need Font Awesome?

It reuses the Font Awesome 6.6.0 build that ECard already enqueues on card pages for
uniform contact and social icons. If ECard is inactive the addon does not load, so the
theme is never rendered without its icon font.

### How does the vCard download work?

The public card permalink accepts `?my_ecard_action=vcard`. The addon validates the post
type, public visibility, password gate and the card's `enable_vcard` setting, then sends a
vCard 3.0 file with `Content-Disposition: attachment`. The file is generated on the
server: no JavaScript is involved. Draft, private and password-protected cards return 404.

### Why is the QR code unavailable?

The release ZIP bundles `chillerlan/php-qrcode` 4.4.2. QR rendering additionally requires
the PHP `gd` extension; some hosts disable it. When the library or GD is missing,
`?my_ecard_action=qr` returns HTTP 503 with an explicit message and the shared QR modal
renders nothing, so no broken image is shown. The rest of the addon keeps working. The QR
configuration (mode, size, colors, label) is stored per card and becomes active again as
soon as the requirement is met.

### Does the QR cache refresh when card data changes?

Yes. The transient key includes the card ID, mode, size, colors and a hash of the real
payload. Changing the public URL or any contact field used by contact mode produces a new
key and a fresh image instead of serving a stale QR for twelve hours.

### What happens when I uninstall the plugin?

By default every setting is kept, so reinstalling restores the previous configuration. To
remove addon data on uninstall, opt in explicitly by adding this to `wp-config.php`:

```php
define( 'MY_ECARD_DELETE_DATA_ON_UNINSTALL', true );
```

or by setting the `my_ecard_delete_data` option to `1`. Only addon-owned data is removed:
`_my_ecard_*` post meta, the `my_ecard_version` and `my_ecard_delete_data` options, and
`my_ecard_qr_*` transients. ECard metadata, cards, users and attachments are never
touched.

---

## Development

### Project layout

```text
includes/     plugin classes, helpers and template functions
templates/    document shell, password form and shared parts
themes/       bundled themes (reference)
assets/       CSS and JavaScript for the bar, viewers, QR modal and admin
docs/         theme contract
languages/    text domain location (no translations bundled in 0.1.0)
tests/        unit suite and disposable-WordPress E2E harness
scripts/      deterministic release build
```

### Tests

```bash
php tests/run.php
```

The unit suite needs no dependencies and runs against minimal WordPress stubs. The
end-to-end harness uses Docker with `wordpress:6.8.2-php8.2-apache` and `mariadb:10.11`;
see [`tests/README.md`](tests/README.md).

### Coding constraints

- Stored data and the public PHP API keep the historical `_my_ecard_` and `my_ecard_`
  prefixes. They are a compatibility contract and must not be renamed.
- The text domain is `wp-ecard-addons`.
- PHP 7.4 is the declared minimum; do not introduce newer syntax.

---

## Third-party licenses

| Library | Version | License |
| --- | --- | --- |
| chillerlan/php-qrcode | 4.4.2 | MIT |
| chillerlan/php-settings-container | 2.1.6 | MIT |

Both libraries are distributed inside `vendor/` in the release ZIP. Their license files
are preserved under `vendor/chillerlan/*/LICENSE`.

---

## License

GPL-2.0-or-later. See <https://www.gnu.org/licenses/gpl-2.0.html>.

Copyright (c) Yunior Barceló — <barceloch@gmail.com>
