=== WP ECard Addons ===
Contributors: barceloch
Tags: ecard, digital business card, qr code, vcard, themes
Requires at least: 6.5
Tested up to: 6.8.2
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Themes, QR codes, vCard downloads and an action bar for ECard - Digital Visiting Card.

== Requirements ==

* WordPress 6.5 or newer.
* PHP 7.4 or newer.
* ECard - Digital Visiting Card 1.6.x (active).
* PHP `mbstring` (required by the bundled QR library).
* PHP `gd` for QR image rendering. Without GD the rest of the addon works and QR
  stays hidden with a controlled HTTP 503 response.

= Compatibility status =

This release distinguishes what was actually executed from what is only declared.

* WordPress 6.5.12: **executed**. The release ZIP was installed on WordPress 6.5.12
  with ECard 1.6.0, and the Reference card, the action bar, the QR endpoint and the
  vCard download were verified without PHP notices.
* WordPress 6.8.2: **executed**. The end-to-end suite installs and exercises the
  release ZIP on WordPress 6.8.2. This is the version named by `Tested up to`.
* PHP 8.2: **executed**. The unit and end-to-end suites run on PHP 8.2.
* PHP 7.4 and PHP 8.1: **declared minimum and static compatibility only**. The shipped
  code contains no syntax newer than PHP 7.4 and `composer.json` pins
  `config.platform.php` to 7.4.33, but neither version was executed for this release.
  They are not tested configurations.
* ECard 1.6.0: **executed**. The addon accepts the whole 1.6.x range, but only 1.6.0
  was installed and verified. Other 1.6.x releases are supported by declaration, not
  by test.

== Description ==

WP ECard Addons is an independent addon for ECard - Digital Visiting Card 1.6.x.
It keeps ECard as the card management engine and adds visual themes, an action bar,
QR codes and vCard downloads on top of the existing data.

ECard is not modified: the addon reads its metadata, registers its own `_my_ecard_`
metadata and replaces the public card template only when a card explicitly selects a
registered addon theme.

= Current status (0.1.0) =

This release contains the addon base, the front-end template engine and the Reference
theme:

* Dependency detection for ECard 1.6.x with an admin notice and safe degradation.
* Extensible theme registry based on `themes/{id}/theme.json` manifests, with optional
  preview images and declared gallery/videos support.
* Per-card settings storage and admin UI using the `_my_ecard_` prefix.
* A native theme card selector with name, description, preview, selected state and an
  accessible warning when a stored theme is no longer registered.
* `CardData`, the single normalization layer for ECard metadata.
* Template engine: `template_include` at priority 20 renders registered addon themes
  inside an autonomous document shell, with an explicit fallback to the ECard template.
* Theme assets: manifest `styles` and `scripts` are enqueued for the resolved theme.
* Reference theme: panoramic cover with configurable height, optional overlapping
  avatar, centered identity, circular phone/email/WhatsApp buttons and a brand-colored
  social grid. Gallery and video content is never rendered inside the card: the action
  bar opens the independent viewers instead.
* Universal action bar rendered by the document shell for every addon theme: vCard,
  location, share, gallery and videos, plus QR when the renderer is available.
  Gallery and videos open shared media viewers built from the card data, independent of
  the theme. `bar_position` supports fixed, sticky and static; `bar_labels` supports
  show, hide and hide_mobile. ECard's `.dc-footer` remains the fallback for cards
  without an addon theme.
* vCard 3.0 downloads: server-rendered file with UTF-8, escaping, 75-octet folding,
  safe filename and HTTP headers, served from a public card endpoint.
* QR service: payload, options, payload-aware cache key and a fail-closed endpoint.
  The production ZIP bundles `chillerlan/php-qrcode` 4.4.2 in `vendor/`, so QR works
  out of the box when GD is enabled.
* Theme contract in `docs/theme-development.md`: manifest, template, assets, card data,
  partials, shell, action bar, section anchors, external directories and filters.
* Controlled uninstall: data is kept by default and can be removed only when the site
  explicitly opts in, as documented below.

The action bar is functional: it renders real links and buttons, and the share action
falls back to the clipboard when the Web Share API is unavailable. The Modern, Minimal
and Corporate themes are not part of 0.1.0.

== Installation ==

1. Install `dist/wp-ecard-addons.zip` through Plugins > Add New > Upload Plugin,
   or copy the `wp-ecard-addons` folder to `/wp-content/plugins/`.
2. Activate ECard - Digital Visiting Card 1.6.x.
3. Activate WP ECard Addons.

The release ZIP is self-contained: production Composer dependencies are already inside
`vendor/`, so no Composer command is needed on the site.

If ECard is missing, the addon loads without errors, shows an admin notice and keeps
every integration disabled.

= Installing from source =

