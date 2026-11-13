# Translations

This directory is the text domain location declared by the plugin header
(`Domain Path: /languages`) and by `load_plugin_textdomain()`.

Version 0.1.0 ships **no** translation files. There is no `.pot`, `.po` or `.mo`
catalogue in this release, and no translation is claimed to exist.

## Text domain

`wp-ecard-addons`

## Adding a translation

1. Generate a catalogue from the source with WP-CLI:

   ```
   wp i18n make-pot . languages/wp-ecard-addons.pot
   ```

2. Translate the catalogue and save it as
   `languages/wp-ecard-addons-{locale}.po`.

3. Compile it to `.mo`:

   ```
   wp i18n make-mo languages
   ```

4. Keep the text domain in every translation call equal to `wp-ecard-addons`.

Only the compiled `.mo` file is required at runtime. The `.pot` and `.po` files
are source artefacts and are not needed on a production site.

## Note on identifiers

The text domain is `wp-ecard-addons`. Stored data and the public PHP API keep the
historical `_my_ecard_` and `my_ecard_` prefixes for backward compatibility; those
prefixes are not translation identifiers and must not be renamed.
