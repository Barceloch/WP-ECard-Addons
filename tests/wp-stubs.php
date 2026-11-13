<?php
/**
 * Minimal WordPress stubs for the dependency-free test runner.
 *
 * The addon never unserializes meta: keyed reads return already
 * unserialized values, exactly like WordPress does.
 *
 * @package MyECardExtensions
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

$GLOBALS['my_ecard_test_posts']       = array();
$GLOBALS['my_ecard_test_meta']        = array();
$GLOBALS['my_ecard_test_attachments'] = array();
$GLOBALS['my_ecard_test_styles']      = array();
$GLOBALS['my_ecard_test_scripts']     = array();
$GLOBALS['my_ecard_test_filters']     = array();
$GLOBALS['my_ecard_test_query_vars']  = array();
$GLOBALS['my_ecard_test_transients']  = array();
$GLOBALS['my_ecard_test_status']      = 0;
$GLOBALS['my_ecard_test_headers']     = array();
$GLOBALS['my_ecard_test_singular']    = false;
$GLOBALS['my_ecard_test_queried_id']  = 0;
$GLOBALS['my_ecard_test_is_admin']    = false;
$GLOBALS['my_ecard_test_doing_ajax']  = false;
$GLOBALS['my_ecard_test_is_feed']     = false;
$GLOBALS['my_ecard_test_is_embed']    = false;
$GLOBALS['my_ecard_test_is_preview']  = false;

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * Minimal WP_Post stand-in.
	 */
	class WP_Post {

		/** @var int */
		public $ID = 0;

		/** @var string */
		public $post_type = '';

		/** @var string */
		public $post_status = 'publish';

		/** @var string */
		public $post_content = '';

		/** @var string */
		public $post_title = '';

		/** @var string */
		public $post_name = '';

		/** @var string */
		public $post_password = '';

		/**
		 * Constructor.
		 *
		 * @param array $props Properties.
		 */
		public function __construct( array $props = array() ) {
			foreach ( $props as $key => $value ) {
				$this->{$key} = $value;
			}
		}
	}
}