Source checkouts do not include `vendor/`. Run these commands inside the plugin
directory before activating it:

`composer install --no-dev --classmap-authoritative`

`composer.lock` pins `chillerlan/php-qrcode` 4.4.2 and
`chillerlan/php-settings-container` 2.1.6, both MIT licensed.

= Building the release ZIP =

Run `scripts/build-release.sh`. The script stages a clean copy, validates the plugin
header and every shipped PHP file, verifies that the Composer autoloader can load the
QR library, excludes tests and development files, creates
`dist/wp-ecard-addons.zip`, and verifies the internal structure.

== Frequently Asked Questions ==

= Does this plugin replace ECard? =

No. ECard keeps managing cards, users and data. The addon only reads that data and
adds its own settings.

= What happens when a card has no addon theme selected? =

ECard renders the card exactly as before. The addon does not intercept the template.

= When does the template engine take over? =

Only for published, publicly viewable single cards that have a registered addon theme
with a readable template. Drafts, private cards, non-ECard content, archives, feeds,
REST and AJAX requests keep the default WordPress/ECard behaviour.

= What happens to password-protected cards? =

When the card has a resolved addon theme, only the core WordPress password form is
rendered and no card data is loaded. ECard 1.6.0 itself has no password handling, so
cards that do not use an addon theme keep that upstream behaviour.

= How do I add a theme? =

Create `themes/{id}/theme.json` inside the addon, or register another base directory
with the `my_ecard_theme_directories` filter. The `id` must match the directory name
and the manifest must reference an existing `template.php`.

= How do I use the Reference theme? =

Select `Reference` in the card's addon theme setting. The panoramic cover uses the
card cover image, falls back to the company logo and finally to a decorative gradient,
so the hero never collapses. The avatar is the card photo and can be hidden per card;
when it is hidden no empty space is reserved. Header height is configurable per card.

= Does the bottom action bar work? =

Yes. The document shell renders one shared bar for every addon theme instead of each
theme duplicating it. The bar shows the actions the card supports: vCard, location,
share, gallery and videos, plus QR when the renderer and GD are available.
Gallery and videos open shared media viewers built from the card data, so they work with
any theme that uses the bar. `bar_position` accepts fixed, sticky and static.
`bar_labels` accepts show, hide and hide_mobile.

= How does the action bar work? =

The bar is built from the card data by a single shared component and rendered inside
the shell after the theme body. Each action appears only when its card setting is
enabled and the required data exists, so a card without a location or without vCard
simply shows fewer buttons. `bar_position` controls the fixed, sticky or static layout;
`bar_labels` shows labels, hides them, or hides them only on mobile. Its stylesheet and
script are loaded only on addon-rendered cards. Share first tries the Web Share API and
falls back to copying the card URL to the clipboard, reporting the result in an
`aria-live` status region.

= How do gallery and videos work? =

The Gallery and Videos buttons open a shell-owned viewer, not an anchor jump. The image
viewer shows the card gallery with previous/next, thumbnails, a counter, alt text,
Escape/close, arrow keys, focus restoration and touch swipe. The video viewer lists the
card videos, plays YouTube and Vimeo through their embed players, plays direct video
files with a native `<video>` element, and offers a safe external link for other valid
URLs. The card body never renders gallery or video sections: the viewers are the only
way to reach that content, and they do not depend on the theme.

= What is ECard's .dc-footer? =

`.dc-footer` is ECard's native bottom footer (map, gallery and videos through Venobox).
It remains untouched and is used only when a card has no addon theme. Addon themes never
render it: the shell renders the universal ActionBar and the media viewers instead. The
two paths are mutually exclusive, so a page never shows both bars.

= Why does the Reference theme need Font Awesome? =

It reuses the Font Awesome 6.6.0 build that ECard already enqueues on card pages for
uniform contact and social icons. If ECard is inactive the addon does not load, so the
theme is never rendered without its icon font.

= How does the vCard download work? =

The public card permalink accepts `?my_ecard_action=vcard`. The addon validates the post
type, public visibility, password gate and the card's `enable_vcard` setting, then sends
a vCard 3.0 file with `Content-Disposition: attachment`. The file is generated on the
server: no JavaScript is involved. Draft, private and password-protected cards return
404.

= Why is the QR code unavailable? =

The release ZIP bundles `chillerlan/php-qrcode` 4.4.2. QR rendering additionally
requires the PHP `gd` extension; some hosts disable it. When the library or GD is
missing, `?my_ecard_action=qr` returns HTTP 503 with an explicit message and the shared
QR modal renders nothing, so no broken image is shown. The rest of the addon keeps
working. The QR configuration (mode, size, colors, label) is stored per card and
becomes active again as soon as the requirement is met.

= Does the QR cache refresh when card data changes? =

