<?php
/**
 * Plugin bootstrap.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires the addon only when ECard is available and supported.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Dependency detector.
	 *
	 * @var Dependencies
	 */
	private $dependencies;

	/**
	 * Theme registry.
	 *
	 * @var ThemeRegistry
	 */
	private $themes;

	/**
	 * Per-card settings.
	 *
	 * @var CardSettings
	 */
	private $settings;

	/**
	 * Assets.
	 *
	 * @var Assets
	 */
	private $assets;

	/**
	 * Front-end template loader.
	 *
	 * @var TemplateLoader
	 */
	private $templates;

	/**
	 * Front-end vCard and QR endpoints.
	 *
	 * @var FrontendEndpoints
	 */
	private $endpoints;

	/**
	 * Boot the plugin.
	 *
	 * @return void
	 */
	public static function boot(): void {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->dependencies = new Dependencies();
		$this->themes       = new ThemeRegistry( array( MY_ECARD_PATH . 'themes' ), MY_ECARD_VERSION );
		$this->settings     = new CardSettings( $this->themes );
		$this->assets       = new Assets();
		$this->templates    = new TemplateLoader( $this->themes );
		$this->endpoints    = new FrontendEndpoints();

		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ), 20 );
	}

	/**
	 * Enable integrations once every plugin is loaded.
	 *
	 * @return void
	 */
	public function on_plugins_loaded(): void {
		load_plugin_textdomain(
			'wp-ecard-addons',
			false,
			dirname( plugin_basename( MY_ECARD_FILE ) ) . '/languages'
		);

		$this->dependencies->register_admin_notice();

		if ( ! $this->dependencies->is_satisfied() ) {
			return;
		}

		$this->settings->register();
		$this->assets->register();
		$this->templates->register();
		$this->endpoints->register();

		/**
		 * Fires when ECard is available and the addon base is wired.
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action( 'my_ecard_loaded', $this );
	}

	/**
	 * Activation routine.
	 *
	 * No rewrite rules are registered, so nothing is flushed here.
	 *
	 * @return void
	 */
	public static function activate(): void {
		update_option( 'my_ecard_version', MY_ECARD_VERSION );
	}

	/**
	 * Theme registry.
	 *
	 * @return ThemeRegistry
	 */
	public function themes(): ThemeRegistry {
		return $this->themes;
	}

	/**
	 * Dependency detector.
	 *
	 * @return Dependencies
	 */
	public function dependencies(): Dependencies {
		return $this->dependencies;
	}

	/**
	 * Per-card settings.
	 *
	 * @return CardSettings
	 */
	public function settings(): CardSettings {
		return $this->settings;
	}

	/**
	 * Front-end template loader.
	 *
	 * @return TemplateLoader
	 */
	public function template_loader(): TemplateLoader {
		return $this->templates;
	}

	/**
	 * Front-end vCard and QR endpoints.
	 *
	 * @return FrontendEndpoints
	 */
	public function frontend_endpoints(): FrontendEndpoints {
		return $this->endpoints;
	}
}