if ( ! function_exists( 'my_ecard_test_maybe_unserialize' ) ) {
	/**
	 * Emulate maybe_unserialize().
	 *
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	function my_ecard_test_maybe_unserialize( $value ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		if ( ! preg_match( '/^(a|O|s|i|b|d):/', $value ) ) {
			return $value;
		}

		$unserialized = @unserialize( $value, array( 'allowed_classes' => false ) ); // phpcs:ignore

		return false === $unserialized && 'b:0;' !== $value ? $value : $unserialized;
	}
}

if ( ! function_exists( 'my_ecard_test_maybe_serialize' ) ) {
	/**
	 * Emulate maybe_serialize().
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function my_ecard_test_maybe_serialize( $value ) {
		return is_array( $value ) || is_object( $value ) ? serialize( $value ) : $value;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	/**
	 * Get a stubbed post.
	 *
	 * @param int $post_id Post ID.
	 * @return WP_Post|null
	 */
	function get_post( $post_id ) {
		$post_id = (int) $post_id;

		return isset( $GLOBALS['my_ecard_test_posts'][ $post_id ] ) ? $GLOBALS['my_ecard_test_posts'][ $post_id ] : null;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * Mimic WordPress meta reads.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Single value.
	 * @return mixed
	 */
	function get_post_meta( $post_id, $key = '', $single = false ) {
		$post_id = (int) $post_id;
		$all     = isset( $GLOBALS['my_ecard_test_meta'][ $post_id ] ) ? $GLOBALS['my_ecard_test_meta'][ $post_id ] : array();

		if ( '' === $key ) {
			return $all;
		}

		if ( ! isset( $all[ $key ] ) ) {
			return $single ? '' : array();
		}

		$values = array_map( 'my_ecard_test_maybe_unserialize', $all[ $key ] );

		return $single ? $values[0] : $values;
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	/**
	 * Mimic update_post_meta().
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Value.
	 * @return bool
	 */
	function update_post_meta( $post_id, $key, $value ) {
		$post_id = (int) $post_id;

		if ( ! isset( $GLOBALS['my_ecard_test_meta'][ $post_id ] ) ) {
			$GLOBALS['my_ecard_test_meta'][ $post_id ] = array();
		}

		$GLOBALS['my_ecard_test_meta'][ $post_id ][ $key ] = array( my_ecard_test_maybe_serialize( $value ) );

		return true;
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	/**
	 * Mimic delete_post_meta().
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return bool
	 */
	function delete_post_meta( $post_id, $key ) {
		unset( $GLOBALS['my_ecard_test_meta'][ (int) $post_id ][ $key ] );

		return true;
	}
}

if ( ! function_exists( 'metadata_exists' ) ) {
	/**
	 * Mimic metadata_exists().
	 *
	 * @param string $type    Meta type.
	 * @param int    $post_id Object ID.
	 * @param string $key     Meta key.
	 * @return bool
	 */
	function metadata_exists( $type, $post_id, $key ) {
		return isset( $GLOBALS['my_ecard_test_meta'][ (int) $post_id ][ $key ] );
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	/**
	 * Mimic get_the_title().
	 *
	 * @param mixed $post Post object or ID.
	 * @return string
	 */
	function get_the_title( $post = 0 ) {
		$id = $post instanceof WP_Post ? (int) $post->ID : (int) $post;

		if ( isset( $GLOBALS['my_ecard_test_posts'][ $id ] ) ) {
			return (string) $GLOBALS['my_ecard_test_posts'][ $id ]->post_title;
		}

		return isset( $GLOBALS['my_ecard_test_attachments'][ $id ]['title'] ) ? (string) $GLOBALS['my_ecard_test_attachments'][ $id ]['title'] : '';
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	/**
	 * Mimic get_permalink().
	 *
	 * @param mixed $post Post object or ID.
	 * @return string
	 */
	function get_permalink( $post = 0 ) {
		$object = $post instanceof WP_Post ? $post : get_post( (int) $post );

		if ( ! $object instanceof WP_Post ) {
			return '';
		}

		return 'https://example.test/ecard/' . $object->post_name . '/';
	}
}

if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
	/**
	 * Mimic wp_get_attachment_image_url().
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $size          Size.
	 * @return string|false
	 */
	function wp_get_attachment_image_url( $attachment_id, $size = 'thumbnail' ) {
		$attachment_id = (int) $attachment_id;

		if ( ! isset( $GLOBALS['my_ecard_test_attachments'][ $attachment_id ] ) ) {
			return false;
		}

		$key = 'medium' === $size ? 'medium' : 'full';

		return $GLOBALS['my_ecard_test_attachments'][ $attachment_id ][ $key ];
	}
}

if ( ! function_exists( 'wp_attachment_is_image' ) ) {
	/**
	 * Mimic wp_attachment_is_image().
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	function wp_attachment_is_image( $attachment_id ) {
		return isset( $GLOBALS['my_ecard_test_attachments'][ (int) $attachment_id ] );
	}
}

if ( ! function_exists( 'is_post_publicly_viewable' ) ) {
	/**
	 * Mimic is_post_publicly_viewable().
	 *
	 * WordPress ignores the password here: a published, password-protected
	 * post is publicly viewable and gated separately by post_password_required().
	 *
	 * @param mixed $post Post object or ID.
	 * @return bool
	 */
	function is_post_publicly_viewable( $post ) {
		$object = $post instanceof WP_Post ? $post : get_post( (int) $post );

		return $object instanceof WP_Post && 'publish' === $object->post_status;
	}
}

if ( ! function_exists( 'post_password_required' ) ) {
	/**
	 * Mimic post_password_required().
	 *
	 * @param mixed $post Post object or ID.
	 * @return bool
	 */
	function post_password_required( $post = null ) {
		$object = $post instanceof WP_Post ? $post : get_post( (int) $post );

		return $object instanceof WP_Post && '' !== (string) $object->post_password;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * Mimic esc_url_raw() with protocol filtering.
	 *
	 * @param string     $url       URL.
	 * @param array|null $protocols Allowed protocols.
	 * @return string
	 */
	function esc_url_raw( $url, $protocols = null ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return '';
		}

		$scheme = parse_url( $url, PHP_URL_SCHEME );

		if ( false === $scheme || null === $scheme || '' === $scheme ) {
			return $url;
		}

		$allowed = is_array( $protocols ) ? $protocols : array( 'http', 'https', 'mailto', 'tel', 'ftp' );

		return in_array( strtolower( $scheme ), $allowed, true ) ? $url : '';
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Mimic esc_url().
	 *
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url( $url ) {
		return esc_url_raw( $url );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Mimic esc_html().
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Mimic esc_attr().
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_attr( $text ) {
		return esc_html( $text );
	}
}

if ( ! function_exists( 'sanitize_hex_color' ) ) {
	/**
	 * Mimic sanitize_hex_color().
	 *
	 * @param string $color Color.
	 * @return string|null
	 */
	function sanitize_hex_color( $color ) {
		$color = trim( (string) $color );

		return preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color ) ? $color : null;
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * Mimic absint().
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Mimic sanitize_key().
	 *
	 * @param string $key Key.
	 * @return string
	 */
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Mimic sanitize_text_field().
	 *
	 * @param string $value Value.
	 * @return string
	 */
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	/**
	 * Mimic sanitize_email().
	 *
	 * @param string $email Email.
	 * @return string
	 */
	function sanitize_email( $email ) {
		return is_email( $email ) ? $email : '';
	}
}

if ( ! function_exists( 'is_email' ) ) {
	/**
	 * Mimic is_email().
	 *
	 * @param string $email Email.
	 * @return bool
	 */
	function is_email( $email ) {
		return false !== filter_var( (string) $email, FILTER_VALIDATE_EMAIL );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * Mimic wp_strip_all_tags().
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	/**
	 * Mimic wp_kses_post().
	 *
	 * @param string $text HTML.
	 * @return string
	 */
	function wp_kses_post( $text ) {
		return (string) $text;
	}
}

if ( ! function_exists( 'wpautop' ) ) {
	/**
	 * Mimic wpautop() closely enough for theme rendering.
	 *
	 * @param string $text  Text.
	 * @param bool   $br    Whether to convert remaining line breaks.
	 * @return string
	 */
	function wpautop( $text, $br = true ) {
		unset( $br );

		$text = trim( (string) $text );

		if ( '' === $text ) {
			return '';
		}

		$paragraphs = preg_split( '/\R\s*\R/', $text );
		$output     = '';

		foreach ( (array) $paragraphs as $paragraph ) {
			$paragraph = trim( (string) $paragraph );

			if ( '' === $paragraph ) {
				continue;
			}

			$output .= '<p>' . $paragraph . "</p>\n";
		}

		return $output;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Mimic wp_unslash().
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Apply registered filter callbacks.
	 *
	 * @param string $hook  Hook.
	 * @param mixed  $value Value.
	 * @param mixed  ...$args Extra arguments.
	 * @return mixed
	 */
	function apply_filters( $hook, $value, ...$args ) {
		$registry = isset( $GLOBALS['my_ecard_test_filters'][ $hook ] ) ? $GLOBALS['my_ecard_test_filters'][ $hook ] : array();

		if ( array() === $registry ) {
			return $value;
		}

		ksort( $registry );

		foreach ( $registry as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$value = call_user_func_array( $callback, array_merge( array( $value ), $args ) );
			}
		}

		return $value;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Register a filter callback.
	 *
	 * @param string   $hook     Hook.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $args     Accepted args.
	 * @return bool
	 */
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		unset( $args );

		if ( ! isset( $GLOBALS['my_ecard_test_filters'][ $hook ] ) ) {
			$GLOBALS['my_ecard_test_filters'][ $hook ] = array();
		}

		if ( ! isset( $GLOBALS['my_ecard_test_filters'][ $hook ][ $priority ] ) ) {
			$GLOBALS['my_ecard_test_filters'][ $hook ][ $priority ] = array();
		}

		$GLOBALS['my_ecard_test_filters'][ $hook ][ $priority ][] = $callback;

		return true;
	}
}

if ( ! function_exists( 'remove_all_filters' ) ) {
	/**
	 * Remove every callback for a hook.
	 *
	 * @param string $hook Hook.
	 * @return bool
	 */
	function remove_all_filters( $hook ) {
		unset( $GLOBALS['my_ecard_test_filters'][ $hook ] );

		return true;
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Mimic translation.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Mimic escaped translation.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_html__( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	/**
	 * Mimic escaped translation echo.
	 *
	 * @param string $text Text.
	 * @return void
	 */
	function esc_html_e( $text ) {
		echo esc_html( $text );
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	/**
	 * Mimic escaped attribute translation echo.
	 *
	 * @param string $text Text.
	 * @return void
	 */
	function esc_attr_e( $text ) {
		echo esc_attr( $text );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	/**
	 * Mimic escaped attribute translation.
	 *
	 * @param string $text   Text.
	 * @param string $domain Optional text domain.
	 * @return string
	 */
	function esc_attr__( $text, $domain = 'default' ) {
		unset( $domain );

		return esc_attr( $text );
	}
}

if ( ! function_exists( 'checked' ) ) {
	/**
	 * Mimic checked().
	 *
	 * @param mixed  $checked  Checked value.
	 * @param mixed  $current  Current value.
	 * @param bool   $display  Whether to echo.
	 * @return string
	 */
	function checked( $checked, $current = true, $display = true ) {
		$result = (string) $checked === (string) $current ? " checked='checked'" : '';

		if ( $display ) {
			echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return $result;
	}
}

if ( ! function_exists( 'selected' ) ) {
	/**
	 * Mimic selected().
	 *
	 * @param mixed $selected Selected value.
	 * @param mixed $current  Current value.
	 * @param bool  $display  Whether to echo.
	 * @return string
	 */
	function selected( $selected, $current = true, $display = true ) {
		$result = (string) $selected === (string) $current ? " selected='selected'" : '';

		if ( $display ) {
			echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return $result;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Mimic add_action() without storing callbacks.
	 *
	 * @param string   $hook     Hook.
	 * @param callable $callback Callback.
	 * @param int      $priority Priority.
	 * @param int      $args     Args.
	 * @return bool
	 */
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	/**
	 * Mimic do_action().
	 *
	 * @param string $hook Hook.
	 * @param mixed  ...$args Arguments.
	 * @return void
	 */
	function do_action( $hook, ...$args ) {
		unset( $hook, $args );
	}
}

if ( ! function_exists( 'add_meta_box' ) ) {
	/**
	 * Mimic add_meta_box().
	 *
	 * @return void
	 */
	function add_meta_box() {
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	/**
	 * Mimic wp_nonce_field().
	 *
	 * @param string $action Nonce action.
	 * @param string $name   Field name.
	 * @return void
	 */
	function wp_nonce_field( $action, $name ) {
		printf(
			'<input type="hidden" name="%s" value="stub-nonce" />',
			esc_attr( $name )
		);
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	/**
	 * Mimic wp_verify_nonce() using a togglable global.
	 *
	 * @param string $nonce  Nonce.
	 * @param string $action Nonce action.
	 * @return int|false
	 */
	function wp_verify_nonce( $nonce, $action ) {
		unset( $nonce, $action );

		return isset( $GLOBALS['my_ecard_test_nonce_valid'] ) && $GLOBALS['my_ecard_test_nonce_valid'] ? 1 : false;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Mimic current_user_can() using a togglable global.
	 *
	 * @param string $capability Capability.
	 * @return bool
	 */
	function current_user_can( $capability ) {
		unset( $capability );

		return isset( $GLOBALS['my_ecard_test_can_edit'] ) ? (bool) $GLOBALS['my_ecard_test_can_edit'] : true;
	}
}

if ( ! function_exists( 'wp_is_post_revision' ) ) {
	/**
	 * Mimic wp_is_post_revision().
	 *
	 * @param int $post_id Post ID.
	 * @return false
	 */
	function wp_is_post_revision( $post_id ) {
		unset( $post_id );

		return false;
	}
}

if ( ! function_exists( 'wp_is_post_autosave' ) ) {
	/**
	 * Mimic wp_is_post_autosave().
	 *
	 * @param int $post_id Post ID.
	 * @return false
	 */
	function wp_is_post_autosave( $post_id ) {
		unset( $post_id );

		return false;
	}
}

if ( ! function_exists( 'wp_get_attachment_image' ) ) {
	/**
	 * Mimic wp_get_attachment_image().
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $size          Size.
	 * @return string
	 */
	function wp_get_attachment_image( $attachment_id, $size = 'thumbnail' ) {
		$url = wp_get_attachment_image_url( $attachment_id, $size );

		return is_string( $url ) ? '<img src="' . esc_url( $url ) . '" alt="" />' : '';
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * Mimic is_admin().
	 *
	 * @return bool
	 */
	function is_admin() {
		return ! empty( $GLOBALS['my_ecard_test_is_admin'] );
	}
}

if ( ! function_exists( 'wp_doing_ajax' ) ) {
	/**
	 * Mimic wp_doing_ajax().
	 *
	 * @return bool
	 */
	function wp_doing_ajax() {
		return ! empty( $GLOBALS['my_ecard_test_doing_ajax'] );
	}
}

if ( ! function_exists( 'is_feed' ) ) {
	/**
	 * Mimic is_feed().
	 *
	 * @return bool
	 */
	function is_feed() {
		return ! empty( $GLOBALS['my_ecard_test_is_feed'] );
	}
}

if ( ! function_exists( 'is_embed' ) ) {
	/**
	 * Mimic is_embed().
	 *
	 * @return bool
	 */
	function is_embed() {
		return ! empty( $GLOBALS['my_ecard_test_is_embed'] );
	}
}

if ( ! function_exists( 'is_preview' ) ) {
	/**
	 * Mimic is_preview().
	 *
	 * @return bool
	 */
	function is_preview() {
		return ! empty( $GLOBALS['my_ecard_test_is_preview'] );
	}
}

if ( ! function_exists( 'is_singular' ) ) {
	/**
	 * Mimic is_singular().
	 *
	 * @param string|string[] $post_types Optional post types.
	 * @return bool
	 */
	function is_singular( $post_types = '' ) {
		$state = isset( $GLOBALS['my_ecard_test_singular'] ) ? $GLOBALS['my_ecard_test_singular'] : false;

		if ( true !== $state ) {
			return false;
		}

		unset( $post_types );

		return true;
	}
}

if ( ! function_exists( 'get_queried_object_id' ) ) {
	/**
	 * Mimic get_queried_object_id().
	 *
	 * @return int
	 */
	function get_queried_object_id() {
		return isset( $GLOBALS['my_ecard_test_queried_id'] ) ? (int) $GLOBALS['my_ecard_test_queried_id'] : 0;
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	/**
	 * Mimic wp_enqueue_style().
	 *
	 * @param string   $handle Handle.
	 * @param string   $src    Source URL.
	 * @param string[] $deps   Dependencies.
	 * @param string   $ver    Version.
	 * @return void
	 */
	function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false ) {
		unset( $deps );

		$GLOBALS['my_ecard_test_styles'][ $handle ] = array(
			'src' => $src,
			'ver' => $ver,
		);
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	/**
	 * Mimic wp_enqueue_script().
	 *
	 * @param string   $handle Handle.
	 * @param string   $src    Source URL.
	 * @param string[] $deps   Dependencies.
	 * @param string   $ver    Version.
	 * @param bool     $footer Whether the script loads in the footer.
	 * @return void
	 */
	function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $footer = false ) {
		unset( $deps );

		$GLOBALS['my_ecard_test_scripts'][ $handle ] = array(
			'src'    => $src,
			'ver'    => $ver,
			'footer' => $footer,
		);
	}
}

if ( ! function_exists( 'get_post_type' ) ) {
	/**
	 * Mimic get_post_type().
	 *
	 * @param mixed $post Post object or ID.
	 * @return string|false
	 */
	function get_post_type( $post = null ) {
		if ( null === $post ) {
			$post = get_queried_object_id();
		}

		$object = $post instanceof WP_Post ? $post : get_post( (int) $post );

		if ( ! $object instanceof WP_Post ) {
			return false;
		}

		return '' === (string) $object->post_type ? false : (string) $object->post_type;
	}
}

if ( ! function_exists( 'set_query_var' ) ) {
	/**
	 * Mimic set_query_var().
	 *
	 * @param string $key   Query var.
	 * @param mixed  $value Value.
	 * @return void
	 */
	function set_query_var( $key, $value ) {
		$GLOBALS['my_ecard_test_query_vars'][ $key ] = $value;
	}
}

if ( ! function_exists( 'get_query_var' ) ) {
	/**
	 * Mimic get_query_var().
	 *
	 * @param string $key     Query var.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	function get_query_var( $key, $default = '' ) {
		return array_key_exists( $key, $GLOBALS['my_ecard_test_query_vars'] ) ? $GLOBALS['my_ecard_test_query_vars'][ $key ] : $default;
	}
}

if ( ! function_exists( 'language_attributes' ) ) {
	/**
	 * Mimic language_attributes() and mark the call for assertions.
	 *
	 * @return void
	 */
	function language_attributes() {
		echo 'lang="en-US"';
	}
}

if ( ! function_exists( 'bloginfo' ) ) {
	/**
	 * Mimic bloginfo().
	 *
	 * @param string $show Info key.
	 * @return void
	 */
	function bloginfo( $show ) {
		if ( 'charset' === $show ) {
			echo 'UTF-8';
		}
	}
}

if ( ! function_exists( 'body_class' ) ) {
	/**
	 * Mimic body_class().
	 *
	 * @param string|string[] $classes Extra classes.
	 * @return void
	 */
	function body_class( $classes = '' ) {
		$list = array_merge( array( 'single', 'single-ecard_digital_card' ), (array) $classes );

		echo 'class="' . esc_attr( implode( ' ', array_unique( $list ) ) ) . '"';
	}
}

if ( ! function_exists( 'wp_head' ) ) {
	/**
	 * Mimic wp_head() with an identifiable marker.
	 *
	 * @return void
	 */
	function wp_head() {
		echo '<!--my-ecard-test-head-->';
	}
}

if ( ! function_exists( 'wp_body_open' ) ) {
	/**
	 * Mimic wp_body_open() with an identifiable marker.
	 *
	 * @return void
	 */
	function wp_body_open() {
		echo '<!--my-ecard-test-body-open-->';
	}
}

if ( ! function_exists( 'wp_footer' ) ) {
	/**
	 * Mimic wp_footer() with an identifiable marker.
	 *
	 * @return void
	 */
	function wp_footer() {
		echo '<!--my-ecard-test-footer-->';
	}
}

if ( ! function_exists( 'get_the_password_form' ) ) {
	/**
	 * Mimic get_the_password_form().
	 *
	 * @param mixed $post Post object or ID.
	 * @return string
	 */
	function get_the_password_form( $post = 0 ) {
		$object = $post instanceof WP_Post ? $post : get_post( (int) $post );
		$action = $object instanceof WP_Post ? get_permalink( $object ) : '';

		return '<form class="post-password-form" action="' . esc_url( $action ) . '" method="post">'
			. '<label>Password: <input name="post_password" type="password" /></label>'
			. '<input type="submit" name="Submit" value="Enter" /></form>';
	}
}

if ( ! function_exists( 'status_header' ) ) {
	/**
	 * Mimic status_header().
	 *
	 * @param int $status HTTP status code.
	 * @return void
	 */
	function status_header( $status ) {
		$GLOBALS['my_ecard_test_status'] = (int) $status;
	}
}

if ( ! function_exists( 'nocache_headers' ) ) {
	/**
	 * Mimic nocache_headers().
	 *
	 * @return void
	 */
	function nocache_headers() {
		$GLOBALS['my_ecard_test_headers'][] = 'Cache-Control: no-store';
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * Mimic get_transient().
	 *
	 * @param string $key Transient key.
	 * @return mixed
	 */
	function get_transient( $key ) {
		return array_key_exists( $key, $GLOBALS['my_ecard_test_transients'] )
			? $GLOBALS['my_ecard_test_transients'][ $key ]
			: false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * Mimic set_transient().
	 *
	 * @param string $key   Transient key.
	 * @param mixed  $value Value.
	 * @param int    $ttl   Lifetime.
	 * @return bool
	 */
	function set_transient( $key, $value, $ttl = 0 ) {
		unset( $ttl );
		$GLOBALS['my_ecard_test_transients'][ $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * Mimic delete_transient().
	 *
	 * @param string $key Transient key.
	 * @return bool
	 */
	function delete_transient( $key ) {
		unset( $GLOBALS['my_ecard_test_transients'][ $key ] );

		return true;
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * Mimic the array form of add_query_arg().
	 *
	 * @param array  $args Query args.
	 * @param string $url  Base URL.
	 * @return string
	 */
	function add_query_arg( $args, $url ) {
		$args = is_array( $args ) ? $args : array();
		$url  = (string) $url;
		$sep  = false === strpos( $url, '?' ) ? '?' : '&';

		return $url . $sep . http_build_query( $args );
	}
}
