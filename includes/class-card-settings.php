<?php
/**
 * Per-card settings: storage, sanitization and admin UI.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads, validates and renders the addon settings for one card.
 */
final class CardSettings {

	/**
	 * Meta key prefix.
	 */
	const PREFIX = '_my_ecard_';

	/**
	 * Nonce action.
	 */
	const NONCE_ACTION = 'my_ecard_save_settings';

	/**
	 * Nonce field name.
	 */
	const NONCE_NAME = 'my_ecard_settings_nonce';

	/**
	 * Form field group name.
	 */
	const FIELD = 'my_ecard';

	/**
	 * Meta box id.
	 */
	const META_BOX = 'my_ecard_settings';

	/**
	 * Theme registry.
	 *
	 * @var ThemeRegistry
	 */
	private $themes;

	/**
	 * Constructor.
	 *
	 * @param ThemeRegistry $themes Theme registry.
	 */
	public function __construct( ThemeRegistry $themes ) {
		$this->themes = $themes;
	}

	/**
	 * Default values for every setting.
	 *
	 * @return array
	 */
	public static function defaults(): array {
		return array(
			'theme'           => '',
			'show_avatar'     => 1,
			'cover_image'     => 0,
			'header_height'   => 220,
			'accent_color'    => '',
			'show_bar'        => 1,
			'bar_position'    => 'fixed',
			'bar_labels'      => 'show',
			'enable_qr'       => 1,
			'qr_mode'         => 'card',
			'qr_size'         => 220,
			'qr_dark'         => '#0f172a',
			'qr_light'        => '#ffffff',
			'qr_label'        => 1,
			'enable_vcard'    => 1,
			'enable_share'    => 1,
			'enable_location' => 1,
			'enable_gallery'  => 1,
			'enable_videos'   => 1,
		);
	}

	/**
	 * Boolean setting keys.
	 *
	 * @return string[]
	 */
	private static function boolean_keys(): array {
		return array(
			'show_avatar',
			'show_bar',
			'qr_label',
			'enable_qr',
			'enable_vcard',
			'enable_share',
			'enable_location',
			'enable_gallery',
			'enable_videos',
		);
	}

	/**
	 * Full meta key for a setting.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	public static function meta_key( string $key ): string {
		return self::PREFIX . $key;
	}

	/**
	 * Read every setting for a card, applying defaults and revalidation.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function read( int $post_id ): array {
		$defaults = self::defaults();
		$values   = array();

		foreach ( $defaults as $key => $default ) {
			$stored = get_post_meta( $post_id, self::meta_key( $key ), true );

			$values[ $key ] = self::normalize_stored( $key, $stored, $default );
		}

		return $values;
	}

	/**
	 * Normalize a stored value coming from the database.
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $stored   Stored value.
	 * @param mixed  $default  Default value.
	 * @return mixed
	 */
	private static function normalize_stored( string $key, $stored, $default ) {
		if ( null === $stored || '' === $stored || is_array( $stored ) || is_object( $stored ) ) {
			return $default;
		}

		if ( in_array( $key, self::boolean_keys(), true ) ) {
			return (int) ( (int) $stored > 0 );
		}

		switch ( $key ) {
			case 'cover_image':
				return max( 0, (int) $stored );
			case 'header_height':
				return clamp_int( $stored, 140, 480, (int) $default );
			case 'qr_size':
				return clamp_int( $stored, 96, 512, (int) $default );
			case 'qr_dark':
			case 'qr_light':
				return hex_color( $stored, (string) $default );
			case 'accent_color':
				return hex_color( $stored, (string) $default );
			case 'bar_position':
				return choice( $stored, array( 'fixed', 'sticky', 'static' ), (string) $default );
			case 'bar_labels':
				return choice( $stored, array( 'show', 'hide', 'hide_mobile' ), (string) $default );
			case 'qr_mode':
				return choice( $stored, array( 'card', 'contact' ), (string) $default );
			case 'theme':
				return is_string( $stored ) ? sanitize_key( $stored ) : '';
		}

		return is_scalar( $stored ) ? (string) $stored : $default;
	}