Yes. The transient key includes the card ID, mode, size, colors and a hash of the real
payload. Changing the public URL or any contact field used by contact mode produces a
new key and a fresh image instead of serving a stale QR for twelve hours.

= What happens when I uninstall the plugin? =

By default every setting is kept, so reinstalling restores the previous configuration.
To remove addon data on uninstall, opt in explicitly by adding this to `wp-config.php`:

`define( 'MY_ECARD_DELETE_DATA_ON_UNINSTALL', true );`

or by setting the `my_ecard_delete_data` option to `1`. Only addon-owned data is
removed: `_my_ecard_*` post meta, the `my_ecard_version` and `my_ecard_delete_data`
options, and `my_ecard_qr_*` transients. ECard metadata, cards, users and attachments
are never touched.

== Third-party licenses ==

* chillerlan/php-qrcode 4.4.2 - MIT License.
* chillerlan/php-settings-container 2.1.6 - MIT License.

Both libraries are distributed inside `vendor/` in the release ZIP. Their license files
are preserved under `vendor/chillerlan/*/LICENSE`.

== Changelog ==

= 0.1.0 =
* First public release under the final identity **WP ECard Addons** (slug
  `wp-ecard-addons`, main file `wp-ecard-addons.php`, text domain
  `wp-ecard-addons`). Stored `_my_ecard_` meta, the `my_ecard_*` PHP API, hooks,
  filters and transients keep their original names, so existing data and
  integrations are unaffected.
* Minimum WordPress lowered to 6.5, verified on WordPress 6.5.12.
* Asset cache busting uses the plugin version instead of file timestamps, so a
  release update invalidates browser CSS and JavaScript caches correctly.
* Addon base: dependency detection, theme registry, per-card settings, CardData.
* Template engine: `template_include` priority 20, autonomous document shell,
  password gate, explicit fallback to the ECard template and a `my_ecard_render_context`
  filter.
* Theme assets: manifest-declared `styles` and `scripts` are enqueued for the resolved
  theme, restricted to files inside the addon directory.
* Reference theme: panoramic cover with configurable height and `object-fit: cover`,
  optional circular avatar with no reserved gap, centered identity, functional
  `tel:`/`mailto:`/WhatsApp buttons, brand-colored social grid and isolated stylesheet.
* Shared action bar rendered by the shell for every addon theme: vCard, location and
  share actions with per-action availability driven by card data and settings.
* Share fallbacks: Web Share API when available, clipboard copy otherwise, with an
  accessible `aria-live` status region for the result.
* QR availability filter: the QR action and dialog stay hidden while the optional
  `chillerlan/php-qrcode` dependency is missing.
* Sticky bar position: `bar_position` supports fixed, sticky and static, with
  `bar_labels` offering show, hide and hide_mobile.
* Action bar asset loading: `action-bar.css` and `action-bar.js` are enqueued only on
  addon-rendered cards that display the bar.
* vCard 3.0 service: `VCardGenerator` with UTF-8, escaping, 75-octet folding, safe
  filename and download headers, exposed through a validated `?my_ecard_action=vcard`
  endpoint.
* QR service: `QRCodeGenerator` with card/contact payloads, size and color options,
  cache keys, a 12-hour transient cache and a `?my_ecard_action=qr` endpoint that fails
  closed with HTTP 503 until the optional `chillerlan/php-qrcode` dependency is
  installed.
* Shared QR modal part and public URL helpers (`my_ecard_vcard_url()`,
  `my_ecard_qr_url()`, `my_ecard_qr_modal()`) available to any theme.
* Native theme card selector with name, description, optional preview, selected state,
  default option and an accessible warning when a stored theme disappears.
* Theme manifests support optional preview images (`preview` / `preview_url`) and
  declare gallery/videos support.
* Reference gallery and video content stays in `CardData` and is reached only through
  the action bar viewers; the theme renders no in-card media section.
* Shared gallery viewer: full image, thumbnails, previous/next, counter, alt/title text,
  Escape/close, arrow keys, focus restore and touch swipe.
* Shared video viewer: video list, previous/next, counter, YouTube/Vimeo embeds, direct
  video files and a safe external link for other valid URLs, with playback cleanup on
  close.
* Theme contract documented in `docs/theme-development.md`, including the ECard
  `.dc-footer` fallback and the shell-owned ActionBar/media viewers.
* Distribution: `composer.lock` pins `chillerlan/php-qrcode` 4.4.2, the plugin loads
  `vendor/autoload.php` when present, and `scripts/build-release.sh` produces a
  self-contained ZIP with production dependencies.
* QR stability: payload-aware cache keys, GD detection with a controlled 503, and a
  renderer adapter written for the 4.x API.
* Safe uninstall: `uninstall.php` keeps data by default and removes only addon-owned
  data when the site opts in.
