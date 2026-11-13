<?php
/**
 * ECard dependency detection and admin notices.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects whether ECard is available and supported.
 */
final class Dependencies {

	/**
	 * Lowest supported ECard version.
	 */
	const MIN_ECARD = '1.6.0';

	/**
	 * Highest supported ECard version (exclusive major boundary).
	 */
	const MAX_ECARD = '1.99.99';

	/**
	 * Detected ECard version, or an empty string when ECard is not loaded.
	 *
	 * @var string|null
	 */
	private $ecard_version = null;

	/**
	 * Detected ECard version.
	 *
	 * @return string
	 */
	public function ecard_version(): string {
		if ( null === $this->ecard_version ) {
			$this->ecard_version = defined( 'ECARD_VERSION' ) ? (string) ECARD_VERSION : '';
		}

		return $this->ecard_version;
	}

	/**
	 * Whether ECard is loaded in this request.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return '' !== $this->ecard_version();
	}

	/**
	 * Whether a given ECard version is inside the supported range.
	 *
	 * @param string $version Version to check.
	 * @return bool
	 */
	public function supports_version( string $version ): bool {
		if ( '' === $version ) {
			return false;
		}

		return version_compare( $version, self::MIN_ECARD, '>=' )
			&& version_compare( $version, self::MAX_ECARD, '<=' );
	}

	/**
	 * Whether the active ECard version is supported.
	 *
	 * @return bool
	 */
	public function is_supported(): bool {
		return $this->supports_version( $this->ecard_version() );
	}

	/**
	 * Whether every integration can be enabled safely.
	 *
	 * @return bool
	 */
	public function is_satisfied(): bool {
		return $this->is_active() && $this->is_supported();
	}

	/**
	 * Whether ECard registered its post type.
	 *
	 * Only meaningful after init priority 0.
	 *
	 * @return bool
	 */
	public function is_post_type_registered(): bool {
		return post_type_exists( 'ecard_digital_card' );
	}

	/**
	 * Register the dependency admin notice.
	 *
	 * @return void
	 */
	public function register_admin_notice(): void {
		add_action( 'admin_notices', array( $this, 'render_admin_notice' ) );
	}

	/**
	 * Render the dependency admin notice when integration is not possible.
	 *
	 * @return void
	 */
	public function render_admin_notice(): void {
		if ( $this->is_satisfied() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( ! $this->is_active() ) {
			$message = __( 'WP ECard Addons requires ECard - Digital Visiting Card to be installed and active. Card themes, QR codes, vCards and the action bar stay disabled until you activate ECard.', 'wp-ecard-addons' );
		} else {
			$message = sprintf(
				/* translators: %s: detected ECard version. */
				__( 'WP ECard Addons supports ECard 1.6.x. Detected version: %s. The addon stays disabled until a supported ECard version is active.', 'wp-ecard-addons' ),
				$this->ecard_version()
			);
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html( $message )
		);
	}
}