	/**
	 * Validate raw form input.
	 *
	 * @param array    $raw       Unslashed form input.
	 * @param string[] $theme_ids Allowed theme ids.
	 * @return array
	 */
	public static function sanitize( array $raw, array $theme_ids ): array {
		$defaults = self::defaults();
		$clean    = $defaults;

		$theme            = isset( $raw['theme'] ) && is_string( $raw['theme'] ) ? sanitize_key( $raw['theme'] ) : '';
		$clean['theme']   = in_array( $theme, $theme_ids, true ) ? $theme : '';
		$clean['cover_image'] = self::attachment_id( $raw, 'cover_image' );

		$clean['header_height'] = clamp_int( isset( $raw['header_height'] ) ? $raw['header_height'] : '', 140, 480, (int) $defaults['header_height'] );
		$clean['qr_size']       = clamp_int( isset( $raw['qr_size'] ) ? $raw['qr_size'] : '', 96, 512, (int) $defaults['qr_size'] );

		$clean['accent_color'] = self::optional_hex( isset( $raw['accent_color'] ) ? $raw['accent_color'] : '' );
		$clean['qr_dark']      = hex_color( isset( $raw['qr_dark'] ) ? $raw['qr_dark'] : '', (string) $defaults['qr_dark'] );
		$clean['qr_light']     = hex_color( isset( $raw['qr_light'] ) ? $raw['qr_light'] : '', (string) $defaults['qr_light'] );

		$clean['bar_position'] = choice( isset( $raw['bar_position'] ) ? $raw['bar_position'] : '', array( 'fixed', 'sticky', 'static' ), (string) $defaults['bar_position'] );
		$clean['bar_labels']   = choice( isset( $raw['bar_labels'] ) ? $raw['bar_labels'] : '', array( 'show', 'hide', 'hide_mobile' ), (string) $defaults['bar_labels'] );
		$clean['qr_mode']      = choice( isset( $raw['qr_mode'] ) ? $raw['qr_mode'] : '', array( 'card', 'contact' ), (string) $defaults['qr_mode'] );

		foreach ( self::boolean_keys() as $key ) {
			$clean[ $key ] = empty( $raw[ $key ] ) ? 0 : 1;
		}

		return $clean;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_ecard_digital_card', array( $this, 'save' ), 20, 2 );
	}

	/**
	 * Register the settings meta box.
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		add_meta_box(
			self::META_BOX,
			__( 'WP ECard Addons', 'wp-ecard-addons' ),
			array( $this, 'render' ),
			'ecard_digital_card',
			'normal',
			'default'
		);
	}

	/**
	 * Persist settings from the card edit form.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save( int $post_id, $post ): void {
		if ( ! $post instanceof \WP_Post || 'ecard_digital_card' !== $post->post_type ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) );

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw = array();

		if ( isset( $_POST[ self::FIELD ] ) && is_array( $_POST[ self::FIELD ] ) ) {
			// Sanitized field by field in sanitize().
			$raw = wp_unslash( $_POST[ self::FIELD ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		$clean    = self::sanitize( $raw, array_keys( $this->themes->all() ) );
		$defaults = self::defaults();

		foreach ( $clean as $key => $value ) {
			$meta_key = self::meta_key( $key );

			if ( $value === $defaults[ $key ] ) {
				delete_post_meta( $post_id, $meta_key );
				continue;
			}

			update_post_meta( $post_id, $meta_key, $value );
		}
	}

	/**
	 * Render the settings meta box.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render( $post ): void {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$values = self::read( $post->ID );
		$themes = $this->themes->choices();
		$errors = $this->themes->errors();

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<div class="my-ecard-settings">
			<?php if ( array() === $themes ) : ?>
				<p class="my-ecard-empty">
					<?php esc_html_e( 'No addon themes are registered yet. Add a theme directory containing a theme.json manifest to make it selectable.', 'wp-ecard-addons' ); ?>
				</p>
			<?php endif; ?>

			<?php if ( array() !== $errors && current_user_can( 'manage_options' ) ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'Some theme manifests could not be loaded:', 'wp-ecard-addons' ); ?></p>
					<ul>
						<?php foreach ( $errors as $error ) : ?>
							<li><?php echo esc_html( $error ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<div class="my-ecard-section">
				<h3><?php esc_html_e( 'Appearance', 'wp-ecard-addons' ); ?></h3>

				<?php
				$this->render_theme_selector( $values, $post->ID );
				$this->field_checkbox( 'show_avatar', __( 'Show avatar', 'wp-ecard-addons' ), __( 'Uses the ECard person photo as a circular avatar over the cover.', 'wp-ecard-addons' ), $values );
				$this->render_cover_field( $values );
				$this->field_number( 'header_height', __( 'Cover height (px)', 'wp-ecard-addons' ), 140, 480, $values, __( 'Height of the panoramic cover on desktop and mobile.', 'wp-ecard-addons' ) );
				$this->field_text( 'accent_color', __( 'Accent color', 'wp-ecard-addons' ), $values, __( 'Optional. Use a #rrggbb value, or leave empty to inherit the theme default.', 'wp-ecard-addons' ), '#0f172a' );
				?>

				<fieldset class="my-ecard-group">
					<legend><?php esc_html_e( 'Action bar', 'wp-ecard-addons' ); ?></legend>
					<?php
					$this->field_checkbox( 'show_bar', __( 'Show action bar', 'wp-ecard-addons' ), '', $values );
					$this->field_select(
						'bar_position',
						__( 'Position', 'wp-ecard-addons' ),
						array(
							'fixed'  => __( 'Fixed to bottom', 'wp-ecard-addons' ),
							'sticky' => __( 'Sticky to viewport', 'wp-ecard-addons' ),
							'static' => __( 'After card content', 'wp-ecard-addons' ),
						),
						$values
					);
					$this->field_select(
						'bar_labels',
						__( 'Labels', 'wp-ecard-addons' ),
						array(
							'show'        => __( 'Show labels', 'wp-ecard-addons' ),
							'hide'        => __( 'Hide labels', 'wp-ecard-addons' ),
							'hide_mobile' => __( 'Hide labels on mobile', 'wp-ecard-addons' ),
						),
						$values
					);
					?>
				</fieldset>
			</div>

			<div class="my-ecard-section">
				<h3><?php esc_html_e( 'Actions', 'wp-ecard-addons' ); ?></h3>
				<p class="description"><?php esc_html_e( 'A button is shown only when the feature is enabled here and the card has real data for it.', 'wp-ecard-addons' ); ?></p>
				<?php
				$this->field_checkbox( 'enable_vcard', __( 'Download contact (vCard)', 'wp-ecard-addons' ), '', $values );
				$this->field_checkbox( 'enable_location', __( 'Location', 'wp-ecard-addons' ), '', $values );
				$this->field_checkbox( 'enable_qr', __( 'QR code', 'wp-ecard-addons' ), '', $values );
				$this->field_checkbox( 'enable_share', __( 'Share', 'wp-ecard-addons' ), '', $values );
				$this->field_checkbox( 'enable_gallery', __( 'Gallery', 'wp-ecard-addons' ), __( 'The button appears only when the selected theme renders the my-ecard-gallery section and the card has images.', 'wp-ecard-addons' ), $values );
				$this->field_checkbox( 'enable_videos', __( 'Videos', 'wp-ecard-addons' ), __( 'The button appears only when the selected theme renders the my-ecard-videos section and the card has videos.', 'wp-ecard-addons' ), $values );
				?>
			</div>

			<div class="my-ecard-section" data-my-ecard-qr-panel>
				<h3><?php esc_html_e( 'QR code', 'wp-ecard-addons' ); ?></h3>
				<?php if ( ! QRCodeGenerator::is_available() ) : ?>
					<div class="notice notice-info inline" role="status"><p>
						<?php
						if ( 'gd' === QRCodeGenerator::unavailable_reason() ) {
							esc_html_e( 'QR rendering is unavailable because the PHP GD extension is not enabled on this server. The QR action stays hidden on the card; these settings are kept for when GD becomes available.', 'wp-ecard-addons' );
						} else {
							esc_html_e( 'QR rendering is unavailable until the optional composer dependency is installed. The QR action stays hidden on the card; these settings are kept for when it becomes available.', 'wp-ecard-addons' );
						}
						?>
					</p></div>
				<?php endif; ?>
				<?php
				$this->field_select(
					'qr_mode',
					__( 'Content', 'wp-ecard-addons' ),
					array(
						'card'    => __( 'Public card URL', 'wp-ecard-addons' ),
						'contact' => __( 'Contact data (vCard payload)', 'wp-ecard-addons' ),
					),
					$values
				);
				$this->field_number( 'qr_size', __( 'Display size (px)', 'wp-ecard-addons' ), 96, 512, $values, '' );
				$this->field_text( 'qr_dark', __( 'Dark color', 'wp-ecard-addons' ), $values, '', '#0f172a' );
				$this->field_text( 'qr_light', __( 'Background color', 'wp-ecard-addons' ), $values, '', '#ffffff' );
				$this->field_checkbox( 'qr_label', __( 'Show label under the QR code', 'wp-ecard-addons' ), '', $values );
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the theme selector cards.
	 *
	 * @param array $values  Current settings.
	 * @param int   $post_id Post ID.
	 * @return void
	 */
	private function render_theme_selector( array $values, int $post_id ): void {
		$themes   = $this->themes->all();
		$selected = isset( $values['theme'] ) && $this->themes->has( (string) $values['theme'] ) ? (string) $values['theme'] : '';
		$stored   = (string) get_post_meta( $post_id, self::meta_key( 'theme' ), true );
		?>
		<?php if ( '' !== $stored && ! $this->themes->has( $stored ) ) : ?>
			<div class="notice notice-warning inline my-ecard-theme-warning" role="status">
				<p><?php esc_html_e( 'The theme saved for this card is no longer available. The card will use the ECard default template until you select a registered theme.', 'wp-ecard-addons' ); ?></p>
			</div>
		<?php endif; ?>

		<fieldset class="my-ecard-theme-selector">
			<legend><?php esc_html_e( 'Theme', 'wp-ecard-addons' ); ?></legend>
			<p class="description"><?php esc_html_e( 'Only registered themes are listed. Leaving the default keeps ECard rendering untouched.', 'wp-ecard-addons' ); ?></p>

			<div class="my-ecard-theme-grid">
				<label class="my-ecard-theme-card">
					<input type="radio" name="<?php echo esc_attr( self::FIELD ); ?>[theme]" value="" <?php checked( $selected, '' ); ?> />
					<span class="my-ecard-theme-card__inner">
						<span class="my-ecard-theme-card__media my-ecard-theme-card__media--empty">
							<span class="dashicons dashicons-admin-appearance" aria-hidden="true"></span>
						</span>
						<span class="my-ecard-theme-card__content">
							<span class="my-ecard-theme-card__name"><?php esc_html_e( 'ECard default (no addon theme)', 'wp-ecard-addons' ); ?></span>
							<span class="my-ecard-theme-card__description"><?php esc_html_e( 'The card keeps the original ECard template.', 'wp-ecard-addons' ); ?></span>
							<span class="my-ecard-theme-card__badge"><?php esc_html_e( 'Selected', 'wp-ecard-addons' ); ?></span>
						</span>
					</span>
				</label>

				<?php foreach ( $themes as $id => $theme ) : ?>
					<?php
					$id          = (string) $id;
					$preview_url = isset( $theme['preview_url'] ) ? (string) $theme['preview_url'] : '';
					$name        = isset( $theme['name'] ) ? (string) $theme['name'] : $id;
					$version     = isset( $theme['version'] ) ? (string) $theme['version'] : '';
					$description = isset( $theme['description'] ) ? (string) $theme['description'] : '';
					?>
					<label class="my-ecard-theme-card">
						<input type="radio" name="<?php echo esc_attr( self::FIELD ); ?>[theme]" value="<?php echo esc_attr( $id ); ?>" <?php checked( $id, $selected ); ?> />
						<span class="my-ecard-theme-card__inner">
							<span class="my-ecard-theme-card__media<?php echo '' === $preview_url ? ' my-ecard-theme-card__media--empty' : ''; ?>">
								<?php if ( '' !== $preview_url ) : ?>
									<img src="<?php echo esc_url( $preview_url ); ?>" alt="" loading="lazy" />
								<?php else : ?>
									<span class="dashicons dashicons-format-image" aria-hidden="true"></span>
								<?php endif; ?>
							</span>
							<span class="my-ecard-theme-card__content">
								<span class="my-ecard-theme-card__name">
									<?php echo esc_html( $name ); ?>
									<?php if ( '' !== $version ) : ?>
										<span class="my-ecard-theme-card__version"><?php echo esc_html( 'v' . $version ); ?></span>
									<?php endif; ?>
								</span>
								<?php if ( '' !== $description ) : ?>
									<span class="my-ecard-theme-card__description"><?php echo esc_html( $description ); ?></span>
								<?php endif; ?>
								<span class="my-ecard-theme-card__badge"><?php esc_html_e( 'Selected', 'wp-ecard-addons' ); ?></span>
							</span>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Render the cover image picker.
	 *
	 * @param array $values Current settings.
	 * @return void
	 */
	private function render_cover_field( array $values ): void {
		$cover_id  = (int) $values['cover_image'];
		$cover_url = $cover_id > 0 ? (string) wp_get_attachment_image_url( $cover_id, 'medium' ) : '';
		?>
		<div class="my-ecard-field my-ecard-cover">
			<span class="my-ecard-label"><?php esc_html_e( 'Cover photo', 'wp-ecard-addons' ); ?></span>
			<input type="hidden" id="my-ecard-cover_image" name="<?php echo esc_attr( self::FIELD ); ?>[cover_image]" value="<?php echo esc_attr( (string) $cover_id ); ?>" />
			<span class="my-ecard-cover-preview" data-my-ecard-cover-preview <?php echo '' === $cover_url ? 'hidden' : ''; ?>>
				<img src="<?php echo esc_url( $cover_url ); ?>" alt="" />
			</span>
			<button
				type="button"
				class="button"
				data-my-ecard-cover-choose
				data-title="<?php esc_attr_e( 'Select cover photo', 'wp-ecard-addons' ); ?>"
				data-button="<?php esc_attr_e( 'Use photo', 'wp-ecard-addons' ); ?>"
			><?php esc_html_e( 'Select cover photo', 'wp-ecard-addons' ); ?></button>
			<button type="button" class="button-link" data-my-ecard-cover-clear><?php esc_html_e( 'Remove', 'wp-ecard-addons' ); ?></button>
			<span class="description"><?php esc_html_e( 'Panoramic photo used by themes that support a cover. It is independent from the ECard avatar.', 'wp-ecard-addons' ); ?></span>
		</div>
		<?php
	}

	/**
	 * Render a checkbox field.
	 *
	 * @param string $key         Setting key.
	 * @param string $label       Label.
	 * @param string $description Help text.
	 * @param array  $values      Current settings.
	 * @return void
	 */
	private function field_checkbox( string $key, string $label, string $description, array $values ): void {
		printf(
			'<p class="my-ecard-field"><label for="my-ecard-%1$s"><input type="checkbox" id="my-ecard-%1$s" name="%2$s[%1$s]" value="1" %3$s /> %4$s</label>%5$s</p>',
			esc_attr( $key ),
			esc_attr( self::FIELD ),
			checked( 1, (int) $values[ $key ], false ),
			esc_html( $label ),
			'' === $description ? '' : '<span class="description">' . esc_html( $description ) . '</span>'
		);
	}

	/**
	 * Render a select field.
	 *
	 * @param string $key         Setting key.
	 * @param string $label       Label.
	 * @param array  $options     value => label pairs.
	 * @param array  $values      Current settings.
	 * @param string $description Help text.
	 * @return void
	 */
	private function field_select( string $key, string $label, array $options, array $values, string $description = '' ): void {
		$current = isset( $values[ $key ] ) ? (string) $values[ $key ] : '';
		?>
		<p class="my-ecard-field">
			<label for="my-ecard-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			<select id="my-ecard-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::FIELD ); ?>[<?php echo esc_attr( $key ); ?>]">
				<?php foreach ( $options as $value => $option_label ) : ?>
					<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $current, (string) $value ); ?>><?php echo esc_html( $option_label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php if ( '' !== $description ) : ?>
				<span class="description"><?php echo esc_html( $description ); ?></span>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Render a number field.
	 *
	 * @param string $key         Setting key.
	 * @param string $label       Label.
	 * @param int    $min         Minimum.
	 * @param int    $max         Maximum.
	 * @param array  $values      Current settings.
	 * @param string $description Help text.
	 * @return void
	 */
	private function field_number( string $key, string $label, int $min, int $max, array $values, string $description = '' ): void {
		printf(
			'<p class="my-ecard-field"><label for="my-ecard-%1$s">%2$s</label><input type="number" id="my-ecard-%1$s" name="%3$s[%1$s]" value="%4$d" min="%5$d" max="%6$d" step="1" />%7$s</p>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( self::FIELD ),
			(int) $values[ $key ],
			(int) $min,
			(int) $max,
			'' === $description ? '' : '<span class="description">' . esc_html( $description ) . '</span>'
		);
	}

	/**
	 * Render a text field.
	 *
	 * @param string $key         Setting key.
	 * @param string $label       Label.
	 * @param array  $values      Current settings.
	 * @param string $description Help text.
	 * @param string $placeholder Placeholder.
	 * @return void
	 */
	private function field_text( string $key, string $label, array $values, string $description = '', string $placeholder = '' ): void {
		printf(
			'<p class="my-ecard-field"><label for="my-ecard-%1$s">%2$s</label><input type="text" id="my-ecard-%1$s" name="%3$s[%1$s]" value="%4$s" placeholder="%5$s" />%6$s</p>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( self::FIELD ),
			esc_attr( (string) $values[ $key ] ),
			esc_attr( $placeholder ),
			'' === $description ? '' : '<span class="description">' . esc_html( $description ) . '</span>'
		);
	}

	/**
	 * Validate an optional hex color.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function optional_hex( $value ): string {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';

		if ( '' === $value ) {
			return '';
		}

		$color = sanitize_hex_color( $value );

		return is_string( $color ) ? $color : '';
	}

	/**
	 * Validate an attachment id and require an image attachment.
	 *
	 * @param array  $raw Raw input.
	 * @param string $key Field key.
	 * @return int
	 */
	private static function attachment_id( array $raw, string $key ): int {
		$id = isset( $raw[ $key ] ) ? absint( $raw[ $key ] ) : 0;

		if ( $id <= 0 || ! wp_attachment_is_image( $id ) ) {
			return 0;
		}

		return $id;
	}
}
